@extends('layouts.app')

@section('title', 'Edit FHE Context')
@section('meta_description', 'Edit an FHE context')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit FHE Context</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-contexts.index') }}">FHE Contexts</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $context->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('fhe-contexts.update', $context) }}">
            @method('PUT')
            @include('fhe_contexts._form', ['buttonText' => 'Update Context'])
        </form>
    </div>
</div>
@endsection
