<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $creatorModel = new (config('form-stepper.creator.model'))();

        Schema::create(config('form-stepper.tables.form_items_table_name', 'form_items'), function (Blueprint $table) {
          $table->id();
          $table->foreignId(Str::singular(config('form-stepper.tables.forms_table_name', 'forms') . '_id'))
              ->nullable()
              ->constrained(config('form-stepper.tables.forms_table_name', 'forms'), 'id')
              ->onUpdate('cascade')
              ->onDelete('cascade');
          $table->morphs(config('form-stepper.tables.form_items_table_addable', 'addable'));
          $table->integer('qty')->default(1);
      });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('form-stepper.tables.form_items_table_name', 'form_items'));
    }
};
