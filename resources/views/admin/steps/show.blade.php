@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Step: '.$step->key)

@section('actions')
    <a href="{{ route($admin.'steps.edit', $step) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</a>
    @include('form-stepper::admin.partials.delete', [
        'action' => route($admin.'steps.destroy', $step),
        'confirm' => 'Delete this library step? Existing option snapshots are not changed.',
    ])
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <dl class="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
                <div><dt class="text-xs uppercase text-slate-500">Key</dt><dd class="font-mono">{{ $step->key }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Priority</dt><dd>{{ $step->priority }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Repeatable</dt><dd>{{ $step->repeatable ? 'Yes ('.$step->repeat_name.')' : 'No' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Requires authentication</dt><dd>{{ $step->requires_authentication ? 'Yes' : 'No' }}</dd></div>
            </dl>
            <div>
                <h2 class="mb-2 text-sm font-semibold">Inputs (in order)</h2>
                <ol class="list-inside list-decimal divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                    @foreach ($step->inputs as $input)
                        <li class="px-4 py-2 text-sm">
                            <a href="{{ route($admin.'inputs.show', $input) }}" class="font-mono text-indigo-600 hover:underline">{{ $input->key }}</a>
                            <span class="rounded bg-slate-100 px-1.5 text-xs text-slate-600">{{ $input->type }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
        <div>
            <h2 class="mb-2 text-sm font-semibold">Snapshot (what an option stores)</h2>
            @include('form-stepper::admin.partials.json', ['value' => $snapshot])
        </div>
    </div>
@endsection
