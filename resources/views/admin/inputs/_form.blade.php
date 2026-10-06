{{-- Shared lookup input fields. Expects $input, $types, $candidates. --}}
@php
    $selectedChildren = collect(old('children', $input->exists ? $input->children->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $rulesText = old('rules', implode("\n", array_map(fn ($rule) => is_scalar($rule) ? (string) $rule : json_encode($rule), $input->rules ?? [])));
    $optionsText = old('options', collect($input->options ?? [])->map(fn ($option) => $option['value'] === $option['label'] ? $option['value'] : $option['value'].'|'.$option['label'])->implode("\n"));
    $defaultText = old('default_value', $input->default_value === null ? '' : (is_string($input->default_value) ? $input->default_value : json_encode($input->default_value)));
    $extraText = old('extra', $input->extra === null ? '' : json_encode($input->extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $optionTypes = $types->where('has_options', true)->pluck('key')->values();
    $childTypes = $types->where('has_children', true)->pluck('key')->values();
@endphp

<div class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="key" class="block text-sm font-medium">Key</label>
            <input id="key" name="key" value="{{ old('key', $input->key) }}" required dir="ltr"
                   class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Saved values use this key, e.g. <code>name</code> or <code>contact.email</code>.</p>
        </div>
        <div>
            <label for="type" class="block text-sm font-medium">Type</label>
            <select id="type" name="type" required data-option-types='@json($optionTypes)' data-child-types='@json($childTypes)'
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($types as $type)
                    <option value="{{ $type->key }}" @selected(old('type', $input->type ?? 'input') === $type->key)>{{ $type->name }} ({{ $type->key }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="label" class="block text-sm font-medium">Label</label>
            <input id="label" name="label" value="{{ old('label', $input->label) }}"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="placeholder" class="block text-sm font-medium">Placeholder</label>
            <input id="placeholder" name="placeholder" value="{{ old('placeholder', $input->placeholder) }}"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <label for="rules" class="block text-sm font-medium">Validation rules</label>
        <textarea id="rules" name="rules" rows="3" dir="ltr" placeholder="required&#10;string&#10;max:255"
                  class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $rulesText }}</textarea>
        <p class="mt-1 text-xs text-slate-500">One Laravel rule per line, or <code>required|string</code>. <code>regex:</code> rules must be on their own line.</p>
    </div>

    <div data-section="options">
        <label for="options" class="block text-sm font-medium">Choice options</label>
        <textarea id="options" name="options" rows="4" dir="ltr" placeholder="sedan|Sedan&#10;suv|SUV"
                  class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $optionsText }}</textarea>
        <p class="mt-1 text-xs text-slate-500">One <code>value|label</code> per line. Stored in the snapshot as <code>extra.options</code>.</p>
    </div>

    <fieldset data-section="children">
        <legend class="block text-sm font-medium">Child inputs (complex)</legend>
        <p class="mb-2 text-xs text-slate-500">Children are snapshotted in the order listed. Keys must be unique within the complex input.</p>
        <div class="grid max-h-64 gap-2 overflow-auto rounded-md border border-slate-200 p-3 sm:grid-cols-2">
            @forelse ($candidates as $candidate)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="children[]" value="{{ $candidate->id }}" @checked(in_array($candidate->id, $selectedChildren, true))
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="font-mono">{{ $candidate->key }}</span>
                    <span class="text-xs text-slate-500">{{ $candidate->type }}</span>
                </label>
            @empty
                <p class="text-sm text-slate-500">Create other inputs first, then group them here.</p>
            @endforelse
        </div>
    </fieldset>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="default_value" class="block text-sm font-medium">Default value</label>
            <input id="default_value" name="default_value" value="{{ $defaultText }}" dir="ltr"
                   class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Plain text, or JSON such as <code>true</code>, <code>3</code>, <code>["a","b"]</code>.</p>
        </div>
        <div>
            <label for="extra" class="block text-sm font-medium">Extra (JSON object)</label>
            <textarea id="extra" name="extra" rows="3" dir="ltr" placeholder='{"icon": "user"}'
                      class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $extraText }}</textarea>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('type');
        const optionTypes = JSON.parse(type.dataset.optionTypes);
        const childTypes = JSON.parse(type.dataset.childTypes);
        const toggle = () => {
            document.querySelector('[data-section="options"]').hidden = !optionTypes.includes(type.value);
            document.querySelector('[data-section="children"]').hidden = !childTypes.includes(type.value);
        };
        type.addEventListener('change', toggle);
        toggle();
    })();
</script>
@endpush
