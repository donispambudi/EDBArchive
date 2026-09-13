@extends('layouts.app')

@section('title', 'Edit Database Column')
@section('meta_description', 'Edit database column protection metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Database Column</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('database-columns.index') }}">Database Columns</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $databaseColumn->column_name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('database-columns.update', $databaseColumn) }}">
            @method('PUT')
            @include('database_columns._form', ['buttonText' => 'Update Column'])
        </form>
    </div>
</div>
@endsection
