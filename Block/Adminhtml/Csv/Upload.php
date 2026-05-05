<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Block\Adminhtml\Csv;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;

class Upload extends Template
{
    protected $_template = 'MageClone_OrderReplicator::csv/upload.phtml';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProcessUrl(): string
    {
        return $this->getUrl('orderreplicator/csv/process');
    }

    public function getDownloadTemplateUrl(): string
    {
        return $this->getUrl('orderreplicator/csv/downloadtemplate');
    }

    public function getPrefilledOrderId(): string
    {
        $orderId = (int) $this->getRequest()->getParam('order_id', 0);
        if (!$orderId) {
            return '';
        }

        try {
            $order = $this->orderRepository->get($orderId);
            return (string) $order->getIncrementId();
        } catch (\Exception $e) {
            return '';
        }
    }
}
