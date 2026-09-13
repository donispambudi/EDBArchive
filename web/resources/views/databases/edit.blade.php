@extends('layouts.app')

@section('title', 'Edit Database')
@section('meta_description', 'Edit database metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Database</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('databases.index') }}">Databases</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $database->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('databases.update', $database) }}">
            @method('PUT')
            @include('databases._form', ['buttonText' => 'Update Database'])
        </form>
    </div>
</div>
@endsection
