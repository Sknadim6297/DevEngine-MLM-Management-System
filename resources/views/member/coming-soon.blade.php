@extends('member.layouts.app')

@section('title', $feature . ' - Coming Soon')

@section('content')
    <div class="form-card">
        <div class="form-title">
            <h4>{{ $feature }}</h4>
        </div>
        <div class="alert alert-info mb-0">{{ $feature }} - Coming Soon</div>
    </div>
@endsection
