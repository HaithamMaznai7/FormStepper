<?php

declare(strict_types=1);

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use HaithamMaznai\FormStepper\Support\OwnershipStatusType;
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

        $legacySqlite = version_compare(app()->version(), '11.0', '<')
            && DB::connection()->getDriverName() === 'sqlite';

        if ($legacySqlite && ! class_exists(AbstractSchemaManager::class)) {
            throw new RuntimeException('Install doctrine/dbal:^3.9 before upgrading ownership on Laravel 10 with SQLite.');
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

        $creatorColumns = [];

        foreach (['creator_type', 'creator_id'] as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $creatorColumns[] = $column;
            }
        }

        if ($legacySqlite) {
            $this->upgradeLegacySqlite($tableName, $creatorColumns);
        } elseif ($creatorColumns !== []) {
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

        if (! $legacySqlite) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->enum('status', ['draft', 'submitted'])->default('draft')->change();
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

    /** @param list<string> $columns */
    private function upgradeLegacySqlite(string $tableName, array $columns): void
    {
        $connection = DB::connection();

        if (! method_exists($connection, 'getDoctrineSchemaManager')) {
            throw new RuntimeException('Laravel 10 SQLite ownership upgrades require the Doctrine schema manager.');
        }

        /** @var AbstractSchemaManager<AbstractPlatform> $manager */
        $manager = $connection->getDoctrineSchemaManager();
        $original = $manager->introspectTable($connection->getTablePrefix().$tableName);
        $updated = clone $original;

        foreach ($updated->getForeignKeys() as $name => $foreignKey) {
            if (array_intersect($columns, $foreignKey->getLocalColumns()) !== []) {
                $updated->removeForeignKey($name);
            }
        }

        foreach ($updated->getIndexes() as $index) {
            if (array_intersect($columns, $index->getColumns()) !== []) {
                $updated->dropIndex($index->getName());
            }
        }

        foreach ($columns as $column) {
            $updated->dropColumn($column);
        }

        $updated->modifyColumn('status', ['type' => new OwnershipStatusType, 'default' => 'draft']);
        $diff = $manager->createComparator()->compareTables($original, $updated);

        foreach ($manager->getDatabasePlatform()->getAlterTableSQL($diff) as $sql) {
            $connection->statement($sql);
        }
    }
};
