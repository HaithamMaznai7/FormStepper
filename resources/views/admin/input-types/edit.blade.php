@extends('form-stepper::admin.layout')
@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))

@section('title', 'Edit input type: '.$type->key)

@section('content')
    <form method="POST" action="{{ route($admin.'input-types.update', $type) }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')
        @include('form-stepper::admin.input-types._form')
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save changes</button>
            <a href="{{ route($admin.'input-types.show', $type) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
