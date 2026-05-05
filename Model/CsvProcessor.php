<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Model;

use MageClone\OrderReplicator\Api\OrderReplicatorInterface;
use MageClone\OrderReplicator\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;

class CsvProcessor
{
    private const REQUIRED_COLUMNS = [
        'customer_email',
        'customer_firstname',
        'customer_lastname',
    ];

    private const ADDRESS_COLUMNS = [
        'billing_street',
        'billing_city',
        'billing_region',
        'billing_region_id',
        'billing_postcode',
        'billing_country_id',
        'billing_telephone',
        'shipping_street',
        'shipping_city',
        'shipping_region',
        'shipping_region_id',
        'shipping_postcode',
        'shipping_country_id',
        'shipping_telephone',
        'shipping_method',
        'payment_method',
    ];

    private const ITEM_COLUMNS = [
        'override_sku',
        'override_price',
        'override_qty',
        'item_modifications',
    ];

    public function __construct(
        private readonly OrderReplicatorInterface $orderReplicator,
        private readonly Csv $csvReader,
        private readonly Filesystem $filesystem,
        private readonly Config $config,
        private readonly ReplicationLogFactory $replicationLogFactory,
        private readonly ResourceModel\ReplicationLog $replicationLogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate and process uploaded CSV file
     *
     * @param string $filePath Absolute path to uploaded CSV
     * @param int $sourceOrderId Order to replicate from
     * @return array{success: int, failed: int, errors: array}
     * @throws LocalizedException
     */
    public function process(string $filePath, int $sourceOrderId): array
    {
        $delimiter = $this->config->getCsvDelimiter();
        $this->csvReader->setDelimiter($delimiter);

        $data = $this->csvReader->getData($filePath);
        if (empty($data)) {
            throw new LocalizedException(__('CSV file is empty.'));
        }

        // First row is headers
        $headers = array_map('trim', array_map('strtolower', $data[0]));
        $this->validateHeaders($headers);

        $maxRows = $this->config->getMaxCsvRows();
        $dataRows = array_slice($data, 1);

        if (count($dataRows) > $maxRows) {
            throw new LocalizedException(
                __('CSV contains %1 rows, maximum allowed is %2.', count($dataRows), $maxRows)
            );
        }

        $results = ['success' => 0, 'failed' => 0, 'errors' => [], 'orders' => []];

        foreach ($dataRows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +2 because 1-indexed and header row

            if (count($row) !== count($headers)) {
                $results['failed']++;
                $results['errors'][] = sprintf('Row %d: Column count mismatch.', $rowNumber);
                continue;
            }

            $csvRow = array_combine($headers, $row);
            if (empty($csvRow['customer_email'])) {
                $results['failed']++;
                $results['errors'][] = sprintf('Row %d: Missing customer_email.', $rowNumber);
                continue;
            }

            try {
                $newOrder = $this->orderReplicator->replicateFromCsvRow($sourceOrderId, $csvRow);
                $results['success']++;
                $results['orders'][] = [
                    'row' => $rowNumber,
                    'entity_id' => $newOrder->getEntityId(),
                    'order_id' => $newOrder->getIncrementId(),
                    'email' => $csvRow['customer_email'],
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    'Row %d (%s): %s',
                    $rowNumber,
                    $csvRow['customer_email'],
                    $e->getMessage()
                );
                $this->logger->error(sprintf(
                    'OrderReplicator CSV Row %d failed: %s',
                    $rowNumber,
                    $e->getMessage()
                ));
            }
        }

        return $results;
    }

    /**
     * Validate that CSV has required columns
     */
    private function validateHeaders(array $headers): void
    {
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $headers, true)) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            throw new LocalizedException(
                __('CSV is missing required columns: %1', implode(', ', $missing))
            );
        }
    }

    /**
     * Generate a sample CSV template
     */
    public function generateTemplate(): string
    {
        $columns = array_merge(
            self::REQUIRED_COLUMNS,
            self::ADDRESS_COLUMNS,
            self::ITEM_COLUMNS
        );

        $sampleRow = [
            'john@example.com',          // customer_email
            'John',                       // customer_firstname
            'Doe',                        // customer_lastname
            '123 Main St',               // billing_street
            'New York',                  // billing_city
            'New York',                  // billing_region
            '43',                        // billing_region_id
            '10001',                     // billing_postcode
            'US',                        // billing_country_id
            '555-123-4567',              // billing_telephone
            '456 Oak Ave',              // shipping_street
            'Los Angeles',              // shipping_city
            'California',               // shipping_region
            '12',                       // shipping_region_id
            '90001',                    // shipping_postcode
            'US',                       // shipping_country_id
            '555-987-6543',             // shipping_telephone
            'flatrate_flatrate',        // shipping_method
            'checkmo',                  // payment_method
            '',                         // override_sku (pipe-separated, leave empty to use source)
            '',                         // override_price (pipe-separated, leave empty to use source)
            '',                         // override_qty (pipe-separated, leave empty to use source)
            '',                         // item_modifications (JSON alternative)
        ];

        $lines = [
            implode(',', $columns),
            implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', $val) . '"';
            }, $sampleRow)),
        ];

        return implode("\n", $lines);
    }
}
