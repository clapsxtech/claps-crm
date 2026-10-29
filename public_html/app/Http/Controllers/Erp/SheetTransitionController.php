<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access,TeamPermissions};
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SheetTransitionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(config('sheet_transition.enabled'),404);
        $policy = new SiteAccessPolicy(); $policy->assertSiteMember($request->user());
        $administrator = $policy->isAdministrator($request->user());
        $units = (new Access())->units($request->user());
        $request->validate(['unit'=>'nullable|integer|min:1','source'=>'nullable|string|max:40','review'=>'nullable|in:1','q'=>'nullable|string|max:100']);
        $unit = $request->integer('unit') ?: (int)($units->first()->id ?? 0);
        $business = null;
        if ($unit) { (new Access())->unit($request->user(),$unit,false); $business = $units->firstWhere('id',$unit); }
        $sources = array_filter(config('sheet_transition.sources'), fn($s)=>$administrator || ($unit && (new TeamPermissions())->allows($request->user(),$unit,'page',$s['page'])));
        $key = $request->input('source',array_key_first($sources));
        abort_unless($key && isset($sources[$key]),403);
        $states = DB::table('erp_sheet_sources')->whereIn('source_key',array_keys($sources))->get()->keyBy('source_key');
        $review = $request->boolean('review');
        abort_if($review && !$administrator,403);
        $run = $review
            ? DB::table('erp_sheet_runs')->where('id',$states[$key]->latest_run_id ?? '')->first()
            : DB::table('erp_sheet_runs')->where('id',$states[$key]->active_run_id ?? '')->first();
        if ($run && $run->status === 'unchanged') $run = DB::table('erp_sheet_runs')->where('id',$states[$key]->active_run_id ?? '')->first();
        $query = DB::table('erp_sheet_records')->where('run_id',$run->id ?? '');
        if (!$review) $query->where('unit_id',$unit)->whereNull('issue');
        if ($request->filled('q')) $query->where('source_identity',$request->input('q'));
        $records = $query->orderBy('source_row')->paginate(50)->withQueryString();
        $rows = $records->getCollection()->map(function ($row) { $row->data = json_decode($row->payload,true,64,JSON_THROW_ON_ERROR); return $row; });
        $columns = array_keys($rows->first()->data ?? []);
        $success = $states[$key]->last_success_at ?? null;
        $stale = !$success || \Carbon\Carbon::parse($success)->lt(now()->subMinutes(config('sheet_transition.stale_minutes')));
        return response()->view('erp.sheet-transition',compact('sources','states','key','run','records','rows','columns','review','administrator','units','unit','business','stale'))->header('Cache-Control','private, no-store');
    }
}
