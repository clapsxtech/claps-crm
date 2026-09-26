 <div class="modal-dialog" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('subjects.update',$subjects->id)}}" accept-charset="UTF-8" id="edit_form">

            <input name="_method" type="hidden" value="PUT">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">Edit Subjects</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="name" class="control-label">{{__('common.name')}}:*</label>
                    <input class="form-control" required="" value="{{$subjects->name}}" placeholder="{{__('common.name')}}" autocomplete="off" name="name" type="text">
                </div>
                <div class="form-group">
                    <label for="name" class="control-label">{{__('common.description')}}:*</label>
                    <textarea class="form-control" placeholder="{{__('common.description')}}" autocomplete="off" name="description" type="text">{{$subjects->descriptions}}</textarea>
                </div>
                {{-- <div class="form-group" >
                    <label class="control-label">{{__('brand.status')}}:</label><br>
                      <select class="form-control select_group" name="status" required id="status" style="width:100%">
                              <option value="">SELECT</option>
                              <option value="1"{{($brand->status == '1') ? 'Selected' : ''}}>Active</option>
                              <option value="0"{{($brand->status == '0') ? 'Selected' : ''}}>In Active</option>
                      </select>    
                  </div>
            </div> --}}
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{__('common.update')}}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{__('common.close')}}</button>
            </div>
        </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->