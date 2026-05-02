<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Controller\Adminhtml\Csv;

use MageClone\OrderReplicator\Helper\Config;
use MageClone\OrderReplicator\Model\CsvProcessor;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;

class Process extends Action
{
    public const ADMIN_RESOURCE = 'MageClone_OrderReplicator::csv_upload';

    public function __construct(
        Context $context,
        private readonly CsvProcessor $csvProcessor,
        private readonly Config $config,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Order Replicator module is disabled.')
            ]);
        }

        $sourceOrderId = (int) $this->getRequest()->getParam('source_order_id');
        if (!$sourceOrderId) {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter a source order ID.')
            ]);
        }

        try {
            // Handle file upload
            $uploader = $this->uploaderFactory->create(['fileId' => 'csv_file']);
            $uploader->setAllowedExtensions(['csv']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $targetPath = $mediaDir->getAbsolutePath('orderreplicator/csv/');

            $uploadResult = $uploader->save($targetPath);

            if (!$uploadResult || !isset($uploadResult['file'])) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Failed to upload CSV file.')
                ]);
            }

            $filePath = $targetPath . $uploadResult['file'];

            // Process the CSV
            $processResult = $this->csvProcessor->process($filePath, $sourceOrderId);

            // Clean up uploaded file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return $result->setData([
                'success' => true,
                'message' => __(
                    'CSV processed: %1 orders created successfully, %2 failed.',
                    $processResult['success'],
                    $processResult['failed']
                ),
                'details' => $processResult
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
