@extends('layouts.app')

@section('title', 'Create Database Column')
@section('meta_description', 'Create database column protection metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create Database Column</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('database-columns.index') }}">Database Columns</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('database-columns.store') }}">
            @include('database_columns._form', ['buttonText' => 'Create Column'])
        </form>
    </div>
</div>
@endsection
