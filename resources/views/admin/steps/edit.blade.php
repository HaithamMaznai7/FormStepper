@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Edit step: '.$step->key)

@section('content')
    <form method="POST" action="{{ route($admin.'steps.update', $step) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')
        <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Options and forms store snapshots. Changes apply to future snapshots, not to existing option records or forms.
        </p>
        @include('form-stepper::admin.steps._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save changes</button>
            <a href="{{ route($admin.'steps.show', $step) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
