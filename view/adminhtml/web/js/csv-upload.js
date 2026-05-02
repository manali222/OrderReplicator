define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config, element) {
        var processUrl = config.processUrl;

        $('#btn-process-csv').on('click', function () {
            var btn = $(this),
                resultDiv = $('#csv-result'),
                progressDiv = $('#csv-progress'),
                sourceOrderId = $('#source_order_id').val(),
                fileInput = $('#csv_file')[0];

            if (!sourceOrderId) {
                alert({
                    title: $t('Validation Error'),
                    content: $t('Please enter a Source Order ID.')
                });
                return;
            }

            if (!fileInput.files || !fileInput.files.length) {
                alert({
                    title: $t('Validation Error'),
                    content: $t('Please select a CSV file.')
                });
                return;
            }

            var formData = new FormData();
            formData.append('form_key', FORM_KEY);
            formData.append('source_order_id', sourceOrderId);
            formData.append('csv_file', fileInput.files[0]);

            btn.prop('disabled', true).text($t('Processing CSV...'));
            resultDiv.hide();
            progressDiv.show();
            progressDiv.find('.progress-text').text($t('Uploading and processing CSV...'));
            progressDiv.find('.progress-fill').css('width', '30%');

            $.ajax({
                url: processUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    progressDiv.find('.progress-fill').css('width', '100%');

                    resultDiv.show();
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('success');
                        var html = '<strong>' + response.message + '</strong>';

                        if (response.details && response.details.orders && response.details.orders.length) {
                            html += '<table class="admin__table-primary" style="margin-top:10px;">';
                            html += '<thead><tr><th>Row</th><th>New Order #</th><th>Email</th></tr></thead>';
                            html += '<tbody>';
                            response.details.orders.forEach(function (order) {
                                html += '<tr>';
                                html += '<td>' + order.row + '</td>';
                                html += '<td><a href="' + BASE_URL + 'sales/order/view/order_id/' +
                                    order.order_id + '/">' + order.order_id + '</a></td>';
                                html += '<td>' + order.email + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                        }

                        if (response.details && response.details.errors && response.details.errors.length) {
                            html += '<div class="csv-errors" style="margin-top:10px; color:#e22626;">';
                            html += '<strong>' + $t('Errors:') + '</strong><ul>';
                            response.details.errors.forEach(function (error) {
                                html += '<li>' + error + '</li>';
                            });
                            html += '</ul></div>';
                        }

                        resultDiv.html(html);
                    } else {
                        resultDiv.removeClass('success').addClass('error');
                        resultDiv.html('<strong>' + $t('Error: ') + '</strong>' + response.message);
                    }
                },
                error: function () {
                    resultDiv.show().removeClass('success').addClass('error');
                    resultDiv.html('<strong>' + $t('Request failed. Please try again.') + '</strong>');
                },
                complete: function () {
                    btn.prop('disabled', false).text($t('Process CSV & Create Orders'));
                    setTimeout(function () {
                        progressDiv.hide();
                    }, 2000);
                }
            });
        });
    };
});
