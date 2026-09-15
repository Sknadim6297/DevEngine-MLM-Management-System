@extends('admin.layouts.app')

@section('content')
        <main class="dashboard-content">

        

        <div class="form-card">

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <!-- MEMBER ID SEARCH -->
            <div class="row align-items-end mb-4">

                <div class="col-md-6">
                    <label>Member ID</label>
                    <input type="text"
                        id="memberId"
                        name="member_id"
                        form="memberUpdateForm"
                        class="form-control"
                        placeholder="Enter Member ID">
                </div>

                <div class="col-md-auto mt-3 mt-md-0">
                    <button type="button" id="fetchMemberDetails" class="btn btn-primary px-4">
                        <i class="bi bi-search"></i>
                        Submit
                    </button>
                </div>

            </div>


            <!-- PERSONAL DETAILS -->
            <div class="form-title">
                <h4>Personal Details</h4>
            </div>

            <form method="POST" action="{{ route('admin.members.update-member') }}" id="memberUpdateForm">
                @csrf

            <div class="row">

                <div class="col-md-6 mb-3">
                    <label>Sponsor ID <span class="text-danger">*</span></label>
                    <input type="text"
                        id="sponsorId"
                        name="sponsor_id"
                        class="form-control"
                        placeholder="Enter Sponsor ID">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Sponsor Name <span class="text-danger">*</span></label>
                    <input type="text"
                        id="sponsorName"
                        name="sponsor_name"
                        class="form-control"
                        placeholder="Enter Sponsor Name">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Member Name <span class="text-danger">*</span></label>
                    <input type="text"
                        id="memberName"
                        name="member_name"
                        class="form-control"
                        placeholder="Enter Member Name">
                </div>

                <div class="col-md-6 mb-3">
                    <label>BEP20 Wallet Address</label>
                    <input type="text"
                        id="walletAddress"
                        name="wallet_address"
                        class="form-control"
                        placeholder="Enter BEP20 Wallet Address">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Mobile No <span class="text-danger">*</span></label>
                    <input type="text"
                        id="mobileNo"
                        name="mobile_no"
                        class="form-control"
                        placeholder="Enter Mobile No">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Email ID <span class="text-danger">*</span></label>
                    <input type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter Email ID">
                </div>

                <div class="col-md-6 mb-3">
                    <label>PAN Card Number <span class="text-danger">*</span></label>
                    <input type="text"
                        id="panCardNo"
                        name="pan_card_no"
                        class="form-control"
                        placeholder="Enter PAN Card Number"
                        maxlength="10">
                </div>

                <div class="col-md-6 mb-3">
                    <label>Password</label>
                    <input type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter Password">
                </div>

            </div>


            <!-- UPDATE BUTTON -->
            <div class="text-end mt-3">

                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-circle"></i>
                    Update
                </button>

            </div>

            </form>

        </div>


    </main>

@endsection

@section('scripts')
    <script>
        const memberIdInput = document.getElementById('memberId');
        const memberUpdateForm = document.getElementById('memberUpdateForm');
        const fetchMemberDetailsButton = document.getElementById('fetchMemberDetails');
        const fields = {
            sponsor_id: document.getElementById('sponsorId'),
            sponsor_name: document.getElementById('sponsorName'),
            name: document.getElementById('memberName'),
            wallet: document.getElementById('walletAddress'),
            mobile: document.getElementById('mobileNo'),
            email: document.getElementById('email'),
            pan_card_no: document.getElementById('panCardNo'),
            password: document.getElementById('password')
        };
        let fetchedMemberId = null;

        function clearMemberFields() {
            Object.values(fields).forEach(function (field) {
                if (field) {
                    field.value = '';
                }
            });
        }

        function showMemberError(message) {
            let error = document.getElementById('memberIdError');

            if (!error) {
                error = document.createElement('div');
                error.id = 'memberIdError';
                error.className = 'text-danger mt-1 small';
                memberIdInput.parentElement.appendChild(error);
            }

            error.textContent = message || '';
        }

        memberIdInput.addEventListener('input', function () {
            fetchedMemberId = null;
            clearMemberFields();
            showMemberError('');
        });

        fetchMemberDetailsButton.addEventListener('click', function (event) {
            event.preventDefault();
            const memberId = memberIdInput.value.trim();

            fetchedMemberId = null;
            clearMemberFields();
            showMemberError('');

            if (!memberId) {
                showMemberError('Member ID is required.');
                return;
            }

            fetch('{{ route('admin.members.fetch-details') }}?member_id=' + encodeURIComponent(memberId), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Member not found.');
                        }

                        return data;
                    });
                })
                .then(function (member) {
                    memberIdInput.value = member.member_id;
                    fields.sponsor_id.value = member.sponsor_id || '';
                    fields.sponsor_name.value = member.sponsor_name || '';
                    fields.name.value = member.name || '';
                    fields.wallet.value = member.wallet || '';
                    fields.mobile.value = member.mobile || '';
                    fields.email.value = member.email || '';
                    fields.pan_card_no.value = member.pan_card_no || '';
                    fields.password.value = '';
                    fetchedMemberId = member.member_id;
                })
                .catch(function (error) {
                    fetchedMemberId = null;
                    clearMemberFields();
                    showMemberError(error.message || 'Member not found.');
                });
        });

        memberUpdateForm.addEventListener('submit', function (event) {
            if (!fetchedMemberId || memberIdInput.value.trim() !== fetchedMemberId) {
                event.preventDefault();
                showMemberError('Fetch a valid Member ID before updating.');
            }
        });
    </script>
@endsection