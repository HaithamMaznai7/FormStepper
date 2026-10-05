<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $creatorModel = new (config('form-stepper.creator.model'))();

        Schema::create(config('form-stepper.tables.forms_table_name', 'forms'), function (Blueprint $table) use ($creatorModel) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId(config('form-stepper.creator.foreignKey', 'creator_id'))
                ->nullable()
                ->constrained($creatorModel->getTable(), config('form-stepper.creator.ownerKey', $creatorModel->getkeyName()))
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->nullableMorphs('tenant');
            $table->nullableMorphs('requester');
            $table->json('data');
            $table->string('current_step')->nullable()->default(null);

            if(config('form-stepper.types-enum', null) !== null && class_exists(config('form-stepper.types-enum', null))){
                $typesEnumClass = config('form-stepper.types-enum', null);
                $types = collect($typesEnumClass::cases())->map(fn ($case) => $case->value)->toArray();
            }elseif (is_array(config('form-stepper.types-enum', ['b2c', 'b2b']))) {
                $types = config('form-stepper.types', ['b2c', 'b2b']);
                $table->enum('type', $types)->default($types[0]);
            }else{
                $table->string('type')->nullable()->default(null);
            }

            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('form-stepper.tables.forms_table_name', 'forms'));
    }
};
