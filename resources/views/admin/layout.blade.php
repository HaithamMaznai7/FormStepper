@php($admin = config('form-stepper.admin.name', 'form-stepper.admin.'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Form Stepper') · Form Stepper Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-6 py-4">
        <a href="{{ route($admin.'forms.index') }}" class="text-lg font-semibold text-indigo-600">Form Stepper</a>
        <nav class="flex flex-wrap gap-1 text-sm">
            @foreach ([
                'forms' => 'Forms',
                'steps' => 'Steps',
                'inputs' => 'Inputs',
                'input-types' => 'Input types',
            ] as $section => $label)
                <a href="{{ route($admin.$section.'.index') }}"
                   @class([
                       'rounded-md px-3 py-2 font-medium',
                       'bg-indigo-50 text-indigo-700' => request()->routeIs($admin.$section.'.*'),
                       'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($admin.$section.'.*'),
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="mx-auto max-w-7xl px-6 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">@yield('title')</h1>
        <div class="flex flex-wrap gap-2">@yield('actions')</div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
@stack('scripts')
</body>
</html>
