{{-- Form summary card. Expects $form. --}}
<dl class="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:grid-cols-3">
    <div><dt class="text-xs uppercase text-slate-500">UUID</dt><dd class="break-all font-mono text-xs">{{ $form->uuid }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-500">Type / mode</dt><dd>{{ $form->type }} · {{ $form->mode }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-500">Status</dt><dd>{{ $form->status }}@if ($form->completed_at) <span class="text-xs text-slate-500">({{ $form->completed_at->toDayDateTimeString() }})</span>@endif</dd></div>
    <div><dt class="text-xs uppercase text-slate-500">Requester</dt><dd class="text-sm">{{ $form->requester_type ? class_basename($form->requester_type).' #'.$form->requester_id : 'Guest' }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-500">Tenant</dt><dd class="text-sm">{{ $form->tenant_type ? class_basename($form->tenant_type).' #'.$form->tenant_id : '—' }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-500">Current step</dt><dd class="font-mono text-sm">{{ $form->current_step_id ?? '—' }}</dd></div>
    <div class="sm:col-span-3"><dt class="text-xs uppercase text-slate-500">Selected options</dt>
        <dd class="mt-1 flex flex-wrap gap-2">
            @forelse ($form->selectedOptions as $option)
                <span class="rounded bg-indigo-50 px-2 py-0.5 font-mono text-xs text-indigo-700">{{ $option->option_key }}</span>
            @empty
                <span class="text-slate-500">—</span>
            @endforelse
        </dd>
    </div>
</dl>
