<div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('users.store')}}" accept-charset="UTF-8" id="createForm">
            @csrf
            <div class="modal-header">
                            <h4 class="modal-title">{{__('users.add_users')}}</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                        </div>
                            <div class="card-body">
                                <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4"> 
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="first_name">{{__('common.name')}} :*</label>
                                            <div class="form-group">
                                                <input class="form-control" required="" placeholder="{{__('common.name')}}" value="{{old('name')}}" name="name" type="text" id="name" >
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="email">{{__('common.email')}} :*</label>
                                                <input class="form-control"  placeholder="{{__('common.email')}}" name="email" value="{{old('email')}}" type="text" id="email">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="email">{{__('common.phone')}} :*</label>
                                                <input class="form-control"  placeholder="{{__('common.phone')}}" name="phone" value="{{old('phone')}}" type="text" id="phone" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                                            </div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="gender">{{__('common.gender')}} :*</label>
                                            <select class="form-control select2" id="gender"  name="gender" required="" style="width:100%">
                                                <option value="">Please Select</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="email">{{__('users.language')}} :*</label>
                                                <input class="form-control" required="" placeholder="Language" name="language" value="{{old('language')}}" type="text" id="language">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="email">{{__('users.position')}} :</label>
                                                <input class="form-control"  placeholder="{{__('users.position')}}" name="position" value="{{old('position')}}" type="text" id="position">
                                            </div>
                                        </div>
                                        {{-- <div class="col-md-4">
                                            <label for="role">{{__('users.role')}} :</label>
                                            <div class="form-group">
                                                <select class="form-control select2 " id="role" name="role" style="width:100%">
                                                    <option value="1" @if (old('role')==1) selected @endif>Admin</option>
                                                    <option value="2" @if (old('role')==2) selected @endif>Sales</option>
                                                    <option value="3" @if (old('role')==3) selected @endif>Manager</option>
                                                </select>
                                            </div>
                                        </div> --}}
                                        <div class="form-group col-md-4">
                                            <label for="user_dob">{{__('users.date_of_birth')}} :</label>
                                            <input class="form-control" placeholder="{{__('users.date_of_birth')}}" value="{{old('dob')}}"  id="user_dob" name="dob" type="date">
                                        </div>
                                        
                                        <div class="form-group col-md-4">
                                            <label for="user_dob">{{__('users.service_expiry')}} :</label>
                                            <input class="form-control" placeholder="{{__('users.service_expiry')}}" value="{{old('service_expiry')}}"  id="service_expiry" name="service_expiry" type="date">
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="name">{{__('common.photo')}} :</label>
                                                <input class="form-control" name="profile_photo" type="file" id="name" accept="image/*" >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="card-body">
                                <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                                {{-- <div class="header">
                                    <div class="col-md-12"><br>
                                        <h3>{{__('users.password')}}</h3>
                                    </div>
                                </div> --}}
                                <div class="card-body">
                                    <div class="row col-md-12">
                                        <div class="col-md-6 form-group">
                                            <label for="password">{{__('users.password')}} :*</label>
                                            <input class="form-control valid" required="" placeholder="{{__('users.password')}}"  name="password" type="password" id="password" >
                                        </div>
                                        <div class=" col-md-6 form-group">
                                            <label for="confirm_password">{{__('users.confirm_password')}}:*</label>
                                            <input class="form-control" required="" placeholder="{{__('users.confirm_password')}}"  name="confirm_password" type="password"  id="confirm_password" >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="card-body">
                            <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                                <div class="header"><br>
                                    <div class="col-md-12">
                                        <h3>{{__('users.more_informations')}}</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class=" col-md-12">
                                            <div class="form-group">
                                                <label class=""><input id="is_commission_agent" name="is_commission_agent" type="checkbox" value="1"> {{__('users.is_commission_agent')}}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="role">{{__('users.commission_type')}} :</label>
                                            <div class="form-group">
                                                <select class="form-control select2 " id="role" name="commission_type" style="width:100%">
                                                    <option value="sale_amount" >Sale Amount</option>
                                                    <option value="profit_amount" >Profit Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="blood_group">{{__('users.blood_group')}} :</label>
                                            <select class="form-control select2 " required id="blood_group" name="blood_group" style="width:100%">
                                                <option value="">Please Select</option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="alt_number">{{__('users.alternate_number')}} :</label>
                                            <input class="form-control" placeholder="{{__('users.alternate_number')}}" value="{{old('alternate_number')}}" name="alternate_number" type="text" id="alternate_number" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="contact_number">{{__('users.emergency_contact_number')}} :</label>
                                            <input class="form-control " placeholder="{{__('users.emergency_contact_number')}}" value="{{old('emergency_contact_number')}}"  name="emergency_contact_number" type="text" id="emergency_contact_number" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                                        </div>
                                       
                                        <div class="form-group col-md-4">
                                            <label for="custom_fields">{{__('users.custom_fields')}} :</label>
                                            <input class="form-control" placeholder="{{__('users.custom_fields')}}" value="{{old('custom_field')}}" name="custom_fields" type="text" id="custom_fields">
                                        </div>
                                       
                                        <div class="clearfix"></div>
                                        <div class="form-group col-md-4">
                                            <label for="permanent_address">{{__('users.permanent_address')}} :</label>
                                            <textarea class="form-control valid" placeholder="{{__('users.permanent_address')}}" value="{{old('permanent_address')}}" rows="3" name="permanent_address" cols="50" id="permanent_address" aria-invalid="false"></textarea>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="current_address">{{__('users.current_address')}}:</label>
                                            <textarea class="form-control valid" placeholder="{{__('users.current_address')}}" value="{{old('current_address')}}" rows="3" name="current_address" cols="50" id="current_address" aria-invalid="false"></textarea>
                                        </div>
                                        <div class="col-md-12">
                                            <hr>
                                            <h4>{{__('users.bank_details')}}:</h4>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="account_holder_name">{{__('users.account_holder_name')}} :</label>
                                            <input class="form-control" id="account_holder_name" placeholder="{{__('users.account_holder_name')}}" value="{{old('bank_details[account_holder_name]')}}" name="bank_details[account_holder_name]" type="text">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="account_number">{{__('users.account_number')}} :</label>
                                            <input class="form-control" id="account_number" placeholder="{{__('users.account_number')}}" value="{{old('bank_details[account_number]')}}" name="bank_details[account_number]" type="text">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="bank_name">{{__('users.bank_name')}} :</label>
                                            <input class="form-control" id="bank_name" placeholder="{{__('users.bank_name')}}" value="{{old('bank_details[bank_name]')}}" name="bank_details[bank_name]" type="text">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="bank_code">{{__('users.bank_code')}} :</label>
                                            <input class="form-control" id="bank_code" placeholder="{{__('users.bank_code')}}" value="{{old('bank_details[bank_code]')}}" name="bank_details[bank_code]" type="text">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="branch">{{__('users.branch')}} :</label>
                                            <input class="form-control" id="branch" placeholder="{{__('users.branch')}}" value="{{old('bank_details[branch]')}}" name="bank_details[branch]" type="text">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="tax_payer_id">{{__('users.tax_payer_id')}} :</label>
                                            <input class="form-control" id="tax_payer_id" placeholder="{{__('users.tax_payer_id')}}" value="{{old('bank_details[tax_payer_id]')}}" name="bank_details[tax_payer_id]" type="text">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary pull-right save_button">SAVE</button>
                            </div>
                        </div>
        </form>
  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
