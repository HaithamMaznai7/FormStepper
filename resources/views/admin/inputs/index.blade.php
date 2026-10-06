@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Lookup inputs')

@section('actions')
    <a href="{{ route($admin.'inputs.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New input</a>
@endsection

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search key or label" aria-label="Search inputs"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <select name="type" aria-label="Filter by type" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type->key }}" @selected(request('type') === $type->key)>{{ $type->name }} ({{ $type->key }})</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Rules</th>
                    <th class="px-4 py-3">Used in</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($inputs as $input)
                    <tr>
                        <td class="px-4 py-3 font-mono"><a href="{{ route($admin.'inputs.show', $input) }}" class="text-indigo-600 hover:underline">{{ $input->key }}</a></td>
                        <td class="px-4 py-3">{{ $input->label ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $input->type }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ implode('|', array_map(fn ($rule) => is_scalar($rule) ? (string) $rule : json_encode($rule), $input->rules ?? [])) ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $input->step_templates_count }} steps · {{ $input->parents_count }} complex</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route($admin.'inputs.edit', $input) }}" class="text-indigo-600 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No lookup inputs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $inputs->links() }}</div>
@endsection
