<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Contracts;

interface ProvidesFormRequirements
{
    public function formOptionKey(): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function formSteps(): array;

    /**
     * @return list<string>
     */
    public function requires(): array;

    /**
     * @return list<string>
     */
    public function compatibleWith(): array;

    /**
     * @return list<string>
     */
    public function excludes(): array;
}
