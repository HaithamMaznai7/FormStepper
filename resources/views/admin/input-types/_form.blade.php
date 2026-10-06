{{-- Shared input type fields. Expects $type. --}}
<div class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
    <div>
        <label for="key" class="block text-sm font-medium">Key</label>
        @if ($type->is_system)
            <p class="mt-1 font-mono text-sm text-slate-600">{{ $type->key }}</p>
            <p class="mt-1 text-xs text-slate-500">System type keys are fixed.</p>
        @else
            <input id="key" name="key" value="{{ old('key', $type->key) }}" required pattern="[a-z0-9][a-z0-9_-]*" dir="ltr"
                   class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Lowercase letters, numbers, dashes and underscores. Used as the input <code>type</code> in schemas.</p>
        @endif
    </div>

    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" value="{{ old('name', $type->name) }}" required
               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label for="description" class="block text-sm font-medium">Description</label>
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $type->description) }}</textarea>
    </div>

    <label class="flex items-start gap-3">
        <input type="checkbox" name="has_options" value="1" @checked(old('has_options', $type->has_options))
               class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        <span>
            <span class="block text-sm font-medium">Uses choice options</span>
            <span class="block text-xs text-slate-500">Show the <em>options</em> editor for inputs of this type (selections, radios, checkboxes).</span>
        </span>
    </label>

    @if ($type->has_children)
        <p class="text-xs text-slate-500">This type groups child inputs (complex).</p>
    @endif
</div>
