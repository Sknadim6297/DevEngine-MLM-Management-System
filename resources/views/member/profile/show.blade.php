@extends('member.layouts.app')

@section('title', 'Member Profile')

@section('content')
    <main class="member-content">
        <div class="form-card">
            @if (session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            <div class="form-title d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h4>Personal Details</h4>
                <a href="{{ route('member.profile.edit') }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Member Name</label>
                    <div class="form-control bg-light">{{ $member->member_name }}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Email ID</label>
                    <div class="form-control bg-light">{{ $member->email }}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Mobile No</label>
                    <div class="form-control bg-light">{{ $member->mobile_no }}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label>BEP20 Wallet Address</label>
                    <div class="form-control bg-light">{{ $member->wallet_address ?: 'Not Added' }}</div>
                </div>
            </div>
        </div>
    </main>
@endsection
