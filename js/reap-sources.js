jQuery(document).ready(function($) {
    // Open add modal
    $('#reap-add-source').on('click', function() {
        $('#reap-source-form')[0].reset();
        $('#reap-source-form input[name=id]').val('');
        $('#reap-modal-title').text('Add Source');
        $('#reap-source-modal').show();
    });
    // Open edit modal
    $('.reap-edit-source').on('click', function() {
        var $tr = $(this).closest('tr');
        $('#reap-source-form input[name=id]').val($tr.data('id'));
        $('#reap-source-form input[name=name]').val($tr.find('td:eq(0)').text());
        $('#reap-source-form input[name=url]').val($tr.find('td:eq(1) a').attr('href'));
        $('#reap-modal-title').text('Edit Source');
        $('#reap-source-modal').show();
    });
    // Cancel modal
    $('#reap-cancel-modal').on('click', function() {
        $('#reap-source-modal').hide();
    });
    // Save source (add/edit)
    $('#reap-source-form').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize() + '&action=reap_save_source&_wpnonce=' + REAP_AJAX.nonce;
        $.post(REAP_AJAX.ajax_url, data, function(resp) { location.reload(); });
    });
    // Delete source
    $('.reap-delete-source').on('click', function() {
        if (!confirm('Delete this source?')) return;
        var id = $(this).closest('tr').data('id');
        $.post(REAP_AJAX.ajax_url, {action:'reap_delete_source', id:id, _wpnonce:REAP_AJAX.nonce}, function(resp){ location.reload(); });
    });
    // Toggle enable
    $('.reap-toggle-source').on('change', function() {
        var id = $(this).closest('tr').data('id');
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.post(REAP_AJAX.ajax_url, {action:'reap_toggle_source', id:id, enabled:enabled, _wpnonce:REAP_AJAX.nonce});
    });
    // Test source
    $('.reap-test-source').on('click', function() {
        var id = $(this).closest('tr').data('id');
        var $btn = $(this);
        $btn.text('Testing...');
        $.post(REAP_AJAX.ajax_url, {action:'reap_test_source', id:id, _wpnonce:REAP_AJAX.nonce}, function(resp){
            alert(resp.data ? resp.data : 'Test complete');
            $btn.text('Test');
        });
    });
    // Import defaults
    $('#reap-import-defaults').on('click', function() {
        if (!confirm('Import all default sources?')) return;
        $.post(REAP_AJAX.ajax_url, {action:'reap_import_defaults', _wpnonce:REAP_AJAX.nonce}, function(){ location.reload(); });
    });
});