<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Controller\Adminhtml\Order;

use MageClone\OrderReplicator\Api\OrderReplicatorInterface;
use MageClone\OrderReplicator\Controller\Adminhtml\Order\Replicate;
use MageClone\OrderReplicator\Helper\Config;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReplicateTest extends TestCase
{
    private Replicate $controller;
    private OrderReplicatorInterface|MockObject $orderReplicator;
    private Config|MockObject $config;
    private JsonFactory|MockObject $jsonFactory;
    private RequestInterface|MockObject $request;
    private Json|MockObject $jsonResult;

    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['getParam', 'isPost'])
            ->getMockForAbstractClass();

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        $this->orderReplicator = $this->createMock(OrderReplicatorInterface::class);
        $this->config = $this->createMock(Config::class);

        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonResult->method('setData')->willReturnSelf();

        $this->jsonFactory = $this->createMock(JsonFactory::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->controller = new Replicate(
            $context,
            $this->orderReplicator,
            $this->config,
            $this->jsonFactory
        );
    }

    public function testExecuteReturnsErrorWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function ($data) {
                return $data['success'] === false
                    && str_contains((string) $data['message'], 'disabled');
            }));

        $this->controller->execute();
    }

    public function testExecuteReturnsErrorWhenNoOrderId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->request->method('getParam')
            ->willReturnMap([
                ['order_id', null, 0],
            ]);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function ($data) {
                return $data['success'] === false;
            }));

        $this->controller->execute();
    }

    public function testExecuteReturnsSuccessOnReplication(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->request->method('getParam')
            ->willReturnMap([
                ['order_id', null, 1],
                ['source_increment_id', 1, '000000001'],
                ['customer_email', '', 'new@test.com'],
                ['customer_firstname', '', 'John'],
                ['customer_lastname', '', 'Doe'],
                ['billing_street', '', ''],
                ['billing_city', '', ''],
                ['billing_region', '', ''],
                ['billing_region_id', '', ''],
                ['billing_postcode', '', ''],
                ['billing_country_id', '', ''],
                ['billing_telephone', '', ''],
                ['shipping_street', '', ''],
                ['shipping_city', '', ''],
                ['shipping_region', '', ''],
                ['shipping_region_id', '', ''],
                ['shipping_postcode', '', ''],
                ['shipping_country_id', '', ''],
                ['shipping_telephone', '', ''],
                ['shipping_method', '', ''],
                ['payment_method', '', ''],
                ['items', [], []],
            ]);

        $newOrder = $this->createMock(OrderInterface::class);
        $newOrder->method('getEntityId')->willReturn(99);
        $newOrder->method('getIncrementId')->willReturn('000000099');

        $this->orderReplicator->method('replicate')->willReturn($newOrder);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function ($data) {
                return $data['success'] === true
                    && $data['new_increment_id'] === '000000099';
            }));

        $this->controller->execute();
    }
}
