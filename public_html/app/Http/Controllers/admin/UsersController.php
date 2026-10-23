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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'profile_photo'                     => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ];
        $messages = [
            'name.required'       => 'Name Required',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['error' => false, 'msg' => $validator->errors()->first()]);
        }
        $bank_details = array(
            "account_holder_name" => $request->input('bank_details.account_holder_name'),
            "account_number" => $request->input('bank_details.account_number'),
            "bank_name" => $request->input('bank_details.bank_name'),
            "bank_code" => $request->input('bank_details.bank_code'),
            "branch" => $request->input('bank_details.branch'),
            "tax_payer_id" => $request->input('bank_details.tax_payer_id')
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
                    File::makeDirectory($path, 0755, true, true);
                }
                $imageName = Str::uuid().'.'.$request->file('profile_photo')->extension();
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
            Log::error('Unable to create user', ['exception' => $e]);
            return ['success' => false, 'msg' => 'Unable to create the user.'];
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
            'email'                             => ['required', 'email', 'min:3', 'max:50', Rule::unique('users')->ignore($id)],
            'phone'                             => ['required', 'min:10', 'max:10', Rule::unique('users')->ignore($id)],
            'password'                          => 'nullable|min:6|required_with:confirm_password|same:confirm_password',
            'profile_photo'                     => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['error' => false, 'msg' => $validator->errors()->first()]);
            }
            $bank_details = array(
                "account_holder_name" => $request->input('bank_details.account_holder_name'),
                "account_number" => $request->input('bank_details.account_number'),
                "bank_name" => $request->input('bank_details.bank_name'),
                "bank_code" => $request->input('bank_details.bank_code'),
                "branch" => $request->input('bank_details.branch'),
                "tax_payer_id" => $request->input('bank_details.tax_payer_id')
            );
            $address = array(
                "permanent_address" => $request->permanent_address,
                "current_address" => $request->current_address,
            );
            try {
            $user = User::findOrFail($id);
            $newPassword = $request->password;
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
            if (!empty($newPassword)) {
                $user->password = Hash::make($request->password);
            }

            if ($request->hasFile('profile_photo')) {
                    if(file_exists(public_path().$user->profile_photo) && !empty($user->profile_photo)){
                        unlink(public_path().$user->profile_photo);
                    }
                    $file = Str::uuid().'.'.$request->file('profile_photo')->extension();
                    $request->profile_photo->move(public_path('/uploads/profile_photo'),$file);
                    $user->profile_photo='/uploads/profile_photo/'.$file;
                }
                $user->save();
                return response()->json(['success' => true,'msg' =>'successfully updated']);
            } catch (\Exception $e) {
                Log::error('Unable to update user', ['user_id' => $id, 'exception' => $e]);
                return ['success' => false, 'msg' => 'Unable to update the user.'];
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
                Log::error('Unable to delete user', ['user_id' => $id, 'exception' => $e]);
                return response()->json(['success' => false, 'msg' => 'Unable to delete the user.'], 500);
            }
        }
    }
}
