<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Contracts\ProvidesFormRequirements;
use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Schema\Input;
use HaithamMaznai\FormStepper\Schema\Requirements;
use HaithamMaznai\FormStepper\Schema\Step;
use HaithamMaznai\FormStepper\Services\FormService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SnapshotOption extends Model implements ProvidesFormRequirements
{
    protected $table = 'snapshot_options';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['requirements' => 'array'];
    }

    public function formOptionKey(): string
    {
        return $this->getAttribute('key');
    }

    public function formSteps(): array
    {
        return Requirements::fromArray($this->getAttribute('requirements'))->toArray();
    }

    public function requires(): array
    {
        return [];
    }

    public function compatibleWith(): array
    {
        return [];
    }

    public function excludes(): array
    {
        return [];
    }
}

class SnapshotLookupInput extends Model
{
    protected $table = 'snapshot_inputs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }
}

beforeEach(function () {
    foreach (['snapshot_options' => 'requirements', 'snapshot_inputs' => 'definition'] as $name => $column) {
        Schema::create($name, function (Blueprint $table) use ($column) {
            $table->id();
            $table->string('key')->unique();
            $table->json($column);
            $table->timestamps();
        });
    }
    $migration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $migration->up();
});

it('stores lookup snapshots and merges selected option inputs in either form mode', function (string $mode) {
    $lookup = SnapshotLookupInput::create([
        'key' => 'name',
        'definition' => Input::make('name', attributes: [
            'label' => 'Name',
            'rules' => ['required', 'string'],
        ])->toArray(),
    ]);
    $name = Input::fromArray($lookup->fresh()->definition);

    foreach (['inspection' => 'email', 'delivery' => 'phone'] as $key => $additionalInput) {
        SnapshotOption::create([
            'key' => $key,
            'requirements' => Requirements::make(Step::make('contact', [
                $name,
                Input::make($additionalInput, attributes: ['rules' => ['required', 'string']]),
            ], ['title' => 'Contact']))->toArray(),
        ]);
    }

    $lookup->update(['definition' => Input::make('name', attributes: ['label' => 'Changed'])->toArray()]);
    $builder = new class($mode) extends FormBuilder
    {
        public function __construct(private readonly string $formMode) {}

        public function formType(): string
        {
            return 'snapshot';
        }

        public function mode(): string
        {
            return $this->formMode;
        }

        public function resolveOptions(array $keys): array
        {
            return SnapshotOption::query()->whereIn('key', $keys)->orderBy('id')->get()->all();
        }
    };

    $service = app(FormService::class);
    $created = $service->create($builder, ['inspection'], null, null);
    $form = $created['form'];

    if ($mode === 'stepper') {
        $service->saveStep($form, 'contact', ['name' => 'Ada', 'email' => 'ada@example.test']);
    }

    $updated = $service->updateOptions($form, $builder, ['inspection', 'delivery'])->toArray();
    $inputs = $updated['definition']['steps'][0]['requirements'];

    expect(array_column($inputs, 'key'))->toBe(['name', 'email', 'phone'])
        ->and($inputs[0]['label'])->toBe('Name')
        ->and($inputs[0]['rules'])->toBe(['required', 'string'])
        ->and(SnapshotOption::query()->firstOrFail()->requirements)->toBeArray();

    $values = ['name' => 'Ada', 'email' => 'ada@example.test', 'phone' => '123456789'];

    if ($mode === 'stepper') {
        expect($updated['values']['contact']['name'])->toBe('Ada')
            ->and($updated['current_step_id'])->toBe('contact');
        $service->saveStep($form, 'contact', $values);
        $submitted = $service->complete($form)->toArray();
    } else {
        $submitted = $service->submitSingle($form, ['contact' => $values])->toArray();
    }

    expect($submitted['status'])->toBe('submitted')
        ->and($submitted['values']['contact'])->toBe($values);
})->with(['single', 'stepper']);
