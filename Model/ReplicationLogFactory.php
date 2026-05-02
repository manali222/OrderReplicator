<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for ReplicationLog model
 * Note: In production, Magento auto-generates factories.
 * This explicit factory is provided for IDE support and clarity.
 */
class ReplicationLogFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    public function create(array $data = []): ReplicationLog
    {
        return $this->objectManager->create(ReplicationLog::class, $data);
    }
}
