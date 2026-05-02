<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Csv;

use MageClone\OrderReplicator\Model\CsvProcessor;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class DownloadTemplate extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::csv_upload';

    public function __construct(
        Context $context,
        private readonly CsvProcessor $csvProcessor,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $content = $this->csvProcessor->generateTemplate();

        return $this->fileFactory->create(
            'order_replicator_template.csv',
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
