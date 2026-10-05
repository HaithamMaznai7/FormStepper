<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Forms;

use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FormBuilderRegistry
{
    /**
     * @var array<string, FormBuilder>
     */
    private array $builders = [];

    public function register(string $formType, FormBuilder $builder): void
    {
        if ($formType === '' || $builder->formType() !== $formType) {
            throw new InvalidArgumentException('The registered form type must match the builder type.');
        }

        $this->builders[$formType] = $builder;
    }

    public function resolve(string $formType): FormBuilder
    {
        return $this->builders[$formType]
            ?? throw new NotFoundHttpException("No form builder is registered for type [{$formType}].");
    }
}
