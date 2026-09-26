<script>
$(document).on('click', 'button.btn-modal', function() {
    $('div.modal_class').load($(this).data('href'), function() {
        $(this).modal('show');
        $('form#removeForm').submit(function(e) {
            $(this).find('button[type="submit"]').attr('disabled', true);
            e.preventDefault();
            $.ajax({
                method: "delete",
                url: $(this).attr("action"),
                dataType: "json",
                data: {"_token": "{{ csrf_token() }}"},
                success:function(result){
                    if(result.status == true){
                        $('.modal_class').modal('hide');
                        toastr.success(result.msg);
                        $('.data-table').DataTable().ajax.reload();
                    }else{
                        $('.modal_class').modal('hide');
                        toastr.error(result.msg);
                        $('.data-table').DataTable().ajax.reload();
                    }
                }
            });
        });
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
