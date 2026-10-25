<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\Access;
use App\Modules\Erp\Services\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentProfileController extends Controller
{
    public function show(Request $request, int $unit, int $student)
    {
        (new Access())->unit($request->user(), $unit, false);
        $rules = [];
        foreach (['assignments', 'agreements', 'lessons', 'attendance', 'ledger', 'requests', 'followups', 'tasks'] as $section) {
            $rules[$section.'_page'] = 'sometimes|integer|min:1|max:1000000';
        }
        $request->validate($rules);
        $business = DB::table('erp_units')->find($unit);
        abort_unless($business, 404);
        $profile = (new StudentProfile())->read($unit, $student);
        return view('erp.student-profile', $profile + ['business' => $business]);
    }
}
