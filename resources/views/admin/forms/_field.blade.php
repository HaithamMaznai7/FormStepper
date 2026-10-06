{{-- One editable requirement. Expects $requirement, $value, $stepKey. --}}
@php
    $key = $requirement['key'];
    $type = $requirement['type'] ?? 'input';
    $choices = collect($requirement['extra']['options'] ?? [])
        ->map(fn ($choice) => is_array($choice) ? $choice : ['value' => $choice, 'label' => $choice])
        ->all();
    $fieldId = 'field-'.$stepKey.'-'.\Illuminate\Support\Str::slug($key);
    $multiple = in_array($type, ['multiple-selection', 'checkbox'], true);
    $useJson = $type === 'complex' || (is_array($value) && ! ($multiple && $choices !== []));
    $label = $requirement['label'] ?? $key;
    $rules = implode('|', array_map(fn ($rule) => is_scalar($rule) ? (string) $rule : json_encode($rule), $requirement['rules'] ?? []));
@endphp

<div>
    @if ($useJson)
        <label for="{{ $fieldId }}" class="block text-sm font-medium">{{ $label }} <span class="text-xs font-normal text-slate-500">(JSON)</span></label>
        <textarea id="{{ $fieldId }}" name="json_values[{{ $key }}]" rows="4" dir="ltr"
                  class="mt-1 block w-full rounded-md border-slate-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $value === null ? '' : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</textarea>
    @elseif ($choices !== [] && $multiple)
        <fieldset>
            <legend class="block text-sm font-medium">{{ $label }}</legend>
            <div class="mt-1 flex flex-wrap gap-4">
                @foreach ($choices as $choice)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="values[{{ $key }}][]" value="{{ $choice['value'] }}" @checked(in_array((string) $choice['value'], array_map('strval', (array) $value), true))
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        {{ $choice['label'] ?? $choice['value'] }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @elseif ($choices !== [])
        <label for="{{ $fieldId }}" class="block text-sm font-medium">{{ $label }}</label>
        <select id="{{ $fieldId }}" name="values[{{ $key }}]" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">—</option>
            @foreach ($choices as $choice)
                <option value="{{ $choice['value'] }}" @selected((string) $value === (string) $choice['value'])>{{ $choice['label'] ?? $choice['value'] }}</option>
            @endforeach
        </select>
    @elseif (in_array($type, ['boolean', 'checkbox'], true))
        <input type="hidden" name="values[{{ $key }}]" value="0">
        <label class="flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="values[{{ $key }}]" value="1" @checked((bool) $value)
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            {{ $label }}
        </label>
    @else
        <label for="{{ $fieldId }}" class="block text-sm font-medium">{{ $label }}</label>
        <input id="{{ $fieldId }}" name="values[{{ $key }}]" value="{{ is_scalar($value) ? $value : '' }}" placeholder="{{ $requirement['placeholder'] ?? '' }}"
               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @endif
    <p class="mt-1 font-mono text-xs text-slate-500">{{ $key }}@if ($rules !== '') · {{ $rules }}@endif</p>
</div>
