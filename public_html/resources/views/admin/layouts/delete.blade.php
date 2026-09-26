<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">REMOVE</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <form role="form" action="{{route($route.'.destroy',$id)}}" method="post" id="removeForm">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <h3 class="text-center" style="color: red"><i class="fas fa-trash-alt"></i></h3>
                <h4 class="text-center">Are you Sure?</h4>
                <p class="text-center">You won't be able to revert this!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" data-dismiss="modal"><i class="fa fa-times"></i> No, Cancel!</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Yes, Delete it!</button>
            </div>
        </form>
  </div>