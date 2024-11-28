jQuery(document).ready(function($) {
    $('#export-post-data').on('click', function(event) {
        event.preventDefault();

        var postId = $('#post_ID').val();
        $.ajax({
            url: videoExport.ajax_url,
            type: 'POST',
            data: {
                action: 'export_post_data',
                post_id: postId,
                nonce: videoExport.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Success:', response.data);
                } else {
                    console.error('Error:', response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
            }
        });
    });
});