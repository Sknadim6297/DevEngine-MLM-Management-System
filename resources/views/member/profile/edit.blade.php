@extends('member.layouts.app')

@section('title', 'Edit Member Profile')

@section('content')
    <main class="member-content">
        <div class="form-card">
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="form-title">
                <h4>Personal Details</h4>
            </div>

            <form method="POST" action="{{ route('member.profile.update') }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Member Name <span class="text-danger">*</span></label>
                        <input type="text" name="member_name" class="form-control" value="{{ old('member_name', $member->member_name) }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Email ID <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $member->email) }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Mobile No <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_no" class="form-control" value="{{ old('mobile_no', $member->mobile_no) }}" maxlength="15" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>BEP20 Wallet Address</label>
                        <input type="text" name="wallet_address" class="form-control" value="{{ old('wallet_address', $member->wallet_address) }}" placeholder="Enter your BEP20 wallet address" maxlength="255">
                    </div>
                </div>

                <div class="text-end mt-3 d-flex justify-content-end gap-2">
                    <a href="{{ route('member.profile') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection
