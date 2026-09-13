@extends('layouts.app')

@section('title', 'Create Library')
@section('meta_description', 'Create a library')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create Library</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('libraries.index') }}">Libraries</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Create</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('libraries.store') }}">
            @include('libraries._form', ['buttonText' => 'Create Library'])
        </form>
    </div>
</div>
@endsection
