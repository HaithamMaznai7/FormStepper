<?php

declare(strict_types=1);

namespace VendorName\Skeleton\Contracts;

interface ProvidesAvailableTypes
{
    /**
     * @return list<string>
     */
    public function getAvailableTypes(): array;
}
