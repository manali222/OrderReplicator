define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config, element) {
        var replicateUrl = config.replicateUrl,
            orderId = config.orderId,
            sourceIncrementId = config.sourceIncrementId;

        $('#btn-replicate-order').on('click', function () {
            var btn = $(this),
                resultDiv = $('#replication-result'),
                formData;

            // Validate required fields
            var email = $('#customer_email').val();
            var firstname = $('#customer_firstname').val();
            var lastname = $('#customer_lastname').val();

            if (!email || !firstname || !lastname) {
                alert({
                    title: $t('Validation Error'),
                    content: $t('Please fill in Customer Email, First Name, and Last Name.')
                });
                return;
            }

            // Collect form data
            formData = new FormData();
            formData.append('form_key', FORM_KEY);
            formData.append('order_id', orderId);
            formData.append('source_increment_id', sourceIncrementId);

            // Customer fields
            $(element).find('input[name], select[name]').each(function () {
                var name = $(this).attr('name');
                var val = $(this).val();
                if (name && val !== undefined) {
                    formData.append(name, val);
                }
            });

            btn.prop('disabled', true).text($t('Processing...'));
            resultDiv.hide();

            $.ajax({
                url: replicateUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    resultDiv.show();
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('success');
                        resultDiv.html(
                            '<strong>' + response.message + '</strong>' +
                            '<br/><a href="' + BASE_URL + 'sales/order/view/order_id/' +
                            response.new_order_id + '/">' +
                            $t('View New Order #') + response.new_increment_id + '</a>'
                        );
                    } else {
                        resultDiv.removeClass('success').addClass('error');
                        resultDiv.html('<strong>' + $t('Error: ') + '</strong>' + response.message);
                    }
                },
                error: function (xhr) {
                    resultDiv.show().removeClass('success').addClass('error');
                    resultDiv.html('<strong>' + $t('Request failed. Please try again.') + '</strong>');
                },
                complete: function () {
                    btn.prop('disabled', false).text($t('Replicate Order'));
                }
            });
        });
    };
});
