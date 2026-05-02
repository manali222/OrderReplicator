<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Block\Adminhtml\Csv;

use Magento\Backend\Block\Template;

class Upload extends Template
{
    protected $_template = 'MageClone_OrderReplicator::csv/upload.phtml';

    public function getProcessUrl(): string
    {
        return $this->getUrl('orderreplicator/csv/process');
    }

    public function getDownloadTemplateUrl(): string
    {
        return $this->getUrl('orderreplicator/csv/downloadtemplate');
    }
}
