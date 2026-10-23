<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class SubjectController extends Controller
{
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Subject::select('id','name','description'); 
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $button = '<button type="button" data-href="'.route('subjects.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                        return $button;
                    })
                    ->addColumn('status', function ($row) {
                        if($row->status==1){
                           return 'ACTIVE';
                        }elseif($row->status==0){
                            return 'IN ACTIVE';
                        }
                    })
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = \App\Support\TableSearch::term($request);
                                $w->Where('name', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->rawColumns(['status','action'])
                    ->make(true);
        }
        return view('admin.subjects.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.subjects.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      
        $rules = [
            'name'          => 'required',
            'descriptions'   => 'max:150',

        ];
        $messages = [
            'name.required' => 'Name Required',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
        }
        try {
            $brand = new Subject();
            $brand->name              = $request->name;
            $brand->description      = $request->description;
            $brand->save();
            return ['success' => true,'msg' =>'successfully created'];
    }
        catch (\Exception $e) {
            report($e);
            return ['success' => false,'msg' => 'Unable to create the subject.'];
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (request()->ajax()) {
            $subjects = Subject::find($id);
            return view('admin.subjects.edit')->with(compact('subjects'));
        }
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            $rules = [
                'name'           => 'required',
                'descriptions'   => 'max:150',
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
            }
            try {
                $brand = Subject::findOrFail($id);
                $brand->name         = $request->name;
                $brand->description  = $request->description;
                $brand->save();
                return ['success' => true,'msg' =>'successfully updated'];
            } catch (\Exception $e) {
                report($e);
                return ['success' => false, 'msg' => 'Unable to update the subject.'];
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {   
        if (request()->ajax()) {
            try {
                $brand = Subject::findOrFail($id);
                $brand->delete();
                return response()->json(['success' => true,'msg' =>'successfully deleted']);
            } catch (\Exception $e) {
                report($e);
                return response()->json(['success' => false,'msg' => 'Unable to delete the subject.'], 500);
            }
        }
    }
}
