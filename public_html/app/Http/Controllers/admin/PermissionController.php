<?php

namespace App\Http\Controllers\admin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Permission::select('id','name');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $button = '<button type="button" data-href="' . route('permissions.edit', $row->id) . '" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                    $button .= '<button data-href="' . route('delete', ['permissions', $row->id]) . '" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                    return $button;
                })
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $instance->where(function ($w) use ($request) {
                            $search = $request->get('search');
                            $w->Where('name', 'LIKE', "%$search%");
                        });
                    }
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('admin.permission.index');
    }
    public function create()
    {
        return view('admin.permission.create');
    }
    public function store(Request $request)
    {
        $rules = [
            'name'     => 'required|min:2|max:30'
        ];
        $messages = [
            'name.required'    => 'Name Required',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
        }
        try {
            $input = $request->only(['name']);
            $input['business_id'] = Auth::user()->business_id;
            $input['guard_name'] = "web";
            Permission::create($input);
            return ['success' => true, 'msg' => "succussfully added"];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
    public function edit($id)
    {
        if (request()->ajax()) {
            $permissions = Permission::find($id);
            return view('admin.permission.edit')->with(compact('permissions'));
        }
    }
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            $rules = [
                'name'     => 'required|min:2|max:30'
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
            }
            try {
                $permissions = Permission::findOrFail($id);
                $permissions->name = $request->name;
                $permissions->save();
                return ['success' => true, 'msg' => 'successfully updated'];
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => $e->getMessage()];
            }
        }
    }
    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $permissions = Permission::findOrFail($id);
                $permissions->delete();
                return response()->json(['success' => true, 'msg' => 'successfully deleted']);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'msg' => $e->getMessage()]);
            }
        }
    }
}
