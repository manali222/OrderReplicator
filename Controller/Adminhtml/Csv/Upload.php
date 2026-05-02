<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Csv;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::csv_upload';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('MageClone_OrderReplicator::csv');
        $page->getConfig()->getTitle()->prepend(__('CSV Bulk Order Replication'));
        return $page;
    }
}
