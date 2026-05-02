<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Helper;

use MageClone\OrderReplicator\Helper\Config;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface|MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->config = new Config($context);
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('orderreplicator/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('orderreplicator/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    public function testGetDefaultOrderStatusReturnsPendingWhenNotSet(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/general/default_order_status', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals('pending', $this->config->getDefaultOrderStatus());
    }

    public function testGetDefaultOrderStatusReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/general/default_order_status', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('processing');

        $this->assertEquals('processing', $this->config->getDefaultOrderStatus());
    }

    public function testGetDefaultPaymentMethodReturnsCheckmoWhenNotSet(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/general/default_payment_method', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals('checkmo', $this->config->getDefaultPaymentMethod());
    }

    public function testGetMaxCsvRowsReturns500WhenNotSet(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/csv/max_rows', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(500, $this->config->getMaxCsvRows());
    }

    public function testGetMaxCsvRowsReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/csv/max_rows', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('1000');

        $this->assertEquals(1000, $this->config->getMaxCsvRows());
    }

    public function testGetCsvDelimiterReturnsCommaWhenNotSet(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('orderreplicator/csv/delimiter', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(',', $this->config->getCsvDelimiter());
    }

    public function testShouldSendEmailReturnsBool(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('orderreplicator/general/send_email', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->shouldSendEmail());
    }

    public function testShouldAutoCreateCustomerReturnsBool(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('orderreplicator/general/auto_create_customer', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->shouldAutoCreateCustomer());
    }
}
