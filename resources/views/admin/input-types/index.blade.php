@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Input types')

@section('actions')
    <a href="{{ route($admin.'input-types.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New input type</a>
@endsection

@section('content')
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Kind</th>
                    <th class="px-4 py-3">Features</th>
                    <th class="px-4 py-3">Inputs</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($types as $type)
                    <tr>
                        <td class="px-4 py-3 font-mono">
                            <a href="{{ route($admin.'input-types.show', $type) }}" class="text-indigo-600 hover:underline">{{ $type->key }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $type->name }}</td>
                        <td class="px-4 py-3">
                            @if ($type->is_system)
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">System</span>
                            @else
                                <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Custom</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ collect(['children' => $type->has_children, 'options' => $type->has_options])->filter()->keys()->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $type->inputs_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route($admin.'input-types.edit', $type) }}" class="text-indigo-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No input types yet. Run the package migrations to seed the system types.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $types->links() }}</div>
@endsection
