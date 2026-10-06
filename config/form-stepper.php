<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'placeholder' => 'default',
    'default_mode' => 'stepper', // 'single' or 'stepper'
    'allow_guest' => true,
    'allow_instance_mode_override' => false,
    'tenant' => [
        'enabled' => false,
        'relationship' => 'currentTenant',
    ],
    'tables' => [
        'forms' => 'forms',
        'options' => 'form_options',
        'steps' => 'form_steps',
    ],
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/forms',
        'middleware' => ['api'],
        'name' => 'form-stepper.forms.',
    ],
    'builders' => [],
    'types' => [
        'b2c',
        'b2b',
        'b2partner',
    ],
    'default_type' => 'b2c',
    'default_step' => null,
    'last_step' => 'checkout',
    'saleables' => [
    ],
];
