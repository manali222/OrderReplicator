<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class View extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::view';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('No order ID specified.'));
            return $this->_redirect('sales/order/index');
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->_redirect('sales/order/index');
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('MageClone_OrderReplicator::orders');
        $page->getConfig()->getTitle()->prepend(
            __('Replicate Order #%1', $order->getIncrementId())
        );
        return $page;
    }
}
