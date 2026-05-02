<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ReplicationLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mageclone_replication_log', 'log_id');
    }
}
