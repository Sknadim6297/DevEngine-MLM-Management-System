@extends('auth.layout')

@section('title', 'Forgot Password')

@section('content')
    <div class="login-title">Forgot Password?</div>
    <div class="login-subtitle">Enter your Member ID to receive a password reset OTP.</div>

    @if ($errors->any())
        <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('member.forgot.send') }}">
        @csrf
        <div class="input-group">
            <label>Member ID</label>
            <div class="input-box">
                <span>👤</span>
                <input type="text" name="member_id" value="{{ old('member_id') }}" placeholder="Enter Member ID" required autofocus>
            </div>
        </div>
        <button type="submit" class="login-btn">Send OTP</button>
    </form>

    <div class="login-footer"><a href="{{ route('login') }}">Back to Sign In</a></div>
@endsection
