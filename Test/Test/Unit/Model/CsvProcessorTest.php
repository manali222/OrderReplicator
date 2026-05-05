<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Test\Unit\Model;

use MageClone\OrderReplicator\Api\OrderReplicatorInterface;
use MageClone\OrderReplicator\Helper\Config;
use MageClone\OrderReplicator\Model\CsvProcessor;
use MageClone\OrderReplicator\Model\ReplicationLogFactory;
use MageClone\OrderReplicator\Model\ResourceModel\ReplicationLog as ReplicationLogResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CsvProcessorTest extends TestCase
{
    private CsvProcessor $csvProcessor;
    private OrderReplicatorInterface|MockObject $orderReplicator;
    private Csv|MockObject $csvReader;
    private Config|MockObject $config;

    protected function setUp(): void
    {
        $this->orderReplicator = $this->createMock(OrderReplicatorInterface::class);
        $this->csvReader = $this->createMock(Csv::class);
        $this->config = $this->createMock(Config::class);
        $filesystem = $this->createMock(Filesystem::class);
        $replicationLogFactory = $this->createMock(ReplicationLogFactory::class);
        $replicationLogResource = $this->createMock(ReplicationLogResource::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->csvProcessor = new CsvProcessor(
            $this->orderReplicator,
            $this->csvReader,
            $filesystem,
            $this->config,
            $replicationLogFactory,
            $replicationLogResource,
            $logger
        );
    }

    public function testProcessThrowsExceptionForEmptyCsv(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->csvReader->method('getData')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('CSV file is empty');

        $this->csvProcessor->process('/tmp/test.csv', 1);
    }

    public function testProcessThrowsExceptionForMissingRequiredColumns(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->csvReader->method('getData')->willReturn([
            ['some_column', 'another_column']
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('missing required columns');

        $this->csvProcessor->process('/tmp/test.csv', 1);
    }

    public function testProcessThrowsExceptionWhenExceedingMaxRows(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->config->method('getMaxCsvRows')->willReturn(2);

        $data = [
            ['customer_email', 'customer_firstname', 'customer_lastname'],
            ['a@test.com', 'A', 'User'],
            ['b@test.com', 'B', 'User'],
            ['c@test.com', 'C', 'User'],
        ];
        $this->csvReader->method('getData')->willReturn($data);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('maximum allowed is 2');

        $this->csvProcessor->process('/tmp/test.csv', 1);
    }

    public function testProcessSuccessfullyReplicatesOrders(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->config->method('getMaxCsvRows')->willReturn(500);

        $data = [
            ['customer_email', 'customer_firstname', 'customer_lastname'],
            ['john@test.com', 'John', 'Doe'],
            ['jane@test.com', 'Jane', 'Smith'],
        ];
        $this->csvReader->method('getData')->willReturn($data);

        $mockOrder = $this->createMock(OrderInterface::class);
        $mockOrder->method('getIncrementId')->willReturn('000000099');
        $mockOrder->method('getEntityId')->willReturn(99);

        $this->orderReplicator->method('replicateFromCsvRow')
            ->willReturn($mockOrder);

        $result = $this->csvProcessor->process('/tmp/test.csv', 1);

        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
        $this->assertCount(2, $result['orders']);
    }

    public function testProcessHandlesPartialFailures(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->config->method('getMaxCsvRows')->willReturn(500);

        $data = [
            ['customer_email', 'customer_firstname', 'customer_lastname'],
            ['john@test.com', 'John', 'Doe'],
            ['', 'No', 'Email'],  // Missing email — will fail
        ];
        $this->csvReader->method('getData')->willReturn($data);

        $mockOrder = $this->createMock(OrderInterface::class);
        $mockOrder->method('getIncrementId')->willReturn('000000099');
        $mockOrder->method('getEntityId')->willReturn(99);

        $this->orderReplicator->method('replicateFromCsvRow')
            ->willReturn($mockOrder);

        $result = $this->csvProcessor->process('/tmp/test.csv', 1);

        $this->assertEquals(1, $result['success']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
    }

    public function testProcessHandlesColumnCountMismatch(): void
    {
        $this->config->method('getCsvDelimiter')->willReturn(',');
        $this->config->method('getMaxCsvRows')->willReturn(500);

        $data = [
            ['customer_email', 'customer_firstname', 'customer_lastname'],
            ['john@test.com', 'John'],  // Missing last column
        ];
        $this->csvReader->method('getData')->willReturn($data);

        $result = $this->csvProcessor->process('/tmp/test.csv', 1);

        $this->assertEquals(0, $result['success']);
        $this->assertEquals(1, $result['failed']);
        $this->assertStringContainsString('Column count mismatch', $result['errors'][0]);
    }

    public function testGenerateTemplateReturnsValidCsv(): void
    {
        $template = $this->csvProcessor->generateTemplate();

        $this->assertStringContainsString('customer_email', $template);
        $this->assertStringContainsString('customer_firstname', $template);
        $this->assertStringContainsString('customer_lastname', $template);
        $this->assertStringContainsString('billing_street', $template);
        $this->assertStringContainsString('override_sku', $template);
        $this->assertStringContainsString('john@example.com', $template);

        // Verify it has 2 lines (header + sample)
        $lines = explode("\n", $template);
        $this->assertCount(2, $lines);
    }
}
