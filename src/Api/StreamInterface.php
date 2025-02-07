<?php

declare(strict_types=1);

namespace Omikron\Factfinder\Api;

/**
 * @api
 */
interface StreamInterface
{
    /**
     * @param array $entity
     */
    public function addEntity(array $entity): void;

    /**
     * @return string
     */
    public function getContent(): string;
}
