@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="card">
        <div class="card-body">
            <h1 class="h4">{{ config('app.name') }}</h1>
            <p class="mb-0 text-body-secondary">The application is up and running.</p>
        </div>
    </div>
@endsection
