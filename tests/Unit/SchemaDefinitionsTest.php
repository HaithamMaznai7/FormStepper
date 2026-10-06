<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Schema\Input;
use HaithamMaznai\FormStepper\Schema\Requirements;
use HaithamMaznai\FormStepper\Schema\Step;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

it('builds arrayable steps and inputs and round trips their JSON snapshot', function () {
    $requirements = Requirements::make(
        Step::make('contact', [
            Input::make('name', attributes: ['label' => 'Name', 'rules' => ['required', 'string']]),
            Input::complex('address', [
                Input::make('city', attributes: ['rules' => ['required', 'string']]),
            ]),
        ], ['title' => 'Contact', 'requires-authentication' => true]),
    );

    expect($requirements)->toBeInstanceOf(Arrayable::class)->toBeInstanceOf(Jsonable::class)
        ->and($requirements->toArray()[0]['requirements'][1]['requirements'][0]['key'])->toBe('city')
        ->and(Requirements::fromJson($requirements->toJson())->toArray())->toBe($requirements->toArray())
        ->and(json_decode(json_encode($requirements, JSON_THROW_ON_ERROR), true))->toBe($requirements->toArray())
        ->and(Input::fromArray(['key' => 'name', 'type' => 'input'])->toJson())->toBe('{"key":"name","type":"input"}');
});

it('copies a lookup definition instead of retaining a live reference', function () {
    $lookup = ['key' => 'name', 'type' => 'input', 'label' => 'Original'];
    $input = Input::fromArray($lookup);
    $lookup['label'] = 'Changed';

    expect($input->toArray()['label'])->toBe('Original')
        ->and(Step::fromArray(['key' => 'contact', 'requirements' => [$input->toArray()]])->toArray()['key'])
        ->toBe('contact');
});

it('rejects invalid input definitions and duplicate keys before storage', function () {
    expect(fn () => Input::make('name', 'unsupported'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Step::make('contact', [Input::make('name'), Input::make('name')]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Requirements::make(Step::make('contact'), Step::make('contact')))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects malformed JSON and non-list requirements', function () {
    expect(fn () => Requirements::fromJson('{'))->toThrow(JsonException::class)
        ->and(fn () => Requirements::fromJson('{"steps":[]}'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Requirements::fromJson('null'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Requirements::fromArray(['contact' => ['key' => 'contact']]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects executable and non-JSON data rather than serializing it silently', function () {
    expect(fn () => Input::make('name', attributes: ['rules' => [fn () => true]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Input::make('name', attributes: ['extra' => ['value' => NAN]]))
        ->toThrow(JsonException::class);
});

it('supports repeatable steps, scopes and complete legacy lookup snapshots', function () {
    $input = Input::fromArray([
        'key' => 'plate',
        'type' => 'plate',
        'rules' => ['required', 'string'],
        'business-types' => ['order'],
        'extra' => ['source' => 'lookup', 'choices' => ['A', 'B']],
    ]);
    $step = Step::make('vehicles', [$input], [
        'repeatable' => true,
        'repeat-name' => 'vehicles',
        'types-scope' => ['order'],
    ]);

    expect($step->toArray()['repeat-name'])->toBe('vehicles')
        ->and($step->toArray()['requirements'][0])->toBe($input->toArray())
        ->and(Requirements::fromArray([])->toArray())->toBe([]);
});
