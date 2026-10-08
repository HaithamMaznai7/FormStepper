<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('form-stepper.tables.step_templates', 'form_steps_library');

        if (! Schema::hasTable($tableName)) {
            throw new RuntimeException('Create the form library tables before adding step priority.');
        }

        if (! Schema::hasColumn($tableName, 'priority')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->integer('priority')->default(100);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Step priority upgrades cannot be rolled back without losing configured ordering.');
    }
};
