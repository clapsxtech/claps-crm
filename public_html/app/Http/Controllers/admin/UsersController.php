<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{ 
    public function index(Request $request)
    { if ($request->ajax()) {
        $data = User::select('id', 'name', 'email', 'phone'); 
        return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $button = '<button type="button" data-href="'.route('users.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                    $button .='<button data-href="'.route('delete',['users',$row->id]).'" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                    return $button;
                })
                
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                         $instance->where(function($w) use($request){
                            $search = $request->get('search');
                            $w->Where('name', 'LIKE', "%$search%");
                        });
                    }
                })
                ->rawColumns(['status','action'])
                ->make(true);
    }
        return view('admin.users.index');   

    }
    public function create()
    {
        return view('admin.users.create');   
    }
   
    public function store(Request $request)
    {
        $rules = [
            'name'                              => 'required|string|min:3|max:30',
            'email'                             => 'required|email|min:3|max:50|unique:users',
            'phone'                             => 'required|min:10|max:10|unique:users',
            'password'                          => 'min:6|required_with:confirm_password|same:confirm_password',
            'confirm_password'                  => 'min:6',
        ];
        $messages = [
            'name.required'       => 'Name Required',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['error' => false, 'msg' => $validator->errors()->first()]);
        }
        $bank_details = array(
            "account_holder_name" => $request->bank_details['account_holder_name'],
            "account_number" => $request->bank_details['account_number'],
            "bank_name" => $request->bank_details['bank_name'],
            "bank_code" => $request->bank_details['bank_code'],
            "branch" => $request->bank_details['branch'],
            "tax_payer_id" => $request->bank_details['tax_payer_id']
        );
        $address = array(
            "permanent_address" => $request->permanent_address,
            "current_address" => $request->current_address,
        );
        try {  
            $profile_photo='';
            if ($request->hasFile('profile_photo')) {
                $path = public_path('/uploads/profile_photo');
                if (!file_exists($path)) {
                    File::makeDirectory($path, $mode = 0777, true, true);
                }
                $imageName =  uniqid().'.'.$request->profile_photo->extension();
                $request->profile_photo->move(public_path('/uploads/profile_photo'),$imageName);
                $profile_photo = '/uploads/profile_photo/'.$imageName;
            }
            $randomId       =   rand(2,50); 
            $user = new User();
            $user->business_id = Auth::user()->business_id;
            $user->name = $request->name;
            $user->employee_code = $randomId;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->dob = $request->dob;
            $user->gender = $request->gender;
            $user->position = $request->position;
            $user->user_type = 'user';
            $user->language = $request->language;
            $user->custom_fields = $request->custom_fields;
            $user->is_commission_agent = $request->is_commission_agent;
            $user->commission_type = $request->commission_type;
            $user->blood_group = $request->blood_group;
            $user->alternate_number = $request->alternate_number;
            $user->emergency_contact_number = $request->emergency_contact_number;
            $user->service_expiry = $request->service_expiry;
            $user->address = json_encode($address);
            $user->bank_details   = json_encode($bank_details);
            $user->password = Hash::make($request->password);
            $user->profile_photo = $profile_photo;
            $user->save();
            return ['success' => true,'msg' => "succussfully added"];
        } catch (\Exception $e) {
            return ['error' => false,'msg' => $e->getMessage()];
        }
    }


    public function edit($id)
    {
        $users = User::findOrFail($id);
        return view('admin.users.edit')->with(compact('users'));
    }
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            $rules = [
            'name'                              => 'required|string|min:3|max:30',
            'email'                             => 'required|email|min:3|max:50',
            'phone'                             => 'required|min:10|max:10',
            'password'                          => 'required_with:confirm_password|same:confirm_password',
            // 'confirm_password'                  => 'min:6',
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['error' => false, 'msg' => $validator->errors()->first()]);
            }
            $bank_details = array(
                "account_holder_name" => $request->bank_details['account_holder_name'],
                "account_number" => $request->bank_details['account_number'],
                "bank_name" => $request->bank_details['bank_name'],
                "bank_code" => $request->bank_details['bank_code'],
                "branch" => $request->bank_details['branch'],
                "tax_payer_id" => $request->bank_details['tax_payer_id']
            );
            $address = array(
                "permanent_address" => $request->permanent_address,
                "current_address" => $request->current_address,
            );
            try {
            $user = User::findOrFail($id);
            $newPassword = $request->password;
            // print_r($newPassword);die;
            $user->business_id = Auth::user()->business_id;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->dob = $request->dob;
            $user->gender = $request->gender;
            $user->position = $request->position;
            $user->user_type = 'user';
            $user->language = $request->language;
            $user->custom_fields = $request->custom_fields;
            $user->is_commission_agent = $request->is_commission_agent;
            $user->commission_type = $request->commission_type;
            $user->blood_group = $request->blood_group;
            $user->alternate_number = $request->alternate_number;
            $user->emergency_contact_number = $request->emergency_contact_number;
            $user->service_expiry = $request->service_expiry;
            $user->address = json_encode($address);
            $user->bank_details   = json_encode($bank_details);
            if(empty($newPassword)){
                $user->update($request->except('password'));
            }else{
                $user->update($request->all());
                $user->password = Hash::make($request->password);
            }

            if ($request->hasFile('profile_photo')) {
                    if(file_exists(public_path().$user->profile_photo) && !empty($user->profile_photo)){
                        unlink(public_path().$user->profile_photo);
                    }
                    $file =  uniqid().'.'.$request->profile_photo->extension();
                    $request->profile_photo->move(public_path('/uploads/profile_photo'),$file);
                    $user->profile_photo='/uploads/profile_photo/'.$file;
                }
                $user->save();
                return response()->json(['success' => true,'msg' =>'successfully updated']);
            } catch (\Exception $e) {
                return ['error' => false, 'msg' => $e->getMessage()];
            }
    }}
    public function Profile()
    {
        $id=Auth::user()->id;
        $users = User::findOrFail($id);
        return view('admin.users.profile')->with(compact('users'));
        
    }
    public function destroy($id)
    {   
        if (request()->ajax()) {
            try {
                $user = User::findOrFail($id);
                $user->delete();
                return response()->json(['success' => true,'msg' =>'successfully deleted']);
            } catch (\Exception $e) {
                return response()->json(['error' => false,'msg' => $e->getMessage()]);
            }
        }
    }
}
