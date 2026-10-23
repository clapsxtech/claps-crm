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

    $(document).on('submit', 'form:not([data-staff-profile])', function () {
        setButtonLoading($(this).find('button[type="submit"]:not([data-no-loading])').first(), true);
    });

    $(document).ajaxComplete(function () {
        // A different AJAX request must not unlock a staff form while its save is pending.
        $('.btn.is-loading').filter(function () {
            return !$(this).closest('form[data-staff-profile]').length;
        }).each(function () { setButtonLoading(this, false); });
    });

    const responseMessage = (response, fallback) => {
        if (!response || typeof response !== 'object') return fallback;
        if (typeof response.msg === 'string' && response.msg) return response.msg;
        if (response.errors && typeof response.errors === 'object') {
            for (const errors of Object.values(response.errors)) {
                const message = Array.isArray(errors) ? errors.find(value => typeof value === 'string') : errors;
                if (typeof message === 'string' && message) return message;
            }
        }
        return typeof response.message === 'string' && response.message ? response.message : fallback;
    };

    const notify = (kind, message) => {
        if (window.toastr) window.toastr[kind](message, null, { escapeHtml: true });
    };

    // This handler also covers the navbar profile modal on pages without the staff table.
    $(document).off('submit.staffProfiles', 'form[data-staff-profile]')
        .on('submit.staffProfiles', 'form[data-staff-profile]', function (event) {
            event.preventDefault();
            const form = this;
            const $form = $(form);
            if ($form.data('submitting')) return;
            $form.data('submitting', true);
            const $buttons = $form.find('button[type="submit"]');
            const $modal = $form.closest('.modal');
            $buttons.each(function () { setButtonLoading(this, true); });

            $.ajax({
                method: 'POST',
                url: $form.attr('action'),
                dataType: 'json',
                data: new FormData(form),
                contentType: false,
                processData: false,
                success: function (result) {
                    if (result && result.success === true) {
                        // A completed request from an older, closed form must not close a newly opened form.
                        if ($modal.length && $.contains($modal[0], form)) $modal.modal('hide');
                        notify('success', responseMessage(result, 'Staff profile saved.'));
                        $(document).trigger('staff:updated', [result]);
                    } else {
                        notify('error', responseMessage(result, 'Unable to save the staff profile.'));
                    }
                },
                error: function (xhr) {
                    notify('error', responseMessage(xhr.responseJSON, 'Unable to save the staff profile. Please try again.'));
                },
                complete: function () {
                    $form.data('submitting', false);
                    $buttons.each(function () { setButtonLoading(this, false); });
                }
            });
        });

    $(document).on('shown.bs.modal', '.modal', function () {
        $(this).find('input:not([type="hidden"]), select, textarea, button').filter(':visible').first().trigger('focus');
    });

    $(document).on('click', '.global-modal-trigger', function (event) {
        event.preventDefault();
        let $modal = $('.global-modal');
        if (!$modal.length) {
            $modal = $('<div class="modal fade global-modal" tabindex="-1" role="dialog" aria-modal="true"></div>').appendTo('body');
        }
        const generation = ($modal.data('load-generation') || 0) + 1;
        $modal.data('load-generation', generation);
        const previous = $modal.data('load-request');
        if (previous) previous.abort();
        $modal.html('<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-5"><span class="spinner-border text-primary" role="status" aria-label="Loading"></span></div></div></div>');
        $modal.modal('show');
        const request = $.ajax({
            method: 'GET',
            url: $(this).data('href'),
            dataType: 'html',
            success: function (html) {
                if ($modal.data('load-generation') !== generation) return;
                $modal.html(html);
                $modal.find('input:not([type="hidden"]), select, textarea').filter(':visible').first().trigger('focus');
            },
            error: function (xhr, status) {
                if (status === 'abort' || $modal.data('load-generation') !== generation) return;
                $modal.modal('hide');
                notify('error', responseMessage(xhr.responseJSON, 'Unable to open the profile. Please try again.'));
            },
            complete: function () {
                if ($modal.data('load-generation') === generation) $modal.removeData('load-request');
            }
        });
        $modal.data('load-request', request);
    });

    $(document).on('hidden.bs.modal', '.modal', function () {
        const $modal = $(this);
        $modal.data('load-generation', ($modal.data('load-generation') || 0) + 1);
        const request = $modal.data('load-request');
        if (request) request.abort();
        $modal.removeData('load-request').removeData('bs.modal').find('.modal-content').remove();
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

    $(document).ajaxError(function (_event, xhr, settings) {
        // Requests with a local error handler already own their feedback.
        if ((settings && settings.error) || xhr.status === 422 || xhr.status === 0) return;
        notify('error', responseMessage(xhr.responseJSON, 'Something went wrong. Please try again.'));
    });
});
