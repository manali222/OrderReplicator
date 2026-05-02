<?php
declare(strict_types=1);

namespace MageClone\OrderReplicator\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['entity_id'])) {
                    $item[$this->getData('name')] = [
                        'replicate' => [
                            'href' => $this->urlBuilder->getUrl(
                                'orderreplicator/order/view',
                                ['order_id' => $item['entity_id']]
                            ),
                            'label' => __('Replicate'),
                        ],
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                'sales/order/view',
                                ['order_id' => $item['entity_id']]
                            ),
                            'label' => __('View Original'),
                        ],
                    ];
                }
            }
        }

        return $dataSource;
    }
}
