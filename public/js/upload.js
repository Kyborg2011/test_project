/**
 * Asynchronous (AJAX) upload of a single file with a progress bar.
 * The server validates everything again; the checks here only save the user a pointless upload.
 */
(function ($) {
    'use strict';

    const $form = $('#upload-form');

    if (!$form.length) {
        return;
    }

    const maxSize = Number($form.data('max-size'));
    const maxSizeHuman = String($form.data('max-size-human'));
    const extensions = String($form.data('extensions')).split(',');

    const $input = $form.find('input[type="file"]');
    const $button = $('#upload-button');
    const $spinner = $button.find('.spinner-border');
    const $progress = $('#upload-progress');
    const $bar = $progress.find('.progress-bar');
    const $alert = $('#upload-alert');
    const $uploadedCard = $('#uploaded-card');
    const $uploadedRows = $('#uploaded-rows');

    function showAlert(type, message) {
        $alert.removeClass('d-none alert-success alert-danger').addClass('alert-' + type).text(message);
    }

    function hideAlert() {
        $alert.addClass('d-none').text('');
    }

    function setProgress(percent) {
        $progress.attr('aria-valuenow', percent);
        $bar.css('width', percent + '%').text(percent + '%');
    }

    function setBusy(busy) {
        $button.prop('disabled', busy);
        $input.prop('disabled', busy);
        $spinner.toggleClass('d-none', !busy);
        $progress.toggleClass('d-none', !busy);
    }

    function validate(file) {
        if (!file) {
            return 'Choose a file to upload.';
        }

        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

        if (!extensions.includes(extension)) {
            return 'Only ' + extensions.map((e) => e.toUpperCase()).join(', ') + ' files are allowed.';
        }

        if (file.size > maxSize) {
            return 'The file must not be larger than ' + maxSizeHuman + '.';
        }

        return null;
    }

    function errorMessage(xhr) {
        const json = xhr.responseJSON || {};

        switch (xhr.status) {
            case 0:
                return 'Network error. Check the connection and try again.';
            case 413:
                // Rejected by nginx/PHP before reaching the application, the body is not JSON.
                return 'The file must not be larger than ' + maxSizeHuman + '.';
            case 419:
                return 'The page has expired. Reload it and try again.';
            case 422:
                if (json.errors) {
                    return Object.values(json.errors).flat()[0];
                }
                break;
        }

        return json.message || 'Upload failed (HTTP ' + xhr.status + '). Try again later.';
    }

    function addUploadedRow(file) {
        const expiresAt = new Date(file.expires_at).toLocaleString();

        $('<tr>')
            .append($('<td class="text-break">').text(file.name))
            .append($('<td class="text-nowrap">').text(file.size_human))
            .append($('<td class="text-nowrap">').text(expiresAt))
            .prependTo($uploadedRows);

        $uploadedCard.removeClass('d-none');
    }

    $input.on('change', hideAlert);

    $form.on('submit', function (event) {
        event.preventDefault();

        const file = $input[0].files[0];
        const error = validate(file);

        if (error) {
            showAlert('danger', error);

            return;
        }

        const data = new FormData();
        data.append('file', file);

        hideAlert();
        setProgress(0);
        setBusy(true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                const xhr = $.ajaxSettings.xhr();

                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        setProgress(Math.round((e.loaded / e.total) * 100));
                    }
                });

                return xhr;
            },
        })
            .done(function (response) {
                showAlert('success', 'File "' + response.data.name + '" uploaded.');
                addUploadedRow(response.data);
                $form[0].reset();
            })
            .fail(function (xhr) {
                showAlert('danger', errorMessage(xhr));
            })
            .always(function () {
                setBusy(false);
            });
    });
})(jQuery);
