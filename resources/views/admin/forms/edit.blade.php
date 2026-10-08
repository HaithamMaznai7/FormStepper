@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Edit form '.$form->type)

@section('actions')
    <a href="{{ route($admin.'forms.show', $form) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">View</a>
    @include('form-stepper::admin.partials.delete', ['action' => route($admin.'forms.destroy', $form), 'confirm' => 'Delete this form and all saved values?'])
@endsection

@section('content')
    @include('form-stepper::admin.forms._summary')

    <form method="POST" action="{{ route($admin.'forms.update', $form) }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')
        <label for="options" class="block text-sm font-medium">Option keys</label>
        <div class="mt-1 flex flex-wrap gap-3">
            <input id="options" name="options" value="{{ old('options', $optionKeys) }}" dir="ltr"
                   class="block min-w-0 flex-1 rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update options</button>
        </div>
        <p class="mt-1 text-xs text-slate-500">Changing options rebuilds the definition. Values for steps that still match are kept.</p>
    </form>

    <div class="mt-8 space-y-6">
        @foreach ($steps as $step)
            @php($stepValues = $values[$step['key']] ?? [])
            <form method="POST" action="{{ route($admin.'forms.steps.update', ['form' => $form, 'stepKey' => $step['key']]) }}"
                  class="rounded-lg border border-slate-200 bg-white">
                @csrf
                @method('PUT')
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-3">
                    <h2 class="font-semibold">{{ $step['title'] ?? $step['key'] }} <span class="font-mono text-xs font-normal text-slate-500">{{ $step['key'] }}</span></h2>
                    <div class="flex items-center gap-2 text-xs">
                        @if ($form->current_step_id === $step['key'])<span class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">current</span>@endif
                        @if (! empty($step['requires_authentication']))<span class="rounded bg-sky-50 px-2 py-0.5 text-sky-700">auth</span>@endif
                        @unless (array_key_exists($step['key'], $values))<span class="text-slate-500">not saved</span>@endunless
                    </div>
                </header>
                <div class="space-y-4 p-6">
                    @if (! empty($step['repeatable']))
                        <label for="step-json-{{ $step['key'] }}" class="block text-sm font-medium">Instances of <code>{{ $step['repeat_name'] }}</code> (JSON)</label>
                        <textarea id="step-json-{{ $step['key'] }}" name="step_json" rows="8" dir="ltr"
                                  class="block w-full rounded-md border-slate-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ json_encode($stepValues === [] ? [$step['repeat_name'] => []] : $stepValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</textarea>
                        <p class="text-xs text-slate-500">Inputs per instance: {{ collect($step['requirements'] ?? [])->pluck('key')->implode(', ') }}</p>
                    @else
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($step['requirements'] ?? [] as $requirement)
                                @include('form-stepper::admin.forms._field', [
                                    'requirement' => $requirement,
                                    'value' => array_key_exists($requirement['key'], $stepValues)
                                        ? $stepValues[$requirement['key']]
                                        : ($requirement['value'] ?? null),
                                    'stepKey' => $step['key'],
                                ])
                            @endforeach
                        </div>
                    @endif
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save step</button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
@endsection
