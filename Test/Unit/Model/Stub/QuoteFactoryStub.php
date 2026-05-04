<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Model\Stub;

use Magento\Quote\Model\Quote;

/**
 * Stub for auto-generated QuoteFactory so PHPUnit can mock it.
 */
class QuoteFactoryStub
{
    public function create(array $data = []): Quote
    {
        throw new \RuntimeException('Stub — should be mocked');
    }
}
