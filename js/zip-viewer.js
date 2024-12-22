jQuery(document).ready(function ($) {
    $('.zip-file').on('click', function (e) {
        e.preventDefault();

        const fileUrl = $(this).data('file-url');

        $.post(zipViewerAjax.ajax_url, {
            action: 'show_zip_content',
            file_url: fileUrl,
        }, function (response) {
            if (response.success) {
                let fileList = '<ul>';
                response.data.forEach(function (file) {
                    fileList += '<li>' + file + '</li>';
                });
                fileList += '</ul>';

                // Show the content in an alert or append it to the DOM
                alert('ZIP Contents:\n' + response.data.join('\n'));
                $('#zip-content').html(fileList);
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
});
