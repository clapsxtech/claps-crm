<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;
class RoleController extends Controller
{   
    public function index()
    {   
        // if (!auth()->user()->can('administration')) {
        //     abort(403, 'Unauthorized action.');
        // }
        if (request()->ajax()) {
            $roles = Role::select(['name', 'id']);
            return DataTables::of($roles)
                ->addColumn('action', function ($row) {
                        $action = '';
                        // if (auth()->user()->can('administration')) {
                            $action .= '<a href="'.url('admin/roles').'/'.$row->id.'/edit" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i> EDIT</a>';
                        // }
                        // if (auth()->user()->can('administration')) {
                        $action .= '&nbsp
                        <button data-href="'.route('delete',['roles',$row->id]).'" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                        // }
                        return $action;
                })
                ->removeColumn('id')
                ->rawColumns([1])
                ->make(false);
        }
        return view('admin.role.index');
    }
    public function create()
    {   
        // if (!auth()->user()->can('administration')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $permission = Permission::select('id','name')->get();
        return view('admin.role.create')->with(compact('permission'));
    }
    public function store(Request $request)
    {
        // if (!auth()->user()->can('administration')) {
        //     abort(403, 'Unauthorized action.');
        // }
        try {
            $role_name = $request->input('name');
            $permissions = $request->input('permissions');
            $count = Role::where('name', $role_name)->count();
            if ($count == 0) {
                $role = Role::create([
                            'name' => $role_name ,
                        ]);
                $this->__createPermissionIfNotExists($permissions);
                if (!empty($permissions)) {
                    $role->syncPermissions($permissions);
                }
                $output = ['status' => 'success','message' =>'successfully added'];
            } else {
                $output = ['status' => 'warning','message' =>'Role already exist'];
            }
        } catch (\Exception $e) {
            $output = ['status' => 'danger','message' =>$e->getMessage()];
        }
        return redirect('admin/roles')->with($output);
    }
    public function edit($id)
    {   
        // if (!auth()->user()->can('administration')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $permission = Permission::select('id','name')->get();
        $role = Role::with(['permissions'])->find($id);
        $role_permissions = [];
        foreach ($role->permissions as $role_perm) {
            $role_permissions[] = $role_perm->name;
        }
        return view('admin.role.edit')->with(compact('role', 'role_permissions','permission'));
    }
    public function update(Request $request, $id)
    {
        // if (!auth()->user()->can('administration')) {
        //     abort(403, 'Unauthorized action.');
        // }
        try {
            $role_name = $request->input('name');
            $permissions = $request->input('permissions');
            $count = Role::where('name', $role_name)
                        ->where('id', '!=', $id)
                        ->count();
            if ($count == 0) {
                $role = Role::findOrFail($id);
                if (!$role->is_default || $role->name == 'Cashier') {
                    if ($role->name == 'Cashier') {
                        $role->is_default = 0;
                    }
                    $role->name = $role_name;
                    $role->save();
                    $this->__createPermissionIfNotExists($permissions);
                    if (!empty($permissions)) {
                        $role->syncPermissions($permissions);
                    }
                    $output = ['status' => 'success','message' =>'successfully updated'];
                } else {
                    $output = ['status' => 'warning','message' =>'default role'];
                }
            } else {
                $output = ['status' => 'danger','message' =>'role exist'];
            }
        } catch (\Exception $e) {
            $output = ['status' => false,'message' =>$e->getMessage()];
        }
        return redirect('admin/roles')->with($output);
    }

    private function __createPermissionIfNotExists($permissions)
    {
        $exising_permissions = Permission::whereIn('name', $permissions)
                                    ->pluck('name')
                                    ->toArray();
        
        $non_existing_permissions = array_diff($permissions, $exising_permissions);
        if (!empty($non_existing_permissions)) {
            foreach ($non_existing_permissions as $new_permission) {
                $time_stamp = now();
                Permission::create([
                    'name' => $new_permission,
                    'guard_name' => 'admin'
                ]);
            }
        }
    }
    public function destroy($id)
    {   
        // if (!auth()->user()->can('administration')) {
        //     return ['success' => false,'msg' => 'Unauthorised access'];
        // }
        if (request()->ajax()) {
            try {
                $roles = Role::findOrFail($id);
                $roles->delete();
                $output = ['success' => true,'msg' =>'successfully deleted'];
            } catch (\Exception $e) {
                $output = ['success' => false,'msg' => $e->getMessage()];
            }
            return $output;
        }
    }
}
