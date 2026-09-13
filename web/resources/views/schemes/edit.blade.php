@extends('layouts.app')

@section('title', 'Edit Scheme')
@section('meta_description', 'Edit a scheme')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Scheme</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('schemes.index') }}">Schemes</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $scheme->scheme_name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('schemes.update', $scheme) }}">
            @method('PUT')
            @include('schemes._form', ['buttonText' => 'Update Scheme'])
        </form>
    </div>
</div>
@endsection
