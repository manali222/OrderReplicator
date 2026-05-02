<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'orderreplicator/general/enabled';
    private const XML_PATH_SEND_EMAIL = 'orderreplicator/general/send_email';
    private const XML_PATH_DEFAULT_STATUS = 'orderreplicator/general/default_order_status';
    private const XML_PATH_DEFAULT_PAYMENT = 'orderreplicator/general/default_payment_method';
    private const XML_PATH_AUTO_CREATE_CUSTOMER = 'orderreplicator/general/auto_create_customer';
    private const XML_PATH_MAX_ROWS = 'orderreplicator/csv/max_rows';
    private const XML_PATH_DELIMITER = 'orderreplicator/csv/delimiter';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldSendEmail(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SEND_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDefaultOrderStatus(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'pending';
    }

    public function getDefaultPaymentMethod(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_PAYMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'checkmo';
    }

    public function shouldAutoCreateCustomer(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_CREATE_CUSTOMER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMaxCsvRows(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_MAX_ROWS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value ? (int) $value : 500;
    }

    public function getCsvDelimiter(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_DELIMITER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: ',';
    }
}
