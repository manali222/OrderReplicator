<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Plugin\Adminhtml;

use Magento\Sales\Block\Adminhtml\Order\View;

class SalesOrderViewButtonsPlugin
{
    public function beforeSetLayout(View $subject): void
    {
        $orderId = $subject->getOrderId();
        if (!$orderId) {
            return;
        }

        $subject->addButton(
            'replicate_order',
            [
                'label' => __('Replicate Order'),
                'class' => 'action-secondary',
                'onclick' => sprintf(
                    "setLocation('%s')",
                    $subject->getUrl('orderreplicator/order/view', ['order_id' => $orderId])
                ),
            ]
        );

        $subject->addButton(
            'csv_bulk_replicate',
            [
                'label' => __('CSV Bulk Replicate'),
                'class' => 'action-secondary',
                'onclick' => sprintf(
                    "setLocation('%s')",
                    $subject->getUrl('orderreplicator/csv/upload', ['order_id' => $orderId])
                ),
            ]
        );
    }
}
