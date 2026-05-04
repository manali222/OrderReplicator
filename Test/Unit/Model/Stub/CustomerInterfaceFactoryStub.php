<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Model\Stub;

use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Stub for auto-generated CustomerInterfaceFactory so PHPUnit can mock it.
 */
class CustomerInterfaceFactoryStub
{
    public function create(array $data = []): CustomerInterface
    {
        throw new \RuntimeException('Stub — should be mocked');
    }
}
