<?php

declare(strict_types=1);

namespace Omikron\Factfinder\Model\Export;

use InvalidArgumentException;
use Magento\Framework\ObjectManagerInterface;
use Omikron\Factfinder\Api\Export\FieldProviderInterface;

class FeedFactory
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var string[] */
    private $feedPool;

    public function __construct(
        ObjectManagerInterface $objectManager,
        array $feedPool
    ) {
        $this->objectManager = $objectManager;
        $this->feedPool      = $feedPool;
    }

    public function create(string $type, array $data = []): Feed
    {
        if (!isset($this->feedPool[$type])) {
            throw new InvalidArgumentException(sprintf('There is no feed configuration for the given type: %s', $type));
        }

        $fieldProvider = $this->feedPool[$type]['fieldProvider'];
        $fields = is_array($fieldProvider)
            ? $fieldProvider
            : call_user_func(function(FieldProviderInterface $fieldProvider) {
                return $fieldProvider->getFields() + $fieldProvider->getVariantFields();
            }, $this->objectManager->create($fieldProvider));

        $dataProvider = $this->objectManager->create($this->feedPool[$type]['dataProvider'], ['fields' => $fields, 'data' => $data]);

        return $this->objectManager->create($this->feedPool[$type]['generator'], ['dataProvider' => $dataProvider, 'fields' => $fields]); // phpcs:ignore
    }
}
