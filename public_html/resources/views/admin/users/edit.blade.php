<div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <form method="post" action="{{ route('users.update', $users->id) }}" accept-charset="UTF-8" id="edit_form">

            <input name="_method" type="hidden" value="PUT">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">{{ __('users.edit_users') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">×</span></button>
            </div>
            <div class="card-body">
                <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="first_name">{{ __('common.name') }} :*</label>
                            <div class="form-group">
                                <input class="form-control" required="" placeholder="{{ __('common.name') }}"
                                    value="{{ old('name', $users->name) }}" name="name" type="text" id="name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">{{ __('common.email') }} :*</label>
                                <input class="form-control" placeholder="{{ __('common.email') }}" name="email"
                                    value="{{ old('email', $users->email) }}" type="text" id="email">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">{{ __('common.phone') }} :*</label>
                                <input class="form-control" placeholder="{{ __('common.phone') }}" name="phone"
                                    value="{{ old('phone', $users->phone) }}" type="text" id="phone" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="gender">{{ __('common.gender') }} :</label>
                            <select class="form-control select2" id="gender" name="gender" style="width:100%">
                                <option value="">Please Select</option>
                                <option value="Male" @if (old('gender', $users->gender) == 'Male') selected @endif>Male</option>
                                <option value="Female" @if (old('gender', $users->gender) == 'Female') selected @endif>Female</option>
                                <option value="Others" @if (old('gender', $users->gender) == 'Others') selected @endif>Others</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">{{ __('users.language') }} :</label>
                                <input class="form-control" placeholder="Language" name="language"
                                    value="{{ old('language', $users->language) }}" type="text" id="language">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="email">{{ __('users.position') }} :</label>
                                <input class="form-control" placeholder="{{ __('users.position') }}" name="position"
                                    value="{{ old('position', $users->position) }}" type="text" id="position">
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="user_dob">{{ __('users.date_of_birth') }} :</label>
                            <input class="form-control" placeholder="{{ __('users.date_of_birth') }}"
                                value="{{ old('dob', $users->dob) }}" id="user_dob" name="dob" type="date">
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="name">{{ __('common.photo') }}:</label>
                                <input class="form-control" name="profile_photo" type="file" id="name"
                                    accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                @if ($users->profile_photo)
                                    <img style="height: 75px" class="img-circle"
                                        src="{{ asset($users->profile_photo) }}" alt="{{ $users->name }}">
                                @else
                                    <img style="height: 75px" class="img-circle" src="{{ asset('img/user2.png') }}"
                                        alt="{{ $users->name }}">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="card-body">

                <div class="row col-md-12">
                    <div class="col-md-6 form-group">
                        <label for="password">{{ __('users.password') }} :*</label>
                        <input class="form-control valid"  placeholder="{{ __('users.password') }}"
                            name="password" type="password" id="password">
                    </div>
                    <div class=" col-md-6 form-group">
                        <label for="confirm_password">{{ __('users.confirm_password') }}:*</label>
                        <input class="form-control"  placeholder="{{ __('users.confirm_password') }}"
                            name="confirm_password" type="password" id="confirm_password">
                    </div>
                </div>
            </div>
            <hr>
            <div class="card-body">
                <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                    <div class="header"><br>
                        <div class="col-md-12">
                            <h3>{{ __('users.more_informations') }}</h3>
                        </div>
                    </div>
                    <div class="row">
                        <div class=" col-md-12">
                            <div class="form-group">
                                <label class=""><input id="is_commission_agent" name="is_commission_agent"
                                        type="checkbox" value="1" @php
                                            if ($users->is_commission_agent) {
                                                echo 'checked';
                                            }
                                        @endphp>
                                    {{ __('users.is_commission_agent') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="role">{{ __('users.commission_type') }} :</label>
                            <div class="form-group">
                                <select class="form-control select2 " id="commission_type" name="commission_type"
                                    style="width:100%">
                                    <option value="sale_amount" @if (old('commission_type', $users->commission_type) == 'sale_amount') selected @endif>Sale
                                        Amount</option>
                                    <option value="profit_amount" @if (old('commission_type', $users->commission_type) == 'profit_amount') selected @endif>
                                        Profit Amount</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="service_expiry">{{ __('users.service_expiry') }} :</label>
                            <input class="form-control" placeholder="{{ __('users.service_expiry') }}"
                                value="{{ old('service_expiry', $users->service_expiry) }}" id="service_expiry"
                                name="service_expiry" type="date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="blood_group">{{ __('users.blood_group') }} :</label>
                            <select class="form-control select2 " id="blood_group" name="blood_group"
                                style="width:100%">
                                <option value="">Please Select</option>
                                <option value="A+" @if (old('blood_group', $users->blood_group) == 'A+') selected @endif> A+</option>
                                <option value="A-" @if (old('blood_group', $users->blood_group) == 'A-') selected @endif>A-</option>
                                <option value="B+" @if (old('blood_group', $users->blood_group) == 'B+') selected @endif>B+</option>
                                <option value="B-" @if (old('blood_group', $users->blood_group) == 'B-') selected @endif>B-</option>
                                <option value="O+" @if (old('blood_group', $users->blood_group) == 'O+') selected @endif>O+</option>
                                <option value="O-" @if (old('blood_group', $users->blood_group) == 'O-') selected @endif>O-</option>
                                <option value="AB+" @if (old('blood_group', $users->blood_group) == 'AB+') selected @endif>AB+</option>
                                <option value="AB-" @if (old('blood_group', $users->blood_group) == 'AB-') selected @endif>AB-</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="alt_number">{{ __('users.alternate_number') }} :</label>
                            <input class="form-control" placeholder="{{ __('users.alternate_number') }}"
                                value="{{ old('alternate_number', $users->alternate_number) }}" name="alternate_number"
                                type="text" id="alternate_number" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="contact_number">{{ __('users.emergency_contact_number') }} :</label>
                            <input class="form-control " placeholder="{{ __('users.emergency_contact_number') }}"
                                value="{{ old('emergency_contact_number', $users->emergency_contact_number) }}"
                                name="emergency_contact_number" type="text" id="emergency_contact_number" onkeyup="if (/\D/g.test(this.value))this.value = this.value.replace(/\D/g, '')">
                        </div>

                        <div class="form-group col-md-4">
                            <label for="custom_fields">{{ __('users.custom_fields') }} :</label>
                            <input class="form-control" placeholder="{{ __('users.custom_fields') }}"
                                value="{{ old('custom_fields', $users->custom_fields) }}" name="custom_fields"
                                type="text" id="custom_fields">
                        </div>
                        <?php $address = json_decode($users['address'], true, 4);
                        // print_r($address['permanent_address']);die;
                        ?>

                        <div class="clearfix"></div>
                        <div class="form-group col-md-4">
                            <label for="permanent_address">{{ __('users.permanent_address') }} :</label>
                            <textarea class="form-control valid" placeholder="{{ __('users.permanent_address') }}" rows="3"
                                name="permanent_address" cols="50" id="permanent_address"
                                aria-invalid="false">{{ old('permanent_address', $address['permanent_address']) }}</textarea>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="current_address">{{ __('users.current_address') }}:</label>
                            <textarea class="form-control valid" placeholder="{{ __('users.current_address') }}" rows="3" name="current_address"
                                cols="50" id="current_address"
                                aria-invalid="false">{{ old('current_address', $address['current_address']) }}</textarea>
                        </div>
                        <?php $bank_details = json_decode($users['bank_details'], true, 4); ?>
                        <div class="col-md-12">
                            <hr>
                            <h4>{{ __('users.bank_details') }}:</h4>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="account_holder_name">{{ __('users.account_holder_name') }} :</label>
                            <input class="form-control" id="account_holder_name"
                                placeholder="{{ __('users.account_holder_name') }}"
                                value="{{ old('bank_details[account_holder_name]', $bank_details['account_holder_name']) }}"
                                name="bank_details[account_holder_name]" type="text">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="account_number">{{ __('users.account_number') }} :</label>
                            <input class="form-control" id="account_number"
                                placeholder="{{ __('users.account_number') }}"
                                value="{{ old('bank_details[account_number]', $bank_details['account_number']) }}"
                                name="bank_details[account_number]" name="bank_details[account_number]" type="text">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="bank_name">{{ __('users.bank_name') }} :</label>
                            <input class="form-control" id="bank_name" placeholder="{{ __('users.bank_name') }}"
                                value="{{ old('bank_details[bank_name]', $bank_details['bank_name']) }}"
                                name="bank_details[bank_name]" type="text">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="bank_code">{{ __('users.bank_code') }} :</label>
                            <input class="form-control" id="bank_code" placeholder="{{ __('users.bank_code') }}"
                                value="{{ old('bank_details[bank_code]', $bank_details['bank_code']) }}"
                                name="bank_details[bank_code]" type="text">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="branch">{{ __('users.branch') }} :</label>
                            <input class="form-control" id="branch" placeholder="{{ __('users.branch') }}"
                                value="{{ old('bank_details[branch]', $bank_details['branch']) }}"
                                name="bank_details[branch]" type="text">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="tax_payer_id">{{ __('users.tax_payer_id') }} :</label>
                            <input class="form-control" id="tax_payer_id"
                                placeholder="{{ __('users.tax_payer_id') }}"
                                value="{{ old('bank_details[tax_payer_id]', $bank_details['tax_payer_id']) }}"
                                name="bank_details[tax_payer_id]" type="text">
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary pull-right save_button">SAVE</button>
                </div>
            </div>
    </div>
    </form>
</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
