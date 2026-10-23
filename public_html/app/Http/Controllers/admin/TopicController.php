<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class TopicController extends Controller
{
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Topic::select('id','name'); 
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $button = '<button type="button" data-href="'.route('topics.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                        return $button;
                    })
                   
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = \App\Support\TableSearch::term($request);
                                $w->Where('name', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
        }
        return view('admin.topics.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.topics.create');
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

        ];
        $messages = [
            'name.required' => 'Name Required',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
        }
        try {
            $topics = new Topic();
            $topics->name              = $request->name;
            $topics->save();
            return ['success' => true,'msg' =>'successfully created'];
    }
        catch (\Exception $e) {
            report($e);
            return ['success' => false,'msg' => 'Unable to create the topic.'];
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
            $topics = Topic::find($id);
            return view('admin.topics.edit')->with(compact('topics'));
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
                'name'           => 'required'
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
            }
            try {
                $topics = Topic::findOrFail($id);
                $topics->name         = $request->name;
                $topics->save();
                return ['success' => true,'msg' =>'successfully updated'];
            } catch (\Exception $e) {
                report($e);
                return ['success' => false, 'msg' => 'Unable to update the topic.'];
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
                $topics = Topic::findOrFail($id);
                $topics->delete();
                return response()->json(['success' => true,'msg' =>'successfully deleted']);
            } catch (\Exception $e) {
                report($e);
                return response()->json(['success' => false,'msg' => 'Unable to delete the topic.'], 500);
            }
        }
    }
}
