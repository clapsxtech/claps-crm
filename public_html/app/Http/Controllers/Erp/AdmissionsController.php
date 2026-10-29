<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\Admissions;
use App\Modules\Erp\Services\SalesPipeline;
use App\Modules\Erp\Services\Access;
use App\Modules\Erp\Services\MoneyInput;
use App\Modules\Erp\Services\TeamPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionsController extends Controller
{
    public function index(Request $request, int $unit)
    {
        (new Access())->unit($request->user(), $unit, false);
        $permissions = new TeamPermissions();
        abort_unless($permissions->allows($request->user(), $unit, 'page', 'admissions'), 403);
        $request->validate([
            'q'=>'nullable|string|max:100', 'status'=>'nullable|in:LEAD,JOINED,LOST',
            'followup'=>'nullable|in:today,overdue,unscheduled', 'lead_id'=>'nullable|integer|min:1',
            'report'=>'nullable|boolean', 'view'=>'nullable|in:pipeline,today,demos,reminders,transfers,reports,new',
            'stage'=>'nullable|in:'.implode(',', array_keys(SalesPipeline::STAGES)), 'hot'=>'nullable|boolean',
            'demo_scope'=>'nullable|in:upcoming,all',
        ]);
        $actor = (int) $request->user()->id;
        $business = DB::table('erp_units')->find($unit);
        $admissions = new Admissions();
        $today = now($business->timezone)->toDateString();
        $crmView = $request->query('view', $request->boolean('report') ? 'reports' : 'pipeline');
        $canManage = in_array($permissions->role($request->user(), $unit), ['superadmin','admin','staff','team_manager'], true);
        $visible = fn () => $admissions->visibleLeads($unit, $actor);
        $visibleIds = fn () => $visible()->reorder()->select('l.id');
        $stageSql = SalesPipeline::expression();
        $stageCounts = array_fill_keys(array_keys(SalesPipeline::STAGES), 0);
        foreach ($visible()->reorder()->select([])->selectRaw($stageSql.' AS stage, COUNT(*) AS aggregate')->groupByRaw($stageSql)->get() as $row) $stageCounts[$row->stage] = (int) $row->aggregate;
        $followupCounts = $visible()->reorder()->where('l.status', 'LEAD')->select([])
            ->selectRaw('SUM(CASE WHEN l.next_follow_up_on = ? THEN 1 ELSE 0 END) AS today', [$today])
            ->selectRaw('SUM(CASE WHEN l.next_follow_up_on < ? THEN 1 ELSE 0 END) AS overdue', [$today])
            ->selectRaw('SUM(CASE WHEN l.next_follow_up_on IS NULL THEN 1 ELSE 0 END) AS unscheduled')->first();
        $followup = $request->query('followup', $crmView === 'today' ? 'today' : '');
        $leads = null;
        if (!$request->filled('lead_id') && in_array($crmView, ['pipeline','today'], true)) {
            $query = $admissions->visibleLeads($unit, $actor, $request->query('q'), $request->query('status'))->selectRaw($stageSql.' AS crm_stage');
            if ($request->filled('stage')) $query->whereRaw($stageSql.' = ?', [$request->query('stage')]);
            if ($request->boolean('hot')) $query->where('l.is_hot', true);
            if ($followup) {
                $query->where('l.status', 'LEAD');
                if ($followup === 'unscheduled') $query->whereNull('l.next_follow_up_on');
                else $query->where('l.next_follow_up_on', $followup === 'today' ? '=' : '<', $today);
                $query->reorder()->orderBy('l.next_follow_up_on')->orderByDesc('l.is_hot')->orderBy('l.id');
            }
            $leads = $query->paginate(20, ['*'], 'leads_page')->withQueryString();
        }
        $selectedLead = null;
        $activities = null;
        $leadDemos = collect();
        $members = collect();
        if ($request->filled('lead_id')) {
            $selectedLead = $visible()->selectRaw($stageSql.' AS crm_stage')->where('l.id', $request->integer('lead_id'))->first();
            abort_unless($selectedLead, 404);
            $activities = DB::table('erp_lead_activities as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->where('a.unit_id', $unit)->where('a.lead_id', $selectedLead->id)->select('a.*', 'u.name as actor_name')
                ->orderByDesc('a.occurred_at')->orderByDesc('a.id')->paginate(20, ['*'], 'history_page')->withQueryString();
            $leadDemos = DB::table('erp_demos')->where('unit_id', $unit)->where('lead_id', $selectedLead->id)
                ->orderByDesc('scheduled_on')->orderByDesc('id')->paginate(10, ['*'], 'history_demos_page')->withQueryString();
            $members = DB::table('users as u')->join('erp_memberships as m', 'm.user_id', '=', 'u.id')
                ->where('m.unit_id', $unit)->whereIn('m.role', ['sales_followups','team_manager','admin','staff'])
                ->where('u.status', 1)->whereNull('u.deleted_at')->select('u.id','u.name')->orderBy('u.name')->get();
        }
        $demos = null;
        if ($crmView === 'demos' && !$selectedLead) {
            $query = DB::table('erp_demos as d')
                ->join('erp_leads as l', function ($join) { $join->on('l.id','=','d.lead_id')->on('l.unit_id','=','d.unit_id'); })
                ->leftJoin('erp_teachers as t','t.id','=','d.teacher_id')->leftJoin('erp_subjects as s','s.id','=','d.subject_id')
                ->where('d.unit_id',$unit)->whereIn('d.lead_id',$visibleIds())
                ->select('d.*','l.name as lead_name','t.name as teacher_name','s.name as subject_name');
            if ($request->query('demo_scope','upcoming') === 'upcoming') $query->whereIn('d.status',['SCHEDULED','RESCHEDULED'])->where('d.scheduled_on','>=',$today)->orderBy('d.scheduled_on')->orderBy('d.scheduled_time');
            else $query->orderByDesc('d.scheduled_on')->orderByDesc('d.id');
            $demos = $query->paginate(20, ['*'], 'demos_page')->withQueryString();
        }
        $reminders = null;
        if (in_array($crmView, ['today','reminders'], true) && !$selectedLead) {
            $query = DB::table('erp_lead_reminders as r')->join('erp_leads as l', function ($join) { $join->on('l.id','=','r.lead_id')->on('l.unit_id','=','r.unit_id'); })
                ->where('r.unit_id',$unit)->whereIn('r.lead_id',$visibleIds())->where('r.is_completed',false)
                ->select('r.*','l.name as lead_name')->orderBy('r.due_on')->orderBy('r.due_time')->orderBy('r.id');
            if ($crmView === 'today') $query->where('r.due_on','<=',$today);
            $reminders = $query->paginate(20, ['*'], 'reminders_page')->withQueryString();
        }
        // Recipients may review a transfer invitation before they gain access to the lead itself.
        $transferQuery = DB::table('erp_lead_transfers as x')->join('erp_leads as l', function ($join) { $join->on('l.id','=','x.lead_id')->on('l.unit_id','=','x.unit_id'); })
            ->leftJoin('users as sender','sender.id','=','x.from_user_id')->leftJoin('users as recipient','recipient.id','=','x.to_user_id')
            ->where('x.unit_id',$unit)->where('x.status','pending');
        if (!$canManage) $transferQuery->where(fn ($q) => $q->where('x.from_user_id',$actor)->orWhere('x.to_user_id',$actor));
        $transferCount = (clone $transferQuery)->count();
        $transfers = null;
        if ($crmView === 'transfers' || $selectedLead) {
            if ($selectedLead) $transferQuery->where('x.lead_id',$selectedLead->id);
            $transfers = $transferQuery->select('x.*','l.name as lead_name','sender.name as sender_name','recipient.name as recipient_name')->orderByDesc('x.id')->paginate(20, ['*'], 'transfers_page')->withQueryString();
        }
        $stats = [
            'Active leads' => array_sum($stageCounts) - $stageCounts['joined'] - $stageCounts['lost'],
            'Due today' => (int) $followupCounts->today,
            'Joined this month' => $visible()->where('l.status','JOINED')->whereBetween('l.joined_on',[now($business->timezone)->startOfMonth()->toDateString(),now($business->timezone)->endOfMonth()->toDateString()])->count(),
        ];
        $salesReport = $crmView === 'reports' ? (new \App\Modules\Erp\Services\SalesReporting())->read($unit, $request->user(), $request->only('report_from','report_to')) : null;
        return view('erp.admissions', compact('business','crmView','canManage','stageCounts','followupCounts','followup','leads','selectedLead','activities','leadDemos','members','demos','reminders','transfers','transferCount','stats','today','salesReport'));
    }

    public function mutate(Request $request, int $unit, string $action)
    {
        (new Access())->unit($request->user(), $unit);
        $service = new Admissions();
        $payload = $request->except('_token', '_method', 'return_page');
        if ($action === 'admission-convert' && array_key_exists('payment_amount_inr', $payload) && $payload['payment_amount_inr'] !== '') {
            $payload['payment_amount_paise'] = MoneyInput::toPaise($payload['payment_amount_inr'], 'payment_amount_inr', 100000000000, false);
            unset($payload['payment_amount_inr']);
        }
        $methods = [
            'admission-create-lead' => 'createLead',
            'admission-update-lead' => 'updateLead',
            'admission-schedule-demo' => 'scheduleDemo',
            'admission-update-demo' => 'updateDemo',
            'admission-followup' => 'recordFollowup',
            'admission-reminder' => 'createReminder',
            'admission-complete-reminder' => 'completeReminder',
            'admission-transfer' => 'requestTransfer',
            'admission-resolve-transfer' => 'resolveTransfer',
            'admission-convert' => 'convertLead',
        ];
        abort_unless(isset($methods[$action]), 404);
        $result = $service->{$methods[$action]}($unit, (int) $request->user()->id, $payload);
        if ($request->expectsJson()) return response()->json(['result' => $result]);
        $destination = [$unit, 'admissions'];
        if ($action === 'admission-update-lead') $destination['lead_id'] = $result->id;
        $returnView = $request->input('return_view');
        if (in_array($returnView, ['pipeline','today','demos','reminders','transfers','detail'], true)) {
            $destination = [$unit, 'admissions', 'view'=>$returnView === 'detail' ? 'pipeline' : $returnView];
            $leadId = in_array($action, ['admission-create-lead','admission-update-lead'], true) ? $result->id : ($result->lead_id ?? $payload['lead_id'] ?? null);
            if ($returnView === 'detail' && $leadId && $service->visibleLeads($unit, (int) $request->user()->id)->where('l.id', $leadId)->exists()) $destination['lead_id'] = $leadId;
        }
        return redirect()->route('erp.business', $destination)->with('status', 'Saved. Your CRM is up to date.');
    }
}
