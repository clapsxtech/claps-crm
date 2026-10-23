@php
    $editing = isset($users) && $users->exists;
    $address = $editing ? json_decode((string) $users->address, true) : [];
    $address = is_array($address) ? $address : [];
    $bank = $editing ? json_decode((string) $users->bank_details, true) : [];
    $bank = is_array($bank) ? $bank : [];
    $gender = strtoupper((string) old('gender', $editing ? $users->gender : ''));
    $gender = $gender === 'OTHERS' ? 'OTHER' : $gender;
@endphp
<div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <form method="post" action="{{ $editing ? route('users.update', $users->id) : route('users.store') }}"
              enctype="multipart/form-data" accept-charset="UTF-8" data-staff-profile="true" id="{{ $editing ? 'edit_form' : 'createForm' }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif
            <div class="modal-header">
                <h4 class="modal-title">{{ __($editing ? 'users.edit_users' : 'users.add_users') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    @foreach (['name' => ['common.name', 'text', 100], 'email' => ['common.email', 'email', 255], 'phone' => ['common.phone', 'tel', 10]] as $field => $options)
                        <div class="form-group col-md-4">
                            <label for="staff_{{ $field }}">{{ __($options[0]) }} *</label>
                            <input class="form-control" id="staff_{{ $field }}" name="{{ $field }}" type="{{ $options[1] }}"
                                   maxlength="{{ $options[2] }}" value="{{ old($field, $editing ? $users->$field : '') }}" required
                                   @if ($field === 'phone') inputmode="numeric" pattern="[0-9]{10}" @endif>
                        </div>
                    @endforeach
                    <div class="form-group col-md-4">
                        <label for="staff_gender">{{ __('common.gender') }} *</label>
                        <select class="form-control select2" id="staff_gender" name="gender" required style="width:100%">
                            <option value="">Please Select</option>
                            @foreach (['MALE' => 'Male', 'FEMALE' => 'Female', 'OTHER' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @if ($gender === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach (['language' => 'users.language', 'position' => 'users.position'] as $field => $label)
                        <div class="form-group col-md-4">
                            <label for="staff_{{ $field }}">{{ __($label) }}</label>
                            <input class="form-control" id="staff_{{ $field }}" name="{{ $field }}" type="text" maxlength="100"
                                   value="{{ old($field, $editing ? $users->$field : '') }}">
                        </div>
                    @endforeach
                    @foreach (['dob' => 'users.date_of_birth', 'service_expiry' => 'users.service_expiry'] as $field => $label)
                        <div class="form-group col-md-4">
                            <label for="staff_{{ $field }}">{{ __($label) }}</label>
                            <input class="form-control" id="staff_{{ $field }}" name="{{ $field }}" type="date"
                                   value="{{ old($field, $editing ? $users->$field : '') }}" @if ($field === 'dob') max="{{ date('Y-m-d') }}" @endif>
                        </div>
                    @endforeach
                    <div class="form-group col-md-4">
                        <label for="staff_profile_photo">{{ __('common.photo') }}</label>
                        <input class="form-control" id="staff_profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="form-text text-muted">JPEG, PNG, GIF or WebP, up to 2 MB.</small>
                        @if ($editing && $users->profile_photo)
                            <img class="img-circle mt-2" style="height:75px" src="{{ asset($users->profile_photo) }}" alt="{{ $users->name }}">
                        @endif
                    </div>
                </div>
                <hr>
                @if ($editing)
                    <p class="text-muted">Leave both password fields blank to keep the current password.</p>
                @endif
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="staff_password">{{ __('users.password') }}{{ $editing ? '' : ' *' }}</label>
                        <input class="form-control" id="staff_password" name="password" type="password" minlength="8" maxlength="72"
                               autocomplete="new-password" @if (!$editing) required @endif>
                        <small class="form-text text-muted">At least 8 characters, no more than 72 bytes.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="staff_confirm_password">{{ __('users.confirm_password') }}{{ $editing ? '' : ' *' }}</label>
                        <input class="form-control" id="staff_confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72"
                               autocomplete="new-password" @if (!$editing) required @endif>
                    </div>
                </div>
                <hr>
                <h5>{{ __('users.more_informations') }}</h5>
                <div class="row">
                    <div class="form-group col-md-12">
                        <input name="is_commission_agent" type="hidden" value="0">
                        <label for="staff_is_commission_agent">
                            <input id="staff_is_commission_agent" name="is_commission_agent" type="checkbox" value="1"
                                   @if (old('is_commission_agent', $editing ? $users->is_commission_agent : false)) checked @endif>
                            {{ __('users.is_commission_agent') }}
                        </label>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="staff_commission_type">{{ __('users.commission_type') }}</label>
                        <select class="form-control select2" id="staff_commission_type" name="commission_type" style="width:100%">
                            <option value="">Please Select</option>
                            @foreach (['sale_amount' => 'Sale Amount', 'profit_amount' => 'Profit Amount'] as $value => $label)
                                <option value="{{ $value }}" @if (old('commission_type', $editing ? $users->commission_type : '') === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="staff_blood_group">{{ __('users.blood_group') }}</label>
                        <select class="form-control select2" id="staff_blood_group" name="blood_group" style="width:100%">
                            <option value="">Please Select</option>
                            @foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $value)
                                <option value="{{ $value }}" @if (old('blood_group', $editing ? $users->blood_group : '') === $value) selected @endif>{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach (['alternate_number', 'emergency_contact_number'] as $field)
                        <div class="form-group col-md-4">
                            <label for="staff_{{ $field }}">{{ __('users.'.$field) }}</label>
                            <input class="form-control" id="staff_{{ $field }}" name="{{ $field }}" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10"
                                   value="{{ old($field, $editing ? $users->$field : '') }}">
                        </div>
                    @endforeach
                    <div class="form-group col-md-4">
                        <label for="staff_custom_fields">{{ __('users.custom_fields') }}</label>
                        <input class="form-control" id="staff_custom_fields" name="custom_fields" type="text" maxlength="1000"
                               value="{{ old('custom_fields', $editing ? $users->custom_fields : '') }}">
                    </div>
                    @foreach (['permanent_address', 'current_address'] as $field)
                        <div class="form-group col-md-6">
                            <label for="staff_{{ $field }}">{{ __('users.'.$field) }}</label>
                            <textarea class="form-control" id="staff_{{ $field }}" name="{{ $field }}" rows="3" maxlength="200">{{ old($field, $address[$field] ?? '') }}</textarea>
                        </div>
                    @endforeach
                </div>
                <hr>
                <h5>{{ __('users.bank_details') }}</h5>
                <div class="row">
                    @foreach (['account_holder_name', 'account_number', 'bank_name', 'bank_code', 'branch', 'tax_payer_id'] as $field)
                        <div class="form-group col-md-4">
                            <label for="staff_bank_{{ $field }}">{{ __('users.'.$field) }}</label>
                            <input class="form-control" id="staff_bank_{{ $field }}" name="bank_details[{{ $field }}]" type="text" maxlength="100"
                                   value="{{ old('bank_details.'.$field, $bank[$field] ?? '') }}">
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary save_button">SAVE</button>
            </div>
        </form>
    </div>
</div>
