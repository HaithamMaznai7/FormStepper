<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Support\InputTypeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $typesTable = config('form-stepper.tables.input_types', 'form_input_types');
        $inputsTable = config('form-stepper.tables.inputs', 'form_inputs');
        $childrenTable = config('form-stepper.tables.input_children', 'form_input_children');
        $stepsTable = config('form-stepper.tables.step_templates', 'form_steps_library');
        $stepInputsTable = config('form-stepper.tables.step_template_inputs', 'form_steps_library_inputs');

        Schema::create($typesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('has_children')->default(false);
            $table->boolean('has_options')->default(false);
            $table->timestamps();
        });

        Schema::create($inputsTable, function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->string('type');
            $table->json('rules')->nullable();
            $table->string('placeholder')->nullable();
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->index('type');
        });

        Schema::create($childrenTable, function (Blueprint $table) use ($inputsTable): void {
            $table->id();
            $table->foreignId('parent_id')->constrained($inputsTable)->cascadeOnDelete();
            $table->foreignId('child_id')->constrained($inputsTable)->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unique(['parent_id', 'child_id']);
        });

        Schema::create($stepsTable, function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->boolean('repeatable')->default(false);
            $table->string('repeat_name')->nullable();
            $table->boolean('requires_authentication')->default(false);
            $table->timestamps();
        });

        Schema::create($stepInputsTable, function (Blueprint $table) use ($stepsTable, $inputsTable): void {
            $table->id();
            $table->foreignId('step_id')->constrained($stepsTable)->cascadeOnDelete();
            $table->foreignId('input_id')->constrained($inputsTable)->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unique(['step_id', 'input_id']);
        });

        $now = now();

        DB::table($typesTable)->insert(array_map(
            static fn (array $type): array => [
                ...$type,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            InputTypeRegistry::SYSTEM_TYPES,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists(config('form-stepper.tables.step_template_inputs', 'form_steps_library_inputs'));
        Schema::dropIfExists(config('form-stepper.tables.step_templates', 'form_steps_library'));
        Schema::dropIfExists(config('form-stepper.tables.input_children', 'form_input_children'));
        Schema::dropIfExists(config('form-stepper.tables.inputs', 'form_inputs'));
        Schema::dropIfExists(config('form-stepper.tables.input_types', 'form_input_types'));
    }
};
