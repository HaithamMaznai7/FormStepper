@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'New draft form')

@section('content')
    <form method="POST" action="{{ route($admin.'forms.store') }}" class="max-w-2xl space-y-6">
        @csrf
        <div class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            <div>
                <label for="type" class="block text-sm font-medium">Form type</label>
                <select id="type" name="type" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Registered in <code>config('form-stepper.builders')</code>.</p>
            </div>
            <div>
                <label for="options" class="block text-sm font-medium">Option keys</label>
                <input id="options" name="options" value="{{ old('options') }}" dir="ltr" placeholder="car-insurance, extra-driver"
                       class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">Comma or space separated. Requirements are merged from the selected options.</p>
            </div>
            @if ($allowModeOverride)
                <div>
                    <label for="mode" class="block text-sm font-medium">Mode</label>
                    <select id="mode" name="mode" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <option value="">Builder default</option>
                        <option value="single" @selected(old('mode') === 'single')>Single</option>
                        <option value="stepper" @selected(old('mode') === 'stepper')>Stepper</option>
                    </select>
                </div>
            @endif
            <p class="text-xs text-slate-500">The draft is owned by the requester and tenant the builder resolves for your account.</p>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create draft</button>
            <a href="{{ route($admin.'forms.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
