<div class="modal-dialog" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('departments.update',$departments->id)}}" accept-charset="UTF-8" id="edit_form">

            <input name="_method" type="hidden" value="PUT">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">{{__('departments.edit_departments')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="name" class="control-label">{{__('common.name')}}:*</label>
                    <input class="form-control" required="" placeholder="{{__('common.name')}}" autocomplete="off" name="name" value="{{$departments->name}}" type="text" minlength="3" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="name" class="control-label">{{__('departments.code')}}:*</label>
                    <input class="form-control" required="" placeholder="{{__('departments.code')}}" autocomplete="off" value="{{$departments->code}}"  name="code" type="text" >
                </div>
              
                <div class="form-group">
                    <label class="control-label">{{__('common.location')}}:</label><br>
                      <select class="form-control select_group" name="location_id"  id="location_id" style="width:100%" required="">
                        <option value="">SELECT</option>
                        <option value="1" {{($departments->location_id == 1)? 'selected': ''}}>LOCATION A</option>
                        <option value="2" {{($departments->location_id == 2)? 'selected': ''}}>LOCATION B</option>
                        <option value="3" {{($departments->location_id == 3)? 'selected': ''}}>LOCATION C</option>
                        <option value="4" {{($departments->location_id == 4)? 'selected': ''}}>LOCATION D</option>
                      </select>    
                  </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{__('common.update')}}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{__('common.close')}}</button>
            </div>
        </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->