<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Api;

/**
 * Order Replicator Service Interface
 */
interface OrderReplicatorInterface
{
    /**
     * Replicate an existing order with modifications
     *
     * @param int $sourceOrderId Original order ID to clone
     * @param array $customerData Customer email, addresses
     * @param array $itemModifications SKU/price changes [['sku' => 'NEW-SKU', 'price' => 29.99, 'qty' => 2]]
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function replicate(
        int $sourceOrderId,
        array $customerData,
        array $itemModifications = []
    ): \Magento\Sales\Api\Data\OrderInterface;

    /**
     * Replicate order from CSV row data
     *
     * @param int $sourceOrderId
     * @param array $csvRow Parsed CSV row with customer data and item modifications
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function replicateFromCsvRow(
        int $sourceOrderId,
        array $csvRow
    ): \Magento\Sales\Api\Data\OrderInterface;
}
