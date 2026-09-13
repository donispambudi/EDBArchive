@extends('layouts.app')

@section('title', 'Create Database')
@section('meta_description', 'Create database metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create Database</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('databases.index') }}">Databases</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('databases.store') }}">
            @include('databases._form', ['buttonText' => 'Create Database'])
        </form>
    </div>
</div>
@endsection
