<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand bg-dark mb-4" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand" href="{{ route('upload.create') }}">{{ config('app.name') }}</a>
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('upload.*') ? 'active' : '' }}"
                   href="{{ route('upload.create') }}">Upload</a>
            </li>
        </ul>
    </div>
</nav>

<main class="container">
    @yield('content')
</main>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
</script>
@stack('scripts')
</body>
</html>
