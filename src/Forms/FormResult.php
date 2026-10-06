<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Forms;

use HaithamMaznai\FormStepper\Http\Resources\FormResource;
use HaithamMaznai\FormStepper\Http\Resources\FormStepperResource;
use HaithamMaznai\FormStepper\Models\Form;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @implements Arrayable<string, mixed>
 */
class FormResult implements Arrayable
{
    public function __construct(
        private readonly Form $form,
        private readonly ?string $resumeToken = null,
    ) {}

    public function form(): Form
    {
        return $this->form;
    }

    /**
     * The configured form resource (`form-stepper.resources.form`).
     */
    public function toResource(): JsonResource
    {
        $class = FormStepperResource::resourceClass('form');
        $resource = new $class($this->form);

        if ($resource instanceof FormResource) {
            $resource->withResumeToken($this->resumeToken);
        }

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? app('request') : new Request;

        /** @var array<string, mixed> $data */
        $data = $this->plain($this->toResource()->resolve($request));

        return $data;
    }

    private function plain(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return is_array($value)
            ? array_map(fn (mixed $item): mixed => $this->plain($item), $value)
            : $value;
    }
}
