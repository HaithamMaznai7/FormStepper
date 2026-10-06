<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Http\Resources\RequirementResource;
use HaithamMaznai\FormStepper\Http\Resources\StepResource;
use HaithamMaznai\FormStepper\Services\FormService;
use Illuminate\Http\Request;

class ResourceTestBuilder extends FormBuilder
{
    public function formType(): string
    {
        return 'resource-application';
    }

    public function mode(): string
    {
        return 'stepper';
    }

    public function steps(): array
    {
        return [
            [
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string'], 'value' => 'Guest'],
                    [
                        'key' => 'address',
                        'type' => 'complex',
                        'rules' => ['array'],
                        'children' => [
                            ['key' => 'city', 'type' => 'input', 'rules' => ['required', 'string']],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'vehicles',
                'repeatable' => true,
                'repeat-name' => 'cars',
                'repeatable-scope' => ['types' => ['resource-application']],
                'requirements' => [
                    ['key' => 'plate', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ],
        ];
    }
}

class CustomStepResource extends StepResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'custom' => true];
    }
}

class CustomRequirementResource extends RequirementResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['key' => $this->definition()['key'], 'saved' => $this->value()];
    }
}

beforeEach(function () {
    $migration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $migration->up();
});

it('returns steps and requirements with rules and null values before anything is saved', function () {
    $result = app(FormService::class)->create(new ResourceTestBuilder, [], null, null)['result']->toArray();
    $applicant = $result['definition']['steps'][0];

    expect($applicant['key'])->toBe('applicant')
        ->and($applicant['values'])->toBeNull()
        ->and($applicant['rules'])->toBe([
            'name' => ['required', 'string'],
            'address' => ['array'],
            'address.city' => ['required', 'string'],
        ])
        ->and($applicant['requirements'][0])->toMatchArray([
            'key' => 'name',
            'rules' => ['required', 'string'],
            'default' => 'Guest',
            'value' => null,
        ])
        ->and($applicant['requirements'][1]['children'][0])->toMatchArray([
            'key' => 'city',
            'value' => null,
        ])
        ->and($result['definition']['steps'][1]['repeats'])->toBe([])
        ->and($result['definition']['steps'][2]['key'])->toBe('review');
});

it('returns saved values on steps, complex children, and repeatable instances', function () {
    $service = app(FormService::class);
    $form = $service->create(new ResourceTestBuilder, [], null, null)['form'];
    $service->saveStep($form, 'applicant', ['name' => 'Ada', 'address' => ['city' => 'Riyadh']]);
    $result = $service->saveStep($form, 'vehicles', ['cars' => [['plate' => 'ABC'], ['plate' => 'XYZ']]])->toArray();

    [$applicant, $vehicles] = $result['definition']['steps'];

    expect($applicant['values'])->toBe(['name' => 'Ada', 'address' => ['city' => 'Riyadh']])
        ->and($applicant['requirements'][0]['value'])->toBe('Ada')
        ->and($applicant['requirements'][1]['children'][0]['value'])->toBe('Riyadh')
        ->and($vehicles['rules'])->toBe(['cars.*.plate' => ['required', 'string']])
        ->and($vehicles['repeats'])->toHaveCount(2)
        ->and($vehicles['repeats'][1])->toMatchArray([
            'index' => 1,
            'rules' => ['plate' => ['required', 'string']],
            'values' => ['plate' => 'XYZ'],
        ])
        ->and($vehicles['repeats'][1]['requirements'][0]['value'])->toBe('XYZ');
});

it('uses custom resources selected in config', function () {
    config()->set('form-stepper.resources.step', CustomStepResource::class);
    config()->set('form-stepper.resources.requirement', CustomRequirementResource::class);

    $result = app(FormService::class)->create(new ResourceTestBuilder, [], null, null)['result']->toArray();
    $applicant = $result['definition']['steps'][0];

    expect($applicant['custom'])->toBeTrue()
        ->and($applicant['requirements'][0])->toBe(['key' => 'name', 'saved' => null]);
});

it('rejects configured resources that are not JSON resources', function () {
    config()->set('form-stepper.resources.step', stdClass::class);

    app(FormService::class)->create(new ResourceTestBuilder, [], null, null)['result']->toArray();
})->throws(InvalidArgumentException::class, 'must be a JsonResource class');
