@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Input type: '.$type->key)

@section('actions')
    <a href="{{ route($admin.'input-types.edit', $type) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</a>
    @unless ($type->is_system)
        @include('form-stepper::admin.partials.delete', ['action' => route($admin.'input-types.destroy', $type)])
    @endunless
@endsection

@section('content')
    <dl class="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-2">
        <div><dt class="text-xs uppercase text-slate-500">Key</dt><dd class="font-mono">{{ $type->key }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-500">Name</dt><dd>{{ $type->name }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-500">Kind</dt><dd>{{ $type->is_system ? 'System (cannot be deleted)' : 'Custom' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-500">Features</dt><dd>{{ collect(['children' => $type->has_children, 'options' => $type->has_options])->filter()->keys()->implode(', ') ?: '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Description</dt><dd>{{ $type->description ?: '—' }}</dd></div>
    </dl>

    <h2 class="mb-3 mt-8 text-lg font-semibold">Inputs using this type ({{ $inputs->count() }})</h2>
    <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
        @forelse ($inputs as $input)
            <li class="px-4 py-3"><a href="{{ route($admin.'inputs.show', $input) }}" class="font-mono text-indigo-600 hover:underline">{{ $input->key }}</a> <span class="text-slate-500">{{ $input->label }}</span></li>
        @empty
            <li class="px-4 py-6 text-center text-slate-500">No inputs use this type.</li>
        @endforelse
    </ul>
@endsection
