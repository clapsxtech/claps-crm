<script>
$(document).on('click', 'button.btn-modal', function() {
    $('div.modal_class').load($(this).data('href'), function(response, status) {
        if (status === 'error') {
            toastr.error('Unable to open the confirmation. Please try again.');
            return;
        }
        $(this).modal('show');
    });
});

// One delegated handler also covers modals loaded or reopened after a failure.
$(document).on('submit', 'form#removeForm', function(e) {
    e.preventDefault();
    var form = $(this);
    if (form.data('submitting')) {
        return;
    }

    var button = form.find('button[type="submit"]');
    form.data('submitting', true);
    button.prop('disabled', true);

    $.ajax({
        method: 'delete',
        url: form.attr('action'),
        dataType: 'json',
        data: {"_token": "{{ csrf_token() }}"},
        success: function(result) {
            // The current controllers return success; status is legacy-only.
            var succeeded = result && (result.success === true ||
                (typeof result.success === 'undefined' && result.status === true));
            if (succeeded) {
                $('.modal_class').modal('hide');
                toastr.success(result.msg || 'Successfully deleted.');
                $('.data-table').each(function() {
                    if ($.fn.dataTable && $.fn.dataTable.isDataTable(this)) {
                        $(this).DataTable().ajax.reload(null, false);
                    }
                });
            } else {
                toastr.error((result && result.msg) || 'Unable to delete the record. Please try again.');
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.msg;
            toastr.error(message || 'Unable to delete the record. Please check your connection and try again.');
        },
        complete: function() {
            form.data('submitting', false);
            button.prop('disabled', false);
        }
    });
});
</script>
<script src="{{ asset('browser-vendor/moment.min.js') }}"></script>
<script src="{{ asset('browser-vendor/daterangepicker.js') }}"></script>
