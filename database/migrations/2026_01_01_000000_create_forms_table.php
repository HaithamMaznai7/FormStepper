<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $formsTable = config('form-stepper.tables.forms', 'forms');
        $optionsTable = config('form-stepper.tables.options', 'form_options');
        $stepsTable = config('form-stepper.tables.steps', 'form_steps');

        Schema::create($formsTable, function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('mode');
            $this->nullableMorphs($table, 'requester');
            $this->nullableMorphs($table, 'creator');
            $this->nullableMorphs($table, 'tenant');
            $table->string('status')->default('draft');
            $table->string('current_step')->nullable();
            $table->json('definition');
            $table->string('resume_token_hash')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create($optionsTable, function (Blueprint $table) use ($formsTable): void {
            $table->id();
            $table->foreignId('form_id')->constrained($formsTable)->cascadeOnDelete();
            $this->morphs($table, 'option');
            $table->string('option_key');
            $table->timestamps();
            $table->unique(['form_id', 'option_type', 'option_id']);
        });

        Schema::create($stepsTable, function (Blueprint $table) use ($formsTable): void {
            $table->id();
            $table->foreignId('form_id')->constrained($formsTable)->cascadeOnDelete();
            $table->string('step_key');
            $table->json('values');
            $table->timestamp('saved_at');
            $table->timestamps();
            $table->unique(['form_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('form-stepper.tables.steps', 'form_steps'));
        Schema::dropIfExists(config('form-stepper.tables.options', 'form_options'));
        Schema::dropIfExists(config('form-stepper.tables.forms', 'forms'));
    }

    private function nullableMorphs(Blueprint $table, string $name): void
    {
        $this->morphs($table, $name, true);
    }

    private function morphs(Blueprint $table, string $name, bool $nullable = false): void
    {
        $type = $table->string($name.'_type');
        $id = $table->string($name.'_id');

        if ($nullable) {
            $type->nullable();
            $id->nullable();
        }

        $table->index([$name.'_type', $name.'_id']);
    }
};
