<?php

declare(strict_types=1);

namespace Omikron\Factfinder\Model\Export\Catalog\Entity;

use Magento\Catalog\Model\Product;
use Omikron\Factfinder\Api\Export\ExportEntityInterface;
use Omikron\Factfinder\Api\Export\FieldInterface;
use Omikron\Factfinder\Model\Export\Catalog\FieldProvider;
use Omikron\Factfinder\Model\Formatter\NumberFormatter;

class ProductVariation implements ExportEntityInterface
{
    /** @var Product */
    private $product;

    /** @var Product */
    private $configurable;

    /** @var NumberFormatter */
    private $numberFormatter;

    /** @var array */
    private $configurableData;

    /** @var FieldProvider */
    private $fieldprovider;

    public function __construct(
        Product $product,
        Product $configurable,
        NumberFormatter $numberFormatter,
        FieldProvider $variantFieldProvider,
        array $data = []
    ) {
        $this->product          = $product;
        $this->configurable     = $configurable;
        $this->numberFormatter  = $numberFormatter;
        $this->configurableData = $data;
        $this->fieldprovider    = $variantFieldProvider;
    }

    public function getId(): int
    {
        return (int) $this->product->getId();
    }

    public function toArray(): array
    {
        $baseData = [
                'ProductNumber' => (string) $this->product->getSku(),
                'Price'         => $this->numberFormatter->format((float) $this->product->getFinalPrice()),
                'Availability'  => (int) $this->product->isAvailable(),
                'HasVariants'   => 0,
                'MagentoId'     => $this->getId(),
            ] + $this->configurableData;

        list($filterAttributes, $restFields) = $this->extractFilterAttributes($this->fieldprovider->getVariantFields());
        $parentAttributes        = $this->configurableData['FilterAttributes'] ?? '';
        $variantAttributes       = $filterAttributes ? $filterAttributes->getValue($this->product) : '';
        $splicedFilterAttributes = str_replace('||', '|', $parentAttributes . $variantAttributes);

        return ($splicedFilterAttributes ? ['FilterAttributes' => $splicedFilterAttributes] : [])
            + array_reduce($restFields, function (array $result, FieldInterface $field): array {
                return [$field->getName() => $field->getValue($this->product)] + $result;
            }, $baseData);
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getConfigurable(): Product
    {
        return $this->configurable;
    }

    private function extractFilterAttributes(array $fields): array
    {
        $withoutFilterAttributes = array_filter($fields, function (FieldInterface $field) {
            return $field->getName() !== 'FilterAttributes';
        });

        $filterAttributes = $fields['FilterAttributes'] ?? [];

        return [$filterAttributes, $withoutFilterAttributes];
    }
}
