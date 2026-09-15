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

        <!-- TITLE -->
        <div class="form-title">
            <h4>Transfer to Member Activation Wallet</h4>
        </div>

        <form method="POST" action="{{ route('admin.activation-wallet.store-credit-entry') }}" id="activationWalletForm">
            @csrf
        <div class="row">

            <!-- MEMBER ID -->
            <div class="col-md-6 mb-3">
                <label>
                    Member ID
                </label>

                <input type="text"
                      id="memberId"
                      name="member_id"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div class="col-md-6 mb-3">
                <label>
                    Member Name
                </label>

                <input type="text"
                      id="memberName"
                      name="member_name"
                       class="form-control"
                      placeholder="Enter Member Name"
                      readonly>
            </div>


            <!-- ACTIVATION WALLET AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>
                    Activation Wallet Amount (USDT)
                </label>

                <input type="text"
                      id="walletAmount"
                      name="wallet_amount"
                       class="form-control"
                       placeholder="Activation Wallet Amount"
                       readonly>
            </div>


            <!-- TRANSFER AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>
                    Amount Want to Transfer (USDT)
                </label>

                <input type="number"
                       id="transferAmount"
                      name="amount"
                       class="form-control"
                       placeholder="Enter Amount">
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="text-end mt-2">

            <button type="button"
                    id="fetchMemberDetails"
                    class="btn btn-primary px-4"
                   >

                <i class="bi bi-arrow-right-circle"></i>
                Submit

            </button>

                    <button type="reset"
                    id="resetActivationWalletForm"
                    class="btn btn-primary px-4 ms-2"
                    >

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>
        </form>

    </div>


</main>
@endsection

@section('scripts')
    <script>
        const activationWalletForm = document.getElementById('activationWalletForm');
        const memberIdInput = document.getElementById('memberId');
        const memberNameInput = document.getElementById('memberName');
        const walletAmountInput = document.getElementById('walletAmount');
        const transferAmountInput = document.getElementById('transferAmount');
        const fetchMemberDetailsButton = document.getElementById('fetchMemberDetails');
        const resetButton = document.getElementById('resetActivationWalletForm');
        const actionButton = fetchMemberDetailsButton;
        let fetchedMemberId = null;

        function setError(field, message) {
            let error = document.getElementById(field + 'Error');
            if (!error) {
                error = document.createElement('div');
                error.id = field + 'Error';
                error.className = 'text-danger mt-1 small validation-message';
                document.getElementById(field).parentElement.appendChild(error);
            }
            error.textContent = message || '';
            error.style.display = message ? 'block' : 'none';
        }

        function formatAmount(value) {
            return (Number(value) || 0).toFixed(4).replace(/\.?0+$/, '') || '0';
        }

        function clearMemberDetails() {
            fetchedMemberId = null;
            memberNameInput.value = '';
            walletAmountInput.value = '';
            actionButton.type = 'button';
            actionButton.innerHTML = '<i class="bi bi-arrow-right-circle"></i> Submit';
        }

        function validateMemberId() {
            const value = memberIdInput.value.trim();
            if (!value) {
                setError('memberId', 'Member ID is required.');
                return false;
            }
            setError('memberId', '');
            return true;
        }

        function validateTransferAmount() {
            const value = transferAmountInput.value.trim();
            if (!value) {
                setError('transferAmount', 'Transfer amount is required.');
                return false;
            }
            if (!/^\d+(\.\d{1,4})?$/.test(value) || Number(value) <= 0) {
                setError('transferAmount', 'Enter a valid transfer amount greater than 0.');
                return false;
            }
            setError('transferAmount', '');
            return true;
        }

        memberIdInput.addEventListener('input', function () {
            clearMemberDetails();
            validateMemberId();
        });
        memberIdInput.addEventListener('blur', validateMemberId);
        transferAmountInput.addEventListener('input', validateTransferAmount);
        transferAmountInput.addEventListener('blur', validateTransferAmount);

        fetchMemberDetailsButton.addEventListener('click', function (event) {
            if (fetchedMemberId && fetchedMemberId === memberIdInput.value.trim()) {
                return;
            }

            event.preventDefault();
            clearMemberDetails();
            if (!validateMemberId()) return;

            fetch('{{ route('admin.activation-wallet.credit-entry.member-lookup') }}?member_id=' + encodeURIComponent(memberIdInput.value.trim()), {
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) throw new Error(data.message || 'Member not found.');
                    return data;
                });
            }).then(function (member) {
                memberIdInput.value = member.member_id;
                memberNameInput.value = member.member_name;
                walletAmountInput.value = formatAmount(member.activation_wallet_amount);
                fetchedMemberId = member.member_id;
                actionButton.type = 'submit';
                actionButton.innerHTML = '<i class="bi bi-arrow-right-circle"></i> Transfer';
                setError('memberId', '');
            }).catch(function (error) {
                clearMemberDetails();
                setError('memberId', error.message || 'Member not found.');
            });
        });

        resetButton.addEventListener('click', function () {
            activationWalletForm.reset();
            clearMemberDetails();
            setError('memberId', '');
            setError('transferAmount', '');
        });

        activationWalletForm.addEventListener('submit', function (event) {
            if (!fetchedMemberId || fetchedMemberId !== memberIdInput.value.trim() || !validateTransferAmount()) {
                event.preventDefault();
                if (!fetchedMemberId) setError('memberId', 'Fetch a valid member before transferring.');
                return;
            }
            actionButton.disabled = true;
        });
    </script>
@endsection