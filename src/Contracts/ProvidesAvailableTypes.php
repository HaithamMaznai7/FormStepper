<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Contracts;

interface ProvidesAvailableTypes
{
    /**
     * @return list<string>
     */
    public function getAvailableTypes(): array;
}
