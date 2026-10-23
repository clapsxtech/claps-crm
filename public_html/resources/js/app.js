require('./bootstrap');
require('select2');

$(function () {
    const setButtonLoading = (button, loading) => {
        const $button = $(button);
        if (loading) {
            $button.data('original-label', $button.html()).addClass('is-loading').prop('disabled', true);
        } else {
            $button.removeClass('is-loading').prop('disabled', false);
            if ($button.data('original-label')) $button.html($button.data('original-label'));
        }
    };

    $(document).on('submit', 'form', function () {
        setButtonLoading($(this).find('button[type="submit"]:not([data-no-loading])').first(), true);
    });

    $(document).ajaxComplete(function () {
        $('.btn.is-loading').each(function () { setButtonLoading(this, false); });
    });

    $(document).on('shown.bs.modal', '.modal', function () {
        $(this).find('input:not([type="hidden"]), select, textarea, button').filter(':visible').first().trigger('focus');
    });

    $(document).on('click', '.global-modal-trigger', function () {
        let $modal = $('.global-modal');
        if (!$modal.length) {
            $modal = $('<div class="modal fade global-modal" tabindex="-1" role="dialog" aria-modal="true"></div>').appendTo('body');
        }
        $modal.html('<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status" aria-label="Loading"></span></div></div></div>');
        $modal.modal('show').load($(this).data('href'));
    });

    $(document).on('hidden.bs.modal', '.modal', function () {
        $(this).removeData('bs.modal').find('.modal-content').remove();
    });

    $('table').each(function () {
        if (!$(this).closest('.table-responsive').length) $(this).wrap('<div class="table-responsive"></div>');
    });

    $('input, select, textarea').each(function () {
        const $field = $(this);
        if (!$field.attr('aria-label') && !$field.attr('aria-labelledby')) {
            const label = $field.closest('.form-group').find('label').first().text().replace('*', '').trim();
            if (label) $field.attr('aria-label', label);
        }
    });

    $(document).ajaxError(function (_event, xhr) {
        if (xhr.status === 422 || xhr.status === 0) return;
        const message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Something went wrong. Please try again.';
        if (window.toastr) window.toastr.error(message);
    });
});
