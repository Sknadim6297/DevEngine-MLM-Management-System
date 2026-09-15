@extends('admin.layouts.app')

@section('content')
    <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Investment Entry</h4>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.investments.store') }}">
            @csrf
        <div class="row">

            <!-- INVESTMENT ID -->
            <div class="col-md-6 mb-3">
                <label>Investment ID</label>

                <input type="text"
                       id="investmentId"
                      name="investment_id"
                      value="{{ old('investment_id', $investmentId) }}"
                       class="form-control"
                       placeholder="Enter Investment ID">
            </div>


            <!-- MEMBER ID -->
            <div class="col-md-6 mb-3">
                <label>Member ID</label>

                <input type="text"
                      id="memberId"
                      name="member_id"
                      value="{{ old('member_id') }}"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div class="col-md-6 mb-3">
                <label>Member Name</label>

                <input type="text"
                      id="memberName"
                      name="member_name"
                      value="{{ old('member_name') }}"
                       class="form-control"
                      placeholder="Enter Member Name"
                      readonly>
            </div>


            <!-- INVESTMENT AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>Investment Amount (USDT)</label>

                <input type="number"
                        id="investmentAmount"
                        name="amount"
                        value="{{ old('amount') }}"
                       class="form-control"
                       placeholder="Enter Investment Amount"
                       step="0.0001">
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="text-end mt-2">

            <button type="submit"
                    class="btn btn-primary px-4"
                    >

                <i class="bi bi-check-circle"></i>
                Submit

            </button>

            <button type="reset"
                    class="btn btn-primary px-4 ms-2"
                    >

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>
        </form>

    </div>
@endsection

@section('scripts')
    <script>
        const memberIdInput = document.getElementById('memberId');
        const memberNameInput = document.getElementById('memberName');
        let lookupTimer;

        memberIdInput.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            memberNameInput.value = '';

            if (!memberIdInput.value.trim()) {
                return;
            }

            lookupTimer = setTimeout(function () {
                fetch('{{ route('admin.investments.member-lookup') }}?member_id=' + encodeURIComponent(memberIdInput.value.trim()), {
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Member ID not found.');
                    }

                    return response.json();
                }).then(function (member) {
                    memberNameInput.value = member.member_name;
                }).catch(function () {
                    memberNameInput.value = '';
                });
            }, 250);
        });
    </script>
@endsection