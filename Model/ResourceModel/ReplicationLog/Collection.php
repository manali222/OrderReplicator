<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model\ResourceModel\ReplicationLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageClone\OrderReplicator\Model\ReplicationLog;
use MageClone\OrderReplicator\Model\ResourceModel\ReplicationLog as ReplicationLogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(ReplicationLog::class, ReplicationLogResource::class);
    }
}
