@extends('admin.layouts.app')

@section('content')
    <main class="member-content">
        <div class="form-card">
            @if(session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.members.update-member') }}">
                @csrf

                <div class="form-title">
                    <h4>Member Profile Update</h4>
                </div>

                <div class="row align-items-end mb-4">
                    <div class="col-md-6">
                        <label>Member ID</label>
                        <input type="text" name="member_id" class="form-control" value="{{ old('member_id', $member['member_id'] ?? '') }}" placeholder="Enter Member ID" data-current-member-id="{{ old('member_id', $member['member_id'] ?? '') }}" readonly>
                        <div class="text-danger mt-1 small validation-message" data-error-for="member_id">
                            @error('member_id'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-auto mt-3 mt-md-0">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-search"></i>
                            Submit
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Sponsor ID <span class="text-danger">*</span></label>
                        <input type="text" name="sponsor_id" class="form-control" value="{{ old('sponsor_id', $member['sponsor_id'] ?? '') }}" placeholder="Enter Sponsor ID" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="sponsor_id">
                            @error('sponsor_id'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Sponsor Name <span class="text-danger">*</span></label>
                        <input type="text" name="sponsor_name" class="form-control" value="{{ old('sponsor_name', $member['sponsor_name'] ?? '') }}" placeholder="Enter Sponsor Name" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="sponsor_name">
                            @error('sponsor_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Member Name <span class="text-danger">*</span></label>
                        <input type="text" name="member_name" class="form-control" value="{{ old('member_name', $member['name'] ?? '') }}" placeholder="Enter Member Name" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="member_name">
                            @error('member_name'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>BEP20 Wallet Address</label>
                        <input type="text" name="wallet_address" class="form-control" value="{{ old('wallet_address', $member['wallet'] ?? '') }}" placeholder="Enter BEP20 Wallet Address">
                        <div class="text-danger mt-1 small validation-message" data-error-for="wallet_address">
                            @error('wallet_address'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Mobile No <span class="text-danger">*</span></label>
                        <input type="text" name="mobile_no" class="form-control" value="{{ old('mobile_no', $member['mobile'] ?? '') }}" placeholder="Enter Mobile No" required maxlength="10">
                        <div class="text-danger mt-1 small validation-message" data-error-for="mobile_no">
                            @error('mobile_no'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Email ID <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $member['email'] ?? '') }}" placeholder="Enter Email ID" required>
                        <div class="text-danger mt-1 small validation-message" data-error-for="email">
                            @error('email'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>PAN Card Number <span class="text-danger">*</span></label>
                        <input type="text" name="pan_card_no" class="form-control" value="{{ old('pan_card_no', $member['pan_card_no'] ?? '') }}" placeholder="Enter PAN Card Number" required maxlength="10">
                        <div class="text-danger mt-1 small validation-message" data-error-for="pan_card_no">
                            @error('pan_card_no'){{ $message }}@enderror
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter Password">
                        <div class="text-danger mt-1 small validation-message" data-error-for="password">
                            @error('password'){{ $message }}@enderror
                        </div>
                    </div>

                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-circle"></i>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const memberIdInput = document.querySelector('input[name="member_id"]');
        const sponsorIdInput = document.querySelector('input[name="sponsor_id"]');
        const sponsorNameInput = document.querySelector('input[name="sponsor_name"]');
        const memberNameInput = document.querySelector('input[name="member_name"]');
        const emailInput = document.querySelector('input[name="email"]');
        const mobileNoInput = document.querySelector('input[name="mobile_no"]');
        const panCardInput = document.querySelector('input[name="pan_card_no"]');
        const updateForm = document.querySelector('form');
        const currentMemberId = (memberIdInput?.dataset.currentMemberId || '').trim();
        let sponsorValidationTimer = null;
        const touchedFields = new Set();

        function setError(fieldName, message = '') {
            const target = document.querySelector('[data-error-for="' + fieldName + '"]');
            if (!target) {
                return;
            }

            target.textContent = message;
            target.style.display = message ? 'block' : 'none';
        }

        function markTouched(fieldName) {
            touchedFields.add(fieldName);
        }

        function shouldShowRequired(fieldName, force = false) {
            return force || touchedFields.has(fieldName);
        }

        function validateMemberName(force = false) {
            const value = (memberNameInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('member_name', force)) {
                    setError('member_name', 'Member name is required.');
                } else {
                    setError('member_name', '');
                }
                return false;
            }

            if (value.length < 3) {
                setError('member_name', 'Member name must contain at least 3 characters.');
                return false;
            }

            if (!/^[A-Za-z ]+$/.test(value)) {
                setError('member_name', 'Member name can contain only letters and spaces.');
                return false;
            }

            setError('member_name', '');
            return true;
        }

        function validateEmail(force = false) {
            const value = (emailInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('email', force)) {
                    setError('email', 'Email ID is required.');
                } else {
                    setError('email', '');
                }
                return false;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                setError('email', 'Please enter a valid email address.');
                return false;
            }

            setError('email', '');
            return true;
        }

        function validateMobileNo(force = false) {
            const value = (mobileNoInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('mobile_no', force)) {
                    setError('mobile_no', 'Mobile No is required.');
                } else {
                    setError('mobile_no', '');
                }
                return false;
            }

            if (!/^[0-9]+$/.test(value)) {
                setError('mobile_no', 'Please enter a valid mobile number.');
                return false;
            }

            if (value.length !== 10) {
                setError('mobile_no', 'Mobile No must be exactly 10 digits.');
                return false;
            }

            setError('mobile_no', '');
            return true;
        }

        function validatePanCard(force = false) {
            const value = (panCardInput?.value || '').trim();

            if (!value) {
                if (shouldShowRequired('pan_card_no', force)) {
                    setError('pan_card_no', 'PAN Card Number is required.');
                } else {
                    setError('pan_card_no', '');
                }
                return false;
            }

            if (value.length !== 10) {
                setError('pan_card_no', 'PAN Card Number must be exactly 10 characters.');
                return false;
            }

            setError('pan_card_no', '');
            return true;
        }

        function validateSponsorId(force = false) {
            const sponsorId = (sponsorIdInput?.value || '').trim();

            if (!sponsorId) {
                if (shouldShowRequired('sponsor_id', force)) {
                    setError('sponsor_id', 'Sponsor ID is required.');
                } else {
                    setError('sponsor_id', '');
                }
                if (sponsorNameInput) {
                    sponsorNameInput.value = '';
                }
                return false;
            }

            if (sponsorId === 'ST666666') {
                if (sponsorNameInput) {
                    sponsorNameInput.value = 'Admin';
                }
                setError('sponsor_id', '');
                return true;
            }

            const requestId = Date.now();
            sponsorValidationTimer = requestId;
            setError('sponsor_id', '');

            fetch('/admin/members/check-sponsor-id?sponsor_id=' + encodeURIComponent(sponsorId))
                .then(response => response.json())
                .then(data => {
                    if (sponsorValidationTimer !== requestId) {
                        return;
                    }

                    if (data.exists) {
                        if (sponsorNameInput) {
                            sponsorNameInput.value = data.sponsor_name || '';
                        }
                        setError('sponsor_id', '');
                    } else {
                        if (sponsorNameInput) {
                            sponsorNameInput.value = '';
                        }
                        setError('sponsor_id', data.message || 'Invalid Sponsor ID.');
                    }
                })
                .catch(() => {
                    if (sponsorValidationTimer === requestId) {
                        setError('sponsor_id', 'Invalid Sponsor ID.');
                    }
                });

            return true;
        }

        function markAllFieldsTouched() {
            markTouched('member_name');
            markTouched('sponsor_id');
            markTouched('email');
            markTouched('mobile_no');
            markTouched('pan_card_no');
        }

        function validateAllFields() {
            const memberNameOk = validateMemberName(true);
            const sponsorIdOk = validateSponsorId(true);
            const emailOk = validateEmail(true);
            const mobileNoOk = validateMobileNo(true);
            const panCardOk = validatePanCard(true);

            return memberNameOk && sponsorIdOk && emailOk && mobileNoOk && panCardOk;
        }

        memberNameInput?.addEventListener('input', function () {
            const value = this.value;
            if (value !== value.trim()) {
                this.value = value.trim();
            }
            markTouched('member_name');
            validateMemberName();
        });
        memberNameInput?.addEventListener('blur', function () {
            markTouched('member_name');
            validateMemberName(true);
        });

        sponsorIdInput?.addEventListener('input', function () {
            const value = this.value;
            if (value !== value.trim()) {
                this.value = value.trim();
            }
            markTouched('sponsor_id');
            clearTimeout(sponsorValidationTimer);
            if (!this.value.trim()) {
                validateSponsorId();
                return;
            }
            sponsorValidationTimer = setTimeout(() => validateSponsorId(), 400);
        });
        sponsorIdInput?.addEventListener('blur', function () {
            markTouched('sponsor_id');
            validateSponsorId(true);
        });

        emailInput?.addEventListener('input', function () {
            markTouched('email');
            validateEmail();
        });
        emailInput?.addEventListener('blur', function () {
            markTouched('email');
            validateEmail(true);
        });

        mobileNoInput?.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            markTouched('mobile_no');
            validateMobileNo();
        });
        mobileNoInput?.addEventListener('blur', function () {
            markTouched('mobile_no');
            validateMobileNo(true);
        });

        panCardInput?.addEventListener('input', function () {
            this.value = this.value.slice(0, 10);
            markTouched('pan_card_no');
            validatePanCard();
        });
        panCardInput?.addEventListener('blur', function () {
            markTouched('pan_card_no');
            validatePanCard(true);
        });

        updateForm?.addEventListener('submit', function (event) {
            markAllFieldsTouched();
            const isValid = validateAllFields();

            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
@endsection