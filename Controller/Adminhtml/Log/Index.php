<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::view';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('MageClone_OrderReplicator::log');
        $page->getConfig()->getTitle()->prepend(__('Replication Log'));
        return $page;
    }
}
