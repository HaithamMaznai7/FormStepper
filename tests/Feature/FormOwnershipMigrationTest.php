<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('upgrades legacy ownership while preserving form state and tenant data', function () {
    config()->set('form-stepper.tables.forms', 'legacy_forms');
    Schema::create('legacy_forms', function (Blueprint $table) {
        $table->id();
        $table->nullableMorphs('creator');
        $table->nullableMorphs('requester');
        $table->nullableMorphs('tenant');
        $table->string('current_step')->nullable();
        $table->enum('status', ['draft', 'completed'])->default('draft');
    });
    DB::table('legacy_forms')->insert([
        'creator_type' => 'old-user',
        'creator_id' => 1,
        'requester_type' => 'user',
        'requester_id' => 2,
        'tenant_type' => 'team',
        'tenant_id' => 3,
        'current_step' => 'review',
        'status' => 'completed',
    ]);
    $migration = require __DIR__.'/../../database/migrations/2026_10_06_000000_update_form_ownership.php';
    $migration->up();
    $form = DB::table('legacy_forms')->first();

    expect(Schema::hasColumn('legacy_forms', 'creator_type'))->toBeFalse()
        ->and(Schema::hasColumn('legacy_forms', 'creator_id'))->toBeFalse()
        ->and(Schema::hasColumn('legacy_forms', 'current_step'))->toBeFalse()
        ->and($form->current_step_id)->toBe('review')
        ->and($form->status)->toBe('submitted')
        ->and($form->requester_id)->toBe(2)
        ->and($form->tenant_id)->toBe(3);

    $migration->up();
    expect(DB::table('legacy_forms')->count())->toBe(1)
        ->and(fn () => $migration->down())->toThrow(RuntimeException::class);
});

it('upgrades fresh personal forms to support tenants without losing data', function () {
    $initial = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $upgrade = require __DIR__.'/../../database/migrations/2026_10_06_000000_update_form_ownership.php';
    config()->set('form-stepper.tenant.enabled', false);
    $initial->up();
    $upgrade->up();
    expect(Schema::hasColumn('forms', 'tenant_id'))->toBeFalse();

    config()->set('form-stepper.tenant.enabled', true);
    $upgrade->up();
    expect(Schema::hasColumn('forms', 'tenant_type'))->toBeTrue()
        ->and(Schema::hasColumn('forms', 'tenant_id'))->toBeTrue();
});

it('rejects conflicting step columns before modifying the table', function () {
    Schema::create('forms', function (Blueprint $table) {
        $table->id();
        $table->string('current_step')->nullable();
        $table->string('current_step_id')->nullable();
        $table->string('status')->default('draft');
        $table->nullableMorphs('creator');
    });
    $upgrade = require __DIR__.'/../../database/migrations/2026_10_06_000000_update_form_ownership.php';
    expect(fn () => $upgrade->up())->toThrow(RuntimeException::class)
        ->and(Schema::hasColumn('forms', 'creator_id'))->toBeTrue();
});

it('removes a constrained legacy creator without deleting its requester', function () {
    Schema::create('old_users', function (Blueprint $table) {
        $table->id();
    });
    Schema::create('forms', function (Blueprint $table) {
        $table->id();
        $table->foreignId('creator_id')->nullable()->constrained('old_users');
        $table->nullableMorphs('requester');
        $table->string('current_step')->nullable();
        $table->string('status')->default('draft');
    });
    DB::table('old_users')->insert(['id' => 1]);
    DB::table('forms')->insert([
        'creator_id' => 1,
        'requester_type' => 'user',
        'requester_id' => 1,
        'status' => 'draft',
    ]);
    $upgrade = require __DIR__.'/../../database/migrations/2026_10_06_000000_update_form_ownership.php';
    $upgrade->up();

    expect(Schema::hasColumn('forms', 'creator_id'))->toBeFalse()
        ->and(Schema::getForeignKeys('forms'))->toBe([])
        ->and(DB::table('forms')->value('requester_id'))->toBe(1)
        ->and(DB::table('old_users')->count())->toBe(1);
});
