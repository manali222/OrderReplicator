<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Order;

use MageClone\OrderReplicator\Api\OrderReplicatorInterface;
use MageClone\OrderReplicator\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Replicate extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::replicate';

    public function __construct(
        Context $context,
        private readonly OrderReplicatorInterface $orderReplicator,
        private readonly Config $config,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Order Replicator module is disabled.')
            ]);
        }

        $orderId = (int) $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return $result->setData([
                'success' => false,
                'message' => __('No source order ID provided.')
            ]);
        }

        // Gather customer data from POST
        $customerData = [
            'email' => $this->getRequest()->getParam('customer_email', ''),
            'firstname' => $this->getRequest()->getParam('customer_firstname', ''),
            'lastname' => $this->getRequest()->getParam('customer_lastname', ''),
            'billing_street' => $this->getRequest()->getParam('billing_street', ''),
            'billing_city' => $this->getRequest()->getParam('billing_city', ''),
            'billing_region' => $this->getRequest()->getParam('billing_region', ''),
            'billing_region_id' => $this->getRequest()->getParam('billing_region_id', ''),
            'billing_postcode' => $this->getRequest()->getParam('billing_postcode', ''),
            'billing_country_id' => $this->getRequest()->getParam('billing_country_id', ''),
            'billing_telephone' => $this->getRequest()->getParam('billing_telephone', ''),
            'shipping_street' => $this->getRequest()->getParam('shipping_street', ''),
            'shipping_city' => $this->getRequest()->getParam('shipping_city', ''),
            'shipping_region' => $this->getRequest()->getParam('shipping_region', ''),
            'shipping_region_id' => $this->getRequest()->getParam('shipping_region_id', ''),
            'shipping_postcode' => $this->getRequest()->getParam('shipping_postcode', ''),
            'shipping_country_id' => $this->getRequest()->getParam('shipping_country_id', ''),
            'shipping_telephone' => $this->getRequest()->getParam('shipping_telephone', ''),
            'shipping_method' => $this->getRequest()->getParam('shipping_method', ''),
            'payment_method' => $this->getRequest()->getParam('payment_method', ''),
        ];

        // Gather item modifications from POST
        $itemModifications = [];
        $modifications = $this->getRequest()->getParam('items', []);
        if (is_array($modifications)) {
            foreach ($modifications as $mod) {
                $itemMod = [];
                if (!empty($mod['original_sku'])) {
                    $itemMod['original_sku'] = $mod['original_sku'];
                }
                if (!empty($mod['sku'])) {
                    $itemMod['sku'] = $mod['sku'];
                }
                if (isset($mod['price']) && $mod['price'] !== '') {
                    $itemMod['price'] = (float) $mod['price'];
                }
                if (isset($mod['qty']) && $mod['qty'] !== '') {
                    $itemMod['qty'] = (float) $mod['qty'];
                }
                if (!empty($itemMod)) {
                    $itemModifications[] = $itemMod;
                }
            }
        }

        try {
            $newOrder = $this->orderReplicator->replicate($orderId, $customerData, $itemModifications);

            return $result->setData([
                'success' => true,
                'message' => __('Order #%1 successfully replicated as #%2.',
                    $this->getRequest()->getParam('source_increment_id', $orderId),
                    $newOrder->getIncrementId()
                ),
                'new_order_id' => $newOrder->getEntityId(),
                'new_increment_id' => $newOrder->getIncrementId(),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
