<?php

declare(strict_types=1);

use FormStepper\FormStepper\Support\SchemaNormalizer;

it('normalizes legacy scope keys and treats null scopes as unrestricted', function () {
    $steps = app(SchemaNormalizer::class)->normalize([
        [
            'key' => 'vehicle-info',
            'types-scope' => null,
            'scope' => ['types' => ['internal-only']],
            'repeatable' => true,
            'repeatable-types-scope' => ['b2b', 'partner'],
            'requirements' => [
                [
                    'key' => 'vin',
                    'type' => 'input',
                    'rules' => ['required', 'string'],
                ],
                [
                    'key' => 'cr',
                    'type' => 'input',
                    'business-types' => ['b2b'],
                    'requester-scope' => null,
                    'rules' => ['required'],
                ],
            ],
        ],
    ]);

    expect($steps[0]['scope']['types'])->toBe(['*'])
        ->and($steps[0]['repeatable_scope']['types'])->toBe(['b2b', 'partner'])
        ->and($steps[0]['requirements'][0]['scope']['requester'])->toBe(['*'])
        ->and($steps[0]['requirements'][1]['scope']['requester'])->toBe(['*']);
});

it('rejects unsupported input kinds', function () {
    app(SchemaNormalizer::class)->normalize([
        [
            'key' => 'vehicle-info',
            'requirements' => [
                ['key' => 'unknown', 'type' => 'unsupported'],
            ],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported input type');
