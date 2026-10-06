{{-- Shared library step fields. Expects $step, $inputs, $positions. --}}
@php
    $selected = collect(old('inputs', array_keys($positions)))->map(fn ($id) => (int) $id)->all();
    $oldPositions = old('positions', $positions);
@endphp

<div class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="key" class="block text-sm font-medium">Key</label>
            <input id="key" name="key" value="{{ old('key', $step->key) }}" required dir="ltr"
                   class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Steps with the same key are merged across selected options. <code>review</code> is reserved.</p>
        </div>
        <div>
            <label for="title" class="block text-sm font-medium">Title</label>
            <input id="title" name="title" value="{{ old('title', $step->title) }}"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="sm:col-span-2">
            <label for="subtitle" class="block text-sm font-medium">Subtitle</label>
            <input id="subtitle" name="subtitle" value="{{ old('subtitle', $step->subtitle) }}"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="space-y-3">
            <label class="flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" id="repeatable" name="repeatable" value="1" @checked(old('repeatable', $step->repeatable))
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Repeatable (users can add many instances)
            </label>
            <div>
                <label for="repeat_name" class="block text-sm font-medium">Repeat name</label>
                <input id="repeat_name" name="repeat_name" value="{{ old('repeat_name', $step->repeat_name) }}" dir="ltr" placeholder="vehicles"
                       class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">Required when repeatable. Values are stored as <code>{repeat_name: [...]}</code>.</p>
            </div>
        </div>
        <label class="flex items-start gap-3 text-sm font-medium">
            <input type="checkbox" name="requires_authentication" value="1" @checked(old('requires_authentication', $step->requires_authentication))
                   class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span>Requires authentication
                <span class="block text-xs font-normal text-slate-500">Guests must sign in before this step.</span></span>
        </label>
    </div>

    <fieldset>
        <legend class="text-sm font-medium">Inputs</legend>
        <p class="mb-2 text-xs text-slate-500">Select the lookup inputs for this step. Lower order numbers come first.</p>
        <div class="max-h-96 divide-y divide-slate-100 overflow-auto rounded-md border border-slate-200">
            @forelse ($inputs as $input)
                <div class="flex items-center gap-3 px-3 py-2 text-sm">
                    <input type="checkbox" id="input-{{ $input->id }}" name="inputs[]" value="{{ $input->id }}" @checked(in_array($input->id, $selected, true))
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="input-{{ $input->id }}" class="flex-1">
                        <span class="font-mono">{{ $input->key }}</span>
                        <span class="text-slate-500">{{ $input->label }}</span>
                        <span class="ms-1 rounded bg-slate-100 px-1.5 text-xs text-slate-600">{{ $input->type }}</span>
                    </label>
                    <label class="sr-only" for="position-{{ $input->id }}">Order for {{ $input->key }}</label>
                    <input type="number" min="0" id="position-{{ $input->id }}" name="positions[{{ $input->id }}]" value="{{ $oldPositions[$input->id] ?? '' }}" placeholder="#"
                           class="w-20 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            @empty
                <p class="px-3 py-6 text-center text-sm text-slate-500">No lookup inputs yet. Create inputs first.</p>
            @endforelse
        </div>
    </fieldset>
</div>
