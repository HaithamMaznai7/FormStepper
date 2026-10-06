@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'New lookup input')

@section('content')
    <form method="POST" action="{{ route($admin.'inputs.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @include('form-stepper::admin.inputs._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create input</button>
            <a href="{{ route($admin.'inputs.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
