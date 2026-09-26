<div class="modal-dialog" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('permissions.store')}}" accept-charset="UTF-8" id="createForm">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">{{ __('permission.add_permissions') }}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="name" class="control-label">{{ __('common.name') }}:*</label>
                    <input class="form-control" required="" placeholder="{{ __('common.name') }}" autocomplete="off" name="name" type="text" minlength="2" maxlength="30">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{ __('common.save') }}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('common.close') }}</button>
            </div>
        </form>
    </div>
</div>
