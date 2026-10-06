<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('form-stepper.tables.forms', 'forms');

        if (! Schema::hasTable($tableName)) {
            throw new RuntimeException('The forms table must exist before upgrading form ownership.');
        }

        $hasOldStep = Schema::hasColumn($tableName, 'current_step');
        $hasNewStep = Schema::hasColumn($tableName, 'current_step_id');

        if ($hasOldStep && $hasNewStep) {
            throw new RuntimeException('Both step columns exist. Reconcile them before upgrading form ownership.');
        }

        if (! $hasOldStep && ! $hasNewStep) {
            throw new RuntimeException('The forms table has no supported current step column.');
        }

        $unsupportedStatus = DB::table($tableName)->whereNotIn('status', ['draft', 'completed', 'submitted'])->exists();

        if ($unsupportedStatus) {
            throw new RuntimeException('The forms table contains unsupported statuses. Reconcile them before upgrading.');
        }

        $hasTenantType = Schema::hasColumn($tableName, 'tenant_type');
        $hasTenantId = Schema::hasColumn($tableName, 'tenant_id');

        if ($hasTenantType !== $hasTenantId) {
            throw new RuntimeException('Tenant identity columns must both exist or both be absent.');
        }

        if ($hasOldStep) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->renameColumn('current_step', 'current_step_id');
            });
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('status')->default('draft')->change();
        });
        DB::table($tableName)->where('status', 'completed')->update(['status' => 'submitted']);
        Schema::table($tableName, function (Blueprint $table): void {
            $table->enum('status', ['draft', 'submitted'])->default('draft')->change();
        });

        $creatorColumns = [];

        foreach (['creator_type', 'creator_id'] as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $creatorColumns[] = $column;
            }
        }

        if ($creatorColumns !== []) {
            foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                if (array_intersect($creatorColumns, $foreignKey['columns']) !== []) {
                    Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                        $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
                    });
                }
            }

            foreach (Schema::getIndexes($tableName) as $index) {
                if (array_intersect($creatorColumns, $index['columns']) !== []) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $table->dropIndex($index['name']);
                    });
                }
            }

            Schema::table($tableName, function (Blueprint $table) use ($creatorColumns): void {
                $table->dropColumn($creatorColumns);
            });
        }

        if (config('form-stepper.tenant.enabled', false) && ! $hasTenantType) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('tenant_type')->nullable();
                $table->string('tenant_id')->nullable();
                $table->index(['tenant_type', 'tenant_id']);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Form ownership cannot be rolled back without restoring deleted creator data from backup.');
    }
};
