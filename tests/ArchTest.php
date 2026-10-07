<?php

declare(strict_types=1);
use Pest\ArchPresets\Php;

if (class_exists(Php::class)) {
    arch('PHP conventions')->preset()->php();
    arch('security conventions')->preset()->security();
} else {
    arch('legacy PHP and security conventions')
        ->expect([
            'dump', 'die', 'var_dump', 'phpinfo', 'print_r', 'var_export',
            'md5', 'sha1', 'uniqid', 'rand', 'mt_rand', 'tempnam', 'str_shuffle',
            'shuffle', 'array_rand', 'eval', 'exec', 'shell_exec', 'system',
            'passthru', 'create_function', 'unserialize', 'extract', 'mb_parse_str',
            'dl', 'assert',
        ])->not->toBeUsed();
}

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('HaithamMaznai\FormStepper')
    ->toUseStrictTypes();
