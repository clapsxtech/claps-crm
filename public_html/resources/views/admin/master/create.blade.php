<div class="modal-dialog" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('departments.store')}}" accept-charset="UTF-8" id="createForm">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">{{__('departments.add_departments')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>

            <div class="modal-body">
                
                <div class="form-group">
                    <label for="name" class="control-label">{{__('common.name')}}:*</label>
                    <input class="form-control" required="" placeholder="{{__('common.name')}}" autocomplete="off" name="name" type="text" minlength="3" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="name" class="control-label">{{__('departments.code')}}:*</label>
                    <input class="form-control" required="" placeholder="{{__('departments.code')}}" autocomplete="off" name="code" type="text" >
                </div>
              
                <div class="form-group">
                    <label class="control-label">{{__('common.location')}}:</label><br>
                      <select class="form-control select_group" name="location_id"  id="location_id" style="width:100%" required="">
                        <option value="">SELECT</option>
                        <option value="1">LOCATION A</option>
                        <option value="2">LOCATION B</option>
                        <option value="3">LOCATION C</option>
                        <option value="4">LOCATION D</option>
                      </select>    
                  </div>
            </div>
        
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{__('common.save')}}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{__('common.close')}}</button>
            </div>
        </form>
  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
