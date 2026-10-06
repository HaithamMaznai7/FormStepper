@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'New library step')

@section('content')
    <form method="POST" action="{{ route($admin.'steps.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @include('form-stepper::admin.steps._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create step</button>
            <a href="{{ route($admin.'steps.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
