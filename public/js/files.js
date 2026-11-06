/**
 * Management page: live "deleted in" countdown and AJAX deletion with a confirmation dialog.
 */
(function ($) {
    'use strict';

    const $table = $('#files-table');

    if (!$table.length) {
        return;
    }

    const $alert = $('#files-alert');
    const modalElement = document.getElementById('delete-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const $confirm = $('#delete-confirm');
    const $confirmSpinner = $confirm.find('.spinner-border');
    let $pendingRow = null;

    function showAlert(type, message) {
        $alert.removeClass('d-none alert-success alert-danger alert-warning').addClass('alert-' + type).text(message);
    }

    /** Same format as App\Support\Duration::remaining(). */
    function formatRemaining(seconds) {
        if (seconds <= 0) {
            return 'expired';
        }

        let minutes = Math.floor(seconds / 60);

        if (minutes === 0) {
            return '< 1 min';
        }

        const days = Math.floor(minutes / 1440);
        const hours = Math.floor((minutes % 1440) / 60);
        minutes %= 60;

        return [days ? days + ' d' : null, hours ? hours + ' h' : null, minutes ? minutes + ' min' : null]
            .filter(Boolean)
            .slice(0, 2)
            .join(' ');
    }

    function refreshCountdowns() {
        const now = Date.now();

        $table.find('tr[data-file-row]').each(function () {
            const $row = $(this);
            const seconds = Math.floor((Date.parse($row.data('expires-at')) - now) / 1000);

            $row.find('[data-remaining]').text(formatRemaining(seconds));

            if (seconds <= 0) {
                // The server already treats the file as missing; the purge removes it within a minute.
                $row.addClass('text-body-tertiary');
                $row.find('[data-action]').addClass('d-none');
                $row.find('[data-remaining]').text('expired, deleting…');
            }
        });
    }

    function showLocalTimes() {
        $table.find('[data-local-time]').each(function () {
            const date = new Date($(this).data('local-time'));

            $(this).text(date.toLocaleString([], {dateStyle: 'short', timeStyle: 'short'}));
        });
    }

    function removeRow($row) {
        $row.fadeOut(200, function () {
            $row.remove();

            if (!$table.find('tr[data-file-row]').length) {
                $table.find('tbody').append(
                    $('<tr data-empty-row>').append(
                        $('<td colspan="6" class="text-center text-body-secondary py-4">')
                            .text('No files on this page. Reload the page to see the rest.'),
                    ),
                );
            }
        });
    }

    function setBusy(busy) {
        $confirm.prop('disabled', busy);
        $confirmSpinner.toggleClass('d-none', !busy);
    }

    $table.on('click', 'button[data-delete-url]', function () {
        $pendingRow = $(this).closest('tr');
        $(modalElement).find('[data-modal-file-name]').text($(this).data('file-name'));
        modal.show();
    });

    $confirm.on('click', function () {
        if (!$pendingRow) {
            return;
        }

        const $row = $pendingRow;
        const $button = $row.find('button[data-delete-url]');

        setBusy(true);

        $.ajax({url: $button.data('delete-url'), method: 'DELETE', dataType: 'json'})
            .done(function (response) {
                showAlert('success', response.message);
                removeRow($row);
            })
            .fail(function (xhr) {
                if (xhr.status === 404) {
                    showAlert('warning', 'The file "' + $button.data('file-name') + '" has already been deleted.');
                    removeRow($row);

                    return;
                }

                if (xhr.status === 419) {
                    showAlert('danger', 'The page has expired. Reload it and try again.');

                    return;
                }

                showAlert('danger', (xhr.responseJSON && xhr.responseJSON.message) || 'Deletion failed. Try again later.');
            })
            .always(function () {
                setBusy(false);
                $pendingRow = null;
                modal.hide();
            });
    });

    showLocalTimes();
    refreshCountdowns();
    setInterval(refreshCountdowns, 15000);
})(jQuery);
