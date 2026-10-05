<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'types' => [
        'b2c',
        'b2b',
    ],
    'default_type' => 'b2c',
    'default_step' => null,
    'last_step' => 'checkout',
    'creator' => [
        'model' => "App\Models\User",
        'foreignKey' => 'creator_id',
        'ownerKey' => 'id'
    ],
    'saleables' => [
    ],
];
