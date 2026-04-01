/* global jQuery, wkrnSlider */
jQuery(function ($) {

    // ── Drag to reorder ───────────────────────────────────────────────────────
    var $tbody = $('#kwwd-sortable');
    if ($tbody.length) {
        $tbody.sortable({
            handle:      '.kwwd-handle',
            placeholder: 'kwwd-sortable-placeholder',
            axis:        'y',
            update: function () {
                var order = [];
                $tbody.find('tr').each(function () {
                    order.push($(this).data('id'));
                });
                $.post(wkrnSlider.ajaxUrl, {
                    action: 'KWWDSlider_reorder_slides',
                    nonce:  wkrnSlider.nonce,
                    order:  order,
                }, function (response) {
                    if (!response.success) {
                        alert('Reorder failed. Please refresh and try again.');
                    }
                });
            }
        });
        $tbody.disableSelection();
    }

    // ── Toggle active ─────────────────────────────────────────────────────────
    $(document).on('change', '.kwwd-active-toggle', function () {
        var $cb    = $(this);
        var id     = $cb.data('id');
        var active = $cb.is(':checked') ? 1 : 0;
        $.post(wkrnSlider.ajaxUrl, {
            action:   'KWWDSlider_toggle_slide',
            nonce:    wkrnSlider.nonce,
            slide_id: id,
            active:   active,
        }, function (response) {
            if (!response.success) {
                alert('Toggle failed. Please refresh and try again.');
                $cb.prop('checked', !active);
            }
        });
    });

    // ── Copy title to caption ─────────────────────────────────────────────────
    $(document).on('click', '#copy-title-to-caption', function () {
        var title = $('#title').val();
        $('#caption').val(title);
    });

});
