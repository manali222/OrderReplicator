<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model;

use MageClone\OrderReplicator\Api\OrderReplicatorInterface;
use MageClone\OrderReplicator\Helper\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class OrderReplicator implements OrderReplicatorInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AccountManagementInterface $accountManagement,
        private readonly QuoteFactory $quoteFactory,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly ReplicationLogFactory $replicationLogFactory,
        private readonly ResourceModel\ReplicationLog $replicationLogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function replicate(
        int $sourceOrderId,
        array $customerData,
        array $itemModifications = []
    ): OrderInterface {
        $log = $this->replicationLogFactory->create();

        try {
            // 1. Load source order
            $sourceOrder = $this->orderRepository->get($sourceOrderId);

            $log->setData([
                'source_order_id' => $sourceOrderId,
                'source_increment_id' => $sourceOrder->getIncrementId(),
                'customer_email' => $customerData['email'] ?? '',
                'modifications_json' => json_encode($itemModifications),
                'status' => 'processing'
            ]);
            $this->replicationLogResource->save($log);

            // 2. Resolve or create customer
            $customer = $this->resolveCustomer($customerData, (int) $sourceOrder->getStoreId());

            // 3. Build quote from source order
            $quote = $this->buildQuote($sourceOrder, $customer, $customerData, $itemModifications);

            // 4. Place the order
            $newOrderId = $this->cartManagement->placeOrder($quote->getId());
            $newOrder = $this->orderRepository->get($newOrderId);

            // 5. Set desired status
            $desiredStatus = $this->config->getDefaultOrderStatus((int) $sourceOrder->getStoreId());
            $newOrder->setStatus($desiredStatus);
            $newOrder->addCommentToStatusHistory(
                sprintf(
                    'Order replicated from #%s by Order Replicator.',
                    $sourceOrder->getIncrementId()
                )
            );
            $this->orderRepository->save($newOrder);

            // 6. Update log
            $log->setData('new_order_id', $newOrder->getEntityId());
            $log->setData('new_increment_id', $newOrder->getIncrementId());
            $log->setData('status', 'completed');
            $this->replicationLogResource->save($log);

            $this->logger->info(sprintf(
                'OrderReplicator: Successfully replicated order #%s -> #%s for %s',
                $sourceOrder->getIncrementId(),
                $newOrder->getIncrementId(),
                $customerData['email'] ?? 'unknown'
            ));

            return $newOrder;

        } catch (\Exception $e) {
            $log->setData('status', 'failed');
            $log->setData('error_message', $e->getMessage());
            try {
                $this->replicationLogResource->save($log);
            } catch (\Exception $logException) {
                $this->logger->error('OrderReplicator: Failed to save error log: ' . $logException->getMessage());
            }

            $this->logger->error('OrderReplicator: Replication failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new LocalizedException(
                __('Order replication failed: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function replicateFromCsvRow(
        int $sourceOrderId,
        array $csvRow
    ): OrderInterface {
        $customerData = [
            'email' => $csvRow['customer_email'] ?? '',
            'firstname' => $csvRow['customer_firstname'] ?? '',
            'lastname' => $csvRow['customer_lastname'] ?? '',
            'billing_street' => $csvRow['billing_street'] ?? '',
            'billing_city' => $csvRow['billing_city'] ?? '',
            'billing_region' => $csvRow['billing_region'] ?? '',
            'billing_region_id' => $csvRow['billing_region_id'] ?? '',
            'billing_postcode' => $csvRow['billing_postcode'] ?? '',
            'billing_country_id' => $csvRow['billing_country_id'] ?? '',
            'billing_telephone' => $csvRow['billing_telephone'] ?? '',
            'shipping_street' => !empty($csvRow['shipping_street']) ? $csvRow['shipping_street'] : ($csvRow['billing_street'] ?? ''),
            'shipping_city' => !empty($csvRow['shipping_city']) ? $csvRow['shipping_city'] : ($csvRow['billing_city'] ?? ''),
            'shipping_region' => !empty($csvRow['shipping_region']) ? $csvRow['shipping_region'] : ($csvRow['billing_region'] ?? ''),
            'shipping_region_id' => !empty($csvRow['shipping_region_id']) ? $csvRow['shipping_region_id'] : ($csvRow['billing_region_id'] ?? ''),
            'shipping_postcode' => !empty($csvRow['shipping_postcode']) ? $csvRow['shipping_postcode'] : ($csvRow['billing_postcode'] ?? ''),
            'shipping_country_id' => !empty($csvRow['shipping_country_id']) ? $csvRow['shipping_country_id'] : ($csvRow['billing_country_id'] ?? ''),
            'shipping_telephone' => !empty($csvRow['shipping_telephone']) ? $csvRow['shipping_telephone'] : ($csvRow['billing_telephone'] ?? ''),
            'shipping_method' => $csvRow['shipping_method'] ?? '',
            'payment_method' => $csvRow['payment_method'] ?? '',
        ];

        // Parse item modifications from CSV
        $itemModifications = [];
        if (!empty($csvRow['item_modifications'])) {
            $mods = json_decode($csvRow['item_modifications'], true);
            if (is_array($mods)) {
                $itemModifications = $mods;
            }
        }

        // Support simple column-based SKU/price override
        if (!empty($csvRow['override_sku'])) {
            $skus = explode('|', $csvRow['override_sku']);
            $prices = !empty($csvRow['override_price']) ? explode('|', $csvRow['override_price']) : [];
            $qtys = !empty($csvRow['override_qty']) ? explode('|', $csvRow['override_qty']) : [];

            foreach ($skus as $index => $sku) {
                $mod = ['sku' => trim($sku)];
                if (isset($prices[$index])) {
                    $mod['price'] = (float) trim($prices[$index]);
                }
                if (isset($qtys[$index])) {
                    $mod['qty'] = (float) trim($qtys[$index]);
                }
                $mod['item_index'] = $index;
                $itemModifications[] = $mod;
            }
        }

        return $this->replicate($sourceOrderId, $customerData, $itemModifications);
    }

    /**
     * Resolve existing customer or create new one
     */
    private function resolveCustomer(array $customerData, int $storeId): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $email = $customerData['email'] ?? '';
        if (empty($email)) {
            return null; // Guest order
        }

        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        try {
            return $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException $e) {
            if (!$this->config->shouldAutoCreateCustomer($storeId)) {
                return null; // Place as guest
            }

            // Create new customer
            $customer = $this->customerFactory->create();
            $customer->setEmail($email);
            $customer->setFirstname($customerData['firstname'] ?? 'Customer');
            $customer->setLastname($customerData['lastname'] ?? 'Account');
            $customer->setWebsiteId($websiteId);
            $customer->setStoreId($storeId);

            return $this->accountManagement->createAccount($customer);
        }
    }

    /**
     * Build a quote from the source order with modifications
     */
    private function buildQuote(
        OrderInterface $sourceOrder,
        ?\Magento\Customer\Api\Data\CustomerInterface $customer,
        array $customerData,
        array $itemModifications
    ): \Magento\Quote\Model\Quote {
        $store = $this->storeManager->getStore($sourceOrder->getStoreId());

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId($store->getId());
        $quote->setCurrency();

        // Assign customer or set as guest
        if ($customer) {
            $quote->assignCustomer($customer);
        } else {
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerEmail($customerData['email'] ?? $sourceOrder->getCustomerEmail());
            $quote->setCustomerFirstname($customerData['firstname'] ?? $sourceOrder->getCustomerFirstname());
            $quote->setCustomerLastname($customerData['lastname'] ?? $sourceOrder->getCustomerLastname());
        }

        // Add items from source order with modifications
        $this->addItemsToQuote($quote, $sourceOrder, $itemModifications);

        // Set addresses
        $this->setQuoteAddresses($quote, $sourceOrder, $customerData);

        // Set shipping method — per-customer override or source order fallback
        $shippingMethod = !empty($customerData['shipping_method'])
            ? $customerData['shipping_method']
            : $sourceOrder->getShippingMethod();
        if ($shippingMethod) {
            $quote->getShippingAddress()
                ->setShippingMethod($shippingMethod)
                ->setCollectShippingRates(true)
                ->collectShippingRates();
        }

        // Save quote first so store_id is persisted for payment processing
        $quote->setInventoryProcessed(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        // Set payment method — per-customer override or config default
        $paymentMethod = !empty($customerData['payment_method'])
            ? $customerData['payment_method']
            : $this->config->getDefaultPaymentMethod((int) $store->getId());
        $quote->setPaymentMethod($paymentMethod);
        $quote->getPayment()->importData(['method' => $paymentMethod]);
        $this->cartRepository->save($quote);

        return $quote;
    }

    /**
     * Add source order items to quote, applying any SKU/price modifications
     */
    private function addItemsToQuote(
        \Magento\Quote\Model\Quote $quote,
        OrderInterface $sourceOrder,
        array $itemModifications
    ): void {
        $modsByIndex = [];
        $modsBySku = [];

        foreach ($itemModifications as $mod) {
            if (isset($mod['item_index'])) {
                $modsByIndex[(int) $mod['item_index']] = $mod;
            }
            if (isset($mod['original_sku'])) {
                $modsBySku[$mod['original_sku']] = $mod;
            }
        }

        $itemIndex = 0;
        foreach ($sourceOrder->getItems() as $orderItem) {
            // Skip child items of configurables
            if ($orderItem->getParentItemId()) {
                continue;
            }

            $sku = $orderItem->getSku();
            $qty = (float) $orderItem->getQtyOrdered();
            $customPrice = null;

            // Check for modifications by index
            if (isset($modsByIndex[$itemIndex])) {
                $mod = $modsByIndex[$itemIndex];
                if (!empty($mod['sku'])) {
                    $sku = $mod['sku'];
                }
                if (isset($mod['price'])) {
                    $customPrice = (float) $mod['price'];
                }
                if (isset($mod['qty'])) {
                    $qty = (float) $mod['qty'];
                }
            }

            // Check for modifications by original SKU
            if (isset($modsBySku[$orderItem->getSku()])) {
                $mod = $modsBySku[$orderItem->getSku()];
                if (!empty($mod['sku'])) {
                    $sku = $mod['sku'];
                }
                if (isset($mod['price'])) {
                    $customPrice = (float) $mod['price'];
                }
                if (isset($mod['qty'])) {
                    $qty = (float) $mod['qty'];
                }
            }

            try {
                $product = $this->productRepository->get($sku);
                $request = new \Magento\Framework\DataObject(['qty' => $qty]);
                $quoteItem = $quote->addProduct($product, $request);

                if (is_string($quoteItem)) {
                    throw new LocalizedException(__('Could not add product %1: %2', $sku, $quoteItem));
                }

                if ($customPrice !== null) {
                    $quoteItem->setCustomPrice($customPrice);
                    $quoteItem->setOriginalCustomPrice($customPrice);
                    $quoteItem->getProduct()->setIsSuperMode(true);
                }
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('Product with SKU "%1" not found.', $sku));
            }

            $itemIndex++;
        }
    }

    /**
     * Set billing and shipping addresses on quote
     */
    private function setQuoteAddresses(
        \Magento\Quote\Model\Quote $quote,
        OrderInterface $sourceOrder,
        array $customerData
    ): void {
        // Billing address — use source values when overrides are empty
        $sourceBilling = $sourceOrder->getBillingAddress();
        $billingData = [
            'firstname' => !empty($customerData['firstname']) ? $customerData['firstname'] : $sourceBilling->getFirstname(),
            'lastname' => !empty($customerData['lastname']) ? $customerData['lastname'] : $sourceBilling->getLastname(),
            'street' => !empty($customerData['billing_street']) ? $customerData['billing_street'] : $sourceBilling->getStreet(),
            'city' => !empty($customerData['billing_city']) ? $customerData['billing_city'] : $sourceBilling->getCity(),
            'region' => !empty($customerData['billing_region']) ? $customerData['billing_region'] : $sourceBilling->getRegion(),
            'region_id' => !empty($customerData['billing_region_id']) ? $customerData['billing_region_id'] : $sourceBilling->getRegionId(),
            'postcode' => !empty($customerData['billing_postcode']) ? $customerData['billing_postcode'] : $sourceBilling->getPostcode(),
            'country_id' => !empty($customerData['billing_country_id']) ? $customerData['billing_country_id'] : $sourceBilling->getCountryId(),
            'telephone' => !empty($customerData['billing_telephone']) ? $customerData['billing_telephone'] : $sourceBilling->getTelephone(),
            'email' => !empty($customerData['email']) ? $customerData['email'] : $sourceOrder->getCustomerEmail(),
        ];

        $quoteBilling = $quote->getBillingAddress();
        $quoteBilling->addData($billingData);

        // Shipping address — use source values when overrides are empty
        $sourceShipping = $sourceOrder->getShippingAddress();
        if ($sourceShipping) {
            $shippingData = [
                'firstname' => !empty($customerData['firstname']) ? $customerData['firstname'] : $sourceShipping->getFirstname(),
                'lastname' => !empty($customerData['lastname']) ? $customerData['lastname'] : $sourceShipping->getLastname(),
                'street' => !empty($customerData['shipping_street']) ? $customerData['shipping_street'] : $sourceShipping->getStreet(),
                'city' => !empty($customerData['shipping_city']) ? $customerData['shipping_city'] : $sourceShipping->getCity(),
                'region' => !empty($customerData['shipping_region']) ? $customerData['shipping_region'] : $sourceShipping->getRegion(),
                'region_id' => !empty($customerData['shipping_region_id']) ? $customerData['shipping_region_id'] : $sourceShipping->getRegionId(),
                'postcode' => !empty($customerData['shipping_postcode']) ? $customerData['shipping_postcode'] : $sourceShipping->getPostcode(),
                'country_id' => !empty($customerData['shipping_country_id']) ? $customerData['shipping_country_id'] : $sourceShipping->getCountryId(),
                'telephone' => !empty($customerData['shipping_telephone']) ? $customerData['shipping_telephone'] : $sourceShipping->getTelephone(),
                'email' => !empty($customerData['email']) ? $customerData['email'] : $sourceOrder->getCustomerEmail(),
            ];

            $quoteShipping = $quote->getShippingAddress();
            $quoteShipping->addData($shippingData);
        }
    }
}
