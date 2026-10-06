@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Form '.$form->type)

@section('actions')
    @if ($form->status === 'draft')
        <a href="{{ route($admin.'forms.edit', $form) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</a>
    @endif
    @include('form-stepper::admin.partials.delete', ['action' => route($admin.'forms.destroy', $form), 'confirm' => 'Delete this form and all saved values?'])
@endsection

@section('content')
    @include('form-stepper::admin.forms._summary')

    <div class="mt-8 space-y-6">
        @forelse ($steps as $step)
            <section class="rounded-lg border border-slate-200 bg-white">
                <header class="flex items-center justify-between border-b border-slate-100 px-6 py-3">
                    <h2 class="font-semibold">{{ $step['title'] ?? $step['key'] }} <span class="font-mono text-xs font-normal text-slate-500">{{ $step['key'] }}</span></h2>
                    @unless (array_key_exists($step['key'], $values))
                        <span class="text-xs text-slate-500">Not saved yet</span>
                    @endunless
                </header>
                <div class="p-6">
                    @if (! empty($step['repeatable']))
                        @include('form-stepper::admin.partials.json', ['value' => $values[$step['key']][$step['repeat_name']] ?? []])
                    @else
                        <dl class="grid gap-4 sm:grid-cols-2">
                            @foreach ($step['requirements'] ?? [] as $requirement)
                                @php($value = $values[$step['key']][$requirement['key']] ?? null)
                                <div>
                                    <dt class="text-xs uppercase text-slate-500">{{ $requirement['label'] ?? $requirement['key'] }}</dt>
                                    <dd class="text-sm">
                                        @if (is_array($value))
                                            <code class="break-all text-xs">{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                                        @elseif (is_bool($value))
                                            {{ $value ? 'Yes' : 'No' }}
                                        @else
                                            {{ $value ?? '—' }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </section>
        @empty
            <p class="text-slate-500">This form has no steps.</p>
        @endforelse
    </div>
@endsection
