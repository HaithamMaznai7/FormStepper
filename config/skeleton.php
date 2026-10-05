<?php

declare(strict_types=1);

return [

    'placeholder' => 'default',

    'default_mode' => 'single',
    'allow_guest' => true,
    'allow_instance_mode_override' => false,

    'tables' => [
        'forms' => 'forms',
        'options' => 'form_options',
        'steps' => 'form_steps',
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'api/forms',
        'middleware' => ['api'],
        'name' => 'skeleton.forms.',
    ],

    'builders' => [],

];
