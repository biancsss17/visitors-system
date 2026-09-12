<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'School Visitor System') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
        <section class="w-full rounded-2xl bg-white p-8 shadow-xl ring-1 ring-slate-200 sm:p-12">
            <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-indigo-600">School Visitor System</p>
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Welcome</h1>
            <p class="mt-3 text-slate-600">Enter a business type to prepare a generated result.</p>

            @if ($errors->any())
                <div class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700" role="alert">
                    {{ $errors->first('business_type') }}
                </div>
            @endif

            <form action="{{ url('/generate') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label for="business_type" class="mb-2 block text-sm font-medium text-slate-700">Business type</label>
                    <input
                        id="business_type"
                        name="business_type"
                        type="text"
                        value="{{ old('business_type') }}"
                        required
                        maxlength="255"
                        placeholder="e.g. School cafeteria"
                        class="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                    >
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-2">
                    Generate
                </button>
            </form>
        </section>
    </main>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
