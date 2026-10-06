@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Library steps')

@section('actions')
    <a href="{{ route($admin.'steps.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New step</a>
@endsection

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search key or title" aria-label="Search steps"
               class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Inputs</th>
                    <th class="px-4 py-3">Flags</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($steps as $step)
                    <tr>
                        <td class="px-4 py-3 font-mono"><a href="{{ route($admin.'steps.show', $step) }}" class="text-indigo-600 hover:underline">{{ $step->key }}</a></td>
                        <td class="px-4 py-3">{{ $step->title ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $step->inputs_count }}</td>
                        <td class="px-4 py-3 space-x-1">
                            @if ($step->repeatable)<span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">repeatable: {{ $step->repeat_name }}</span>@endif
                            @if ($step->requires_authentication)<span class="rounded bg-sky-50 px-2 py-0.5 text-xs text-sky-700">auth</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route($admin.'steps.edit', $step) }}" class="text-indigo-600 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No library steps found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $steps->links() }}</div>
@endsection
