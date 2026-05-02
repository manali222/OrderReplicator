<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model;

use Magento\Framework\Model\AbstractModel;

class ReplicationLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\ReplicationLog::class);
    }
}
