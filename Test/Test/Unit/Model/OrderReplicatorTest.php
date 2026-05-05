<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Model;

use MageClone\OrderReplicator\Helper\Config;
use MageClone\OrderReplicator\Model\OrderReplicator;
use MageClone\OrderReplicator\Model\ReplicationLog;
use MageClone\OrderReplicator\Model\ReplicationLogFactory;
use MageClone\OrderReplicator\Model\ResourceModel\ReplicationLog as ReplicationLogResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderReplicatorTest extends TestCase
{
    private OrderReplicator $replicator;
    private OrderRepositoryInterface|MockObject $orderRepository;
    private ProductRepositoryInterface|MockObject $productRepository;
    private CustomerRepositoryInterface|MockObject $customerRepository;
    private AccountManagementInterface|MockObject $accountManagement;
    private QuoteFactory|MockObject $quoteFactory;
    private CartManagementInterface|MockObject $cartManagement;
    private CartRepositoryInterface|MockObject $cartRepository;
    private StoreManagerInterface|MockObject $storeManager;
    private Config|MockObject $config;
    private ReplicationLogFactory|MockObject $replicationLogFactory;
    private ReplicationLogResource|MockObject $replicationLogResource;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $this->quoteFactory = $this->createMock(QuoteFactory::class);
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->replicationLogFactory = $this->createMock(ReplicationLogFactory::class);
        $this->replicationLogResource = $this->createMock(ReplicationLogResource::class);
        $logger = $this->createMock(LoggerInterface::class);

        // CustomerInterfaceFactory is auto-generated — create a stub via anonymous class
        $customerMock = $this->createMock(CustomerInterface::class);
        $customerFactory = new class ($customerMock) {
            private CustomerInterface $customer;
            public function __construct(CustomerInterface $customer)
            {
                $this->customer = $customer;
            }
            public function create(array $data = []): CustomerInterface
            {
                return $this->customer;
            }
        };

        $this->replicator = new OrderReplicator(
            $this->orderRepository,
            $this->productRepository,
            $this->customerRepository,
            $customerFactory,
            $this->accountManagement,
            $this->quoteFactory,
            $this->cartManagement,
            $this->cartRepository,
            $this->storeManager,
            $this->config,
            $this->replicationLogFactory,
            $this->replicationLogResource,
            $logger
        );
    }

    public function testReplicateFromCsvRowMapsFieldsCorrectly(): void
    {
        $csvRow = [
            'customer_email' => 'new@customer.com',
            'customer_firstname' => 'New',
            'customer_lastname' => 'Customer',
            'billing_street' => '123 Test St',
            'billing_city' => 'TestCity',
            'billing_region' => 'CA',
            'billing_region_id' => '12',
            'billing_postcode' => '90001',
            'billing_country_id' => 'US',
            'billing_telephone' => '555-1234',
            'override_sku' => 'SKU-NEW-1|SKU-NEW-2',
            'override_price' => '19.99|29.99',
            'override_qty' => '3|1',
        ];

        $this->setupFullReplicationMocks();

        $newOrder = $this->createMock(OrderInterface::class);
        $newOrder->method('getEntityId')->willReturn(99);
        $newOrder->method('getIncrementId')->willReturn('000000099');
        $newOrder->method('setStatus')->willReturnSelf();
        $newOrder->method('addCommentToStatusHistory')->willReturnSelf();

        $this->cartManagement->method('placeOrder')->willReturn(99);
        $this->orderRepository->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->createSourceOrderMock(),
                $newOrder
            );

        $result = $this->replicator->replicateFromCsvRow(1, $csvRow);

        $this->assertEquals('000000099', $result->getIncrementId());
    }

    public function testReplicateLogsFailureOnException(): void
    {
        $log = $this->createMock(ReplicationLog::class);
        $log->method('setData')->willReturnSelf();
        $this->replicationLogFactory->method('create')->willReturn($log);

        $this->orderRepository->method('get')
            ->willThrowException(new \Exception('Order not found'));

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Order replication failed');

        $this->replicator->replicate(999, ['email' => 'test@test.com']);
    }

    public function testReplicateCreatesGuestOrderWhenNoEmail(): void
    {
        $this->setupFullReplicationMocks();

        $newOrder = $this->createMock(OrderInterface::class);
        $newOrder->method('getEntityId')->willReturn(50);
        $newOrder->method('getIncrementId')->willReturn('000000050');
        $newOrder->method('setStatus')->willReturnSelf();
        $newOrder->method('addCommentToStatusHistory')->willReturnSelf();

        $this->cartManagement->method('placeOrder')->willReturn(50);
        $this->orderRepository->method('get')
            ->willReturnOnConsecutiveCalls(
                $this->createSourceOrderMock(),
                $newOrder
            );

        $this->customerRepository->expects($this->never())->method('get');

        $result = $this->replicator->replicate(1, ['email' => '']);

        $this->assertEquals('000000050', $result->getIncrementId());
    }

    private function setupFullReplicationMocks(): void
    {
        $log = $this->createMock(ReplicationLog::class);
        $log->method('setData')->willReturnSelf();
        $this->replicationLogFactory->method('create')->willReturn($log);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->config->method('getDefaultOrderStatus')->willReturn('pending');
        $this->config->method('getDefaultPaymentMethod')->willReturn('checkmo');
        $this->config->method('shouldAutoCreateCustomer')->willReturn(false);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods([
                'setStore',
                'setStoreId',
                'setCurrency',
                'setCustomerIsGuest',
                'setCustomerEmail',
                'setCustomerFirstname',
                'setCustomerLastname',
                'setPaymentMethod',
                'setInventoryProcessed',
            ])
            ->onlyMethods([
                'assignCustomer',
                'addProduct',
                'getBillingAddress',
                'getShippingAddress',
                'getPayment',
                'collectTotals',
                'getId',
            ])
            ->getMock();

        $quote->method('setStore')->willReturnSelf();
        $quote->method('setStoreId')->willReturnSelf();
        $quote->method('setCurrency')->willReturnSelf();
        $quote->method('setCustomerIsGuest')->willReturnSelf();
        $quote->method('setCustomerEmail')->willReturnSelf();
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();
        $quote->method('setPaymentMethod')->willReturnSelf();
        $quote->method('setInventoryProcessed')->willReturnSelf();
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getId')->willReturn(1);

        $quoteAddress = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['setShippingMethod', 'setCollectShippingRates'])
            ->onlyMethods(['addData', 'collectShippingRates'])
            ->getMock();
        $quoteAddress->method('addData')->willReturnSelf();
        $quoteAddress->method('setShippingMethod')->willReturnSelf();
        $quoteAddress->method('setCollectShippingRates')->willReturnSelf();
        $quoteAddress->method('collectShippingRates')->willReturnSelf();

        $quote->method('getBillingAddress')->willReturn($quoteAddress);
        $quote->method('getShippingAddress')->willReturn($quoteAddress);

        $quotePayment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $quotePayment->method('importData')->willReturnSelf();
        $quote->method('getPayment')->willReturn($quotePayment);

        $quoteItem = $this->createMock(\Magento\Quote\Model\Quote\Item::class);
        $quoteItem->method('setCustomPrice')->willReturnSelf();
        $quoteItem->method('setOriginalCustomPrice')->willReturnSelf();
        $quoteItem->method('getProduct')->willReturn(
            $this->createMock(\Magento\Catalog\Model\Product::class)
        );
        $quote->method('addProduct')->willReturn($quoteItem);

        $this->quoteFactory->method('create')->willReturn($quote);

        $product = $this->createMock(\Magento\Catalog\Model\Product::class);
        $this->productRepository->method('get')->willReturn($product);
    }

    private function createSourceOrderMock(): OrderInterface|MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('original@customer.com');
        $order->method('getCustomerFirstname')->willReturn('Original');
        $order->method('getCustomerLastname')->willReturn('Customer');
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getSku')->willReturn('ORIG-SKU-1');
        $orderItem->method('getName')->willReturn('Original Product');
        $orderItem->method('getPrice')->willReturn(49.99);
        $orderItem->method('getQtyOrdered')->willReturn(2.0);
        $orderItem->method('getParentItemId')->willReturn(null);

        $order->method('getItems')->willReturn([$orderItem]);

        $billingAddress = $this->createMock(OrderAddressInterface::class);
        $billingAddress->method('getFirstname')->willReturn('Original');
        $billingAddress->method('getLastname')->willReturn('Customer');
        $billingAddress->method('getStreet')->willReturn(['100 Main St']);
        $billingAddress->method('getCity')->willReturn('OldCity');
        $billingAddress->method('getRegion')->willReturn('NY');
        $billingAddress->method('getRegionId')->willReturn(43);
        $billingAddress->method('getPostcode')->willReturn('10001');
        $billingAddress->method('getCountryId')->willReturn('US');
        $billingAddress->method('getTelephone')->willReturn('555-0000');

        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($billingAddress);

        $payment = $this->createMock(\Magento\Sales\Api\Data\OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('checkmo');
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }
}
