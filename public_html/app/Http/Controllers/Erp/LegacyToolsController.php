<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\Access;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;

class LegacyToolsController extends Controller
{
    public function index(Request $request)
    {
        $policy = new SiteAccessPolicy();
        $policy->assertSiteMember($request->user());
        $units = (new Access())->units($request->user());
        $administrator = $policy->isAdministrator($request->user());
        return view('erp.legacy', compact('units', 'administrator'));
    }
}
