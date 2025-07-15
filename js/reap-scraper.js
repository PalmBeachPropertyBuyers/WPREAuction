jQuery(document).ready(function($) {
    $('#reap-start-scrape').on('click', function() {
        var $btn = $(this);
        var $log = $('#reap-scrape-log');
        $btn.prop('disabled', true).text('Scraping...');
        $log.text('Starting manual scraping...\n');
        $.post(REAP_AJAX.ajax_url, {
            action: 'reap_manual_scrape',
            nonce: REAP_AJAX.nonce
        }, function(response) {
            if (response.success) {
                $log.text(response.data.log.join('\n'));
            } else {
                $log.text('Error: ' + (response.data ? response.data : 'Unknown error'));
            }
            $btn.prop('disabled', false).text('Start Manual Scraping');
        });
    });
});