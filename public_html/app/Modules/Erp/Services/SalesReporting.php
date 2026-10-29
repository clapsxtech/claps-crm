<?php

namespace App\Modules\Erp\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Event counts and cohort outcomes; never calculates commission or moves money. */
class SalesReporting
{
    public function read(int $unit, User $actor, array $input): array
    {
        (new Access())->unit($actor, $unit, false);
        $permissions = new TeamPermissions();
        abort_unless($permissions->allows($actor, $unit, 'page', 'admissions'), 403);
        $zone = DB::table('erp_units')->where('id', $unit)->value('timezone');
        $today = now($zone)->toDateString();
        $dates = Validator::make($input + ['report_from'=>$today, 'report_to'=>$today], [
            'report_from'=>'required|date_format:Y-m-d',
            'report_to'=>'required|date_format:Y-m-d|after_or_equal:report_from',
        ])->validate();
        $start = CarbonImmutable::parse($dates['report_from'], $zone)->startOfDay();
        $end = CarbonImmutable::parse($dates['report_to'], $zone)->addDay()->startOfDay();
        abort_if($start->diffInDays($end) > 93, 422, 'Choose up to 93 days.');
        // CRM activities and receipts use the installation timezone for storage.
        $from = $start->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $until = $end->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $ids = fn () => (new Admissions())->visibleLeads($unit, $actor->id)->reorder()->select('l.id');
        $staff = DB::table('erp_lead_activities as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->where('a.unit_id', $unit)->whereIn('a.lead_id', $ids())->where('a.occurred_at', '>=', $from)->where('a.occurred_at', '<', $until)
            ->select('a.actor_id', 'u.name')->selectRaw("SUM(CASE WHEN a.kind = 'created' THEN 1 ELSE 0 END) AS enquiries, SUM(CASE WHEN a.kind = 're_enquiry' THEN 1 ELSE 0 END) AS repeats, SUM(CASE WHEN a.kind = 'contact_attempt' THEN 1 ELSE 0 END) AS contacts, SUM(CASE WHEN a.kind = 'demo_scheduled' THEN 1 ELSE 0 END) AS demos, SUM(CASE WHEN a.kind = 'demo_feedback' THEN 1 ELSE 0 END) AS feedback, SUM(CASE WHEN a.kind = 'joined' THEN 1 ELSE 0 END) AS registrations")
            ->groupBy('a.actor_id', 'u.name')->orderBy('u.name')->get();
        $source = "COALESCE(NULLIF(TRIM(l.source), ''), 'Unattributed')";
        $campaigns = DB::table('erp_leads as l')->where('l.unit_id', $unit)->whereIn('l.id', $ids())
            ->whereBetween('l.enquiry_date', [$dates['report_from'], $dates['report_to']])
            ->selectRaw("$source AS source, COUNT(*) AS enquiries, SUM(CASE WHEN l.status = 'JOINED' THEN 1 ELSE 0 END) AS joined, SUM(CASE WHEN l.status = 'LOST' THEN 1 ELSE 0 END) AS lost")
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM erp_demos d WHERE d.unit_id = l.unit_id AND d.lead_id = l.id) THEN 1 ELSE 0 END) AS demo_leads")
            ->groupByRaw($source)->orderBy('source')->get();
        $due = DB::table('erp_leads as l')->where('l.unit_id', $unit)->whereIn('l.id', $ids())->where('l.status', 'LEAD')
            ->selectRaw('SUM(CASE WHEN next_follow_up_on IS NULL THEN 1 ELSE 0 END) AS unscheduled')
            ->selectRaw('SUM(CASE WHEN next_follow_up_on = ? THEN 1 ELSE 0 END) AS today', [$today])
            ->selectRaw('SUM(CASE WHEN next_follow_up_on < ? AND next_follow_up_on >= ? THEN 1 ELSE 0 END) AS recent', [$today, now($zone)->subDays(3)->toDateString()])
            ->selectRaw('SUM(CASE WHEN next_follow_up_on < ? THEN 1 ELSE 0 END) AS older', [now($zone)->subDays(3)->toDateString()])->first();
        $collections = null;
        if ($permissions->allows($actor, $unit, 'page', 'money')) {
            // Count original receipts and their reversals on each entry's actual date.
            // No lead join: one receipt cannot multiply through repeated enquiries.
            $collections = DB::table('erp_ledger as e')->leftJoin('erp_ledger as original', function ($join) {
                $join->on('original.id', '=', 'e.reverses_id')->on('original.unit_id', '=', 'e.unit_id');
            })->where('e.unit_id', $unit)->whereNull('e.teacher_id')->whereNotNull('e.student_id')
                ->where('e.occurred_at', '>=', $from)->where('e.occurred_at', '<', $until)
                ->where(function ($q) { $q->where('e.kind', 'payment')->orWhere(function ($q) { $q->where('original.kind', 'payment')->whereNull('original.teacher_id'); }); })
                ->select('e.currency')->selectRaw('SUM(e.amount_paise) AS net_paise')->groupBy('e.currency')->get();
        }
        return compact('dates', 'zone', 'staff', 'campaigns', 'due', 'collections');
    }
}
