<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Directory\Model\CountryFactory;

class ViewOrder extends Template
{
    protected $_template = 'MageClone_OrderReplicator::order/view.phtml';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CountryFactory $countryFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?\Magento\Sales\Api\Data\OrderInterface
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return null;
        }

        try {
            return $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getReplicateUrl(): string
    {
        return $this->getUrl('orderreplicator/order/replicate');
    }

    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'price' => (float) $item->getPrice(),
                'qty' => (float) $item->getQtyOrdered(),
                'row_total' => (float) $item->getRowTotal(),
            ];
        }

        return $items;
    }

    public function getCountryName(string $countryId): string
    {
        try {
            $country = $this->countryFactory->create()->loadByCode($countryId);
            return $country->getName() ?: $countryId;
        } catch (\Exception $e) {
            return $countryId;
        }
    }
}
