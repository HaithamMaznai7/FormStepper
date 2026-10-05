<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use VendorName\Skeleton\Contracts\ProvidesAvailableTypes;
use VendorName\Skeleton\Contracts\ProvidesFormRequirements;
use VendorName\Skeleton\Forms\FormBuilder;
use VendorName\Skeleton\Support\SchemaAssembler;

it('merges compatible inputs from selected options and builder steps', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'b2b';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'vehicle-info',
                'title' => 'Vehicle',
                'requirements' => [
                    ['key' => 'vin', 'type' => 'input', 'rules' => ['required']],
                ],
            ]];
        }
    };

    $option = new class extends Model implements ProvidesFormRequirements
    {
        public function formOptionKey(): string
        {
            return 'vehicle';
        }

        public function formSteps(): array
        {
            return [[
                'key' => 'vehicle-info',
                'title' => 'Vehicle',
                'requirements' => [
                    ['key' => 'vin', 'type' => 'input', 'rules' => ['string']],
                ],
            ]];
        }

        public function requires(): array
        {
            return [];
        }

        public function compatibleWith(): array
        {
            return [];
        }

        public function excludes(): array
        {
            return [];
        }
    };

    $definition = app(SchemaAssembler::class)->assemble($builder, [$option], null, null, true);

    expect($definition->type)->toBe('b2b')
        ->and($definition->mode)->toBe('stepper')
        ->and($definition->steps[0]['requirements'][0]['rules'])->toBe(['required', 'string']);
});

it('filters a form type that the requester or tenant does not provide', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'b2b';
        }

        public function steps(): array
        {
            return [[
                'key' => 'billing',
                'types-scope' => ['b2b'],
                'requirements' => [],
            ]];
        }
    };

    $requester = new class extends Model implements ProvidesAvailableTypes
    {
        public function getAvailableTypes(): array
        {
            return ['b2c'];
        }
    };

    $definition = app(SchemaAssembler::class)->assemble($builder, [], $requester, null, false);

    expect($definition->steps)->toBe([]);
});

it('rejects conflicting definitions for a shared input key', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'b2b';
        }

        public function steps(): array
        {
            return [[
                'key' => 'vehicle-info',
                'requirements' => [
                    ['key' => 'vin', 'type' => 'input'],
                ],
            ]];
        }
    };

    $option = new class extends Model implements ProvidesFormRequirements
    {
        public function formOptionKey(): string
        {
            return 'vehicle';
        }

        public function formSteps(): array
        {
            return [[
                'key' => 'vehicle-info',
                'requirements' => [
                    ['key' => 'vin', 'type' => 'selection'],
                ],
            ]];
        }

        public function requires(): array
        {
            return [];
        }

        public function compatibleWith(): array
        {
            return [];
        }

        public function excludes(): array
        {
            return [];
        }
    };

    app(SchemaAssembler::class)->assemble($builder, [$option], null, null, true);
})->throws(InvalidArgumentException::class, 'Conflicting definitions for input [vin]');
