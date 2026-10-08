@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Input: '.$input->key)

@section('actions')
    <a href="{{ route($admin.'inputs.edit', $input) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</a>
    @php($usage = $input->stepTemplates->count() + $input->parents->count())
    @include('form-stepper::admin.partials.delete', [
        'action' => route($admin.'inputs.destroy', $input),
        'confirm' => $usage > 0
            ? "This input is used by {$usage} library step(s) or complex input(s) and will be removed from them. Existing option snapshots are not changed. Delete?"
            : 'Delete this input? Existing option snapshots are not changed.',
    ])
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <dl class="grid content-start gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
            <div><dt class="text-xs uppercase text-slate-500">Key</dt><dd class="font-mono">{{ $input->key }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Type</dt><dd>{{ $input->inputType?->name ?? $input->type }} <span class="font-mono text-xs text-slate-500">({{ $input->type }})</span></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Rules</dt><dd class="font-mono text-sm">{{ implode(' | ', array_map(fn ($rule) => is_scalar($rule) ? (string) $rule : json_encode($rule), $input->rules ?? [])) ?: '—' }}</dd></div>
            @if ($input->children->isNotEmpty())
                <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Children</dt>
                    <dd class="mt-1 flex flex-wrap gap-2">
                        @foreach ($input->children as $child)
                            <a href="{{ route($admin.'inputs.show', $child) }}" class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-indigo-700 hover:underline">{{ $child->key }}</a>
                        @endforeach
                    </dd>
                </div>
            @endif
            <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Used by steps</dt>
                <dd class="mt-1 flex flex-wrap gap-2">
                    @forelse ($input->stepTemplates as $step)
                        <a href="{{ route($admin.'steps.show', $step) }}" class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-indigo-700 hover:underline">{{ $step->key }}</a>
                    @empty
                        <span class="text-slate-500">—</span>
                    @endforelse
                </dd>
            </div>
        </dl>
        <div>
            <h2 class="mb-2 text-sm font-semibold">Snapshot (what an option stores)</h2>
            @include('form-stepper::admin.partials.json', ['value' => $snapshot])
        </div>
    </div>
@endsection
