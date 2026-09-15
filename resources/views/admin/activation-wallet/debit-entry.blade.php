@extends('admin.layouts.app')

@section('content')

        <main class="dashboard-content">

    

    <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Force Debit from Activation Wallet</h4>
        </div>

        <div class="row">

            <!-- MEMBER ID -->
            <div class="col-md-6 mb-3">
                <label>Member ID</label>

                <input type="text"
                       
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div class="col-md-6 mb-3">
                <label>Member Name</label>

                <input type="text"
                      
                       class="form-control"
                       placeholder="Enter Member Name">
            </div>


            <!-- REMARKS -->
            <div class="col-md-6 mb-3">
                <label>Remarks</label>

                <textarea 
                          class="form-control"
                          rows="3"
                          placeholder="Enter Remarks"></textarea>
            </div>


            <!-- WALLET AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>Activation Wallet Amount (USDT)</label>

                <input type="text"
                       
                       class="form-control"
                       placeholder="Activation Wallet Amount"
                       readonly>
            </div>


            <!-- DEBIT AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>Debit Amount (USDT)</label>

                <input type="number"
                       
                       class="form-control"
                       placeholder="Enter Debit Amount">
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="text-end mt-2">

            <button type="button"
                    class="btn btn-primary px-4"
                    >

                <i class="bi bi-dash-circle"></i>
                Submit

            </button>

            <button type="button"
                    class="btn btn-primary px-4 ms-2"
                    >

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>

    </div>


</main>
@endsection