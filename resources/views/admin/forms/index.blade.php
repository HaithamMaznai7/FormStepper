@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Forms')

@section('actions')
    <a href="{{ route($admin.'forms.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New draft form</a>
@endsection

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <select name="type" aria-label="Filter by type" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="Filter by status" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">All statuses</option>
            @foreach (['draft' => 'Open (draft)', 'submitted' => 'Finished (submitted)'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="mode" aria-label="Filter by mode" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">All modes</option>
            @foreach (['single', 'stepper'] as $mode)
                <option value="{{ $mode }}" @selected(request('mode') === $mode)>{{ ucfirst($mode) }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Form</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Current step</th>
                    <th class="px-4 py-3">Owner</th>
                    <th class="px-4 py-3">Updated</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($forms as $form)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs"><a href="{{ route($admin.'forms.show', $form) }}" class="text-indigo-600 hover:underline">{{ \Illuminate\Support\Str::limit($form->uuid, 13, '…') }}</a></td>
                        <td class="px-4 py-3">{{ $form->type }}</td>
                        <td class="px-4 py-3">{{ $form->mode }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium',
                                'bg-amber-50 text-amber-700' => $form->status === 'draft',
                                'bg-emerald-50 text-emerald-700' => $form->status !== 'draft',
                            ])>{{ $form->status }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $form->current_step_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            @if ($form->requester_type)
                                {{ class_basename($form->requester_type) }} #{{ $form->requester_id }}
                            @else
                                Guest
                            @endif
                            @if ($form->tenant_type)
                                <br>{{ class_basename($form->tenant_type) }} #{{ $form->tenant_id }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $form->updated_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($form->status === 'draft')
                                <a href="{{ route($admin.'forms.edit', $form) }}" class="text-indigo-600 hover:underline">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No forms found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $forms->links() }}</div>
@endsection
