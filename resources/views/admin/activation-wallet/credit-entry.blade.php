@extends('admin.layouts.app')

@section('content') 
    <main class="dashboard-content">

    

    <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Transfer to Member Activation Wallet</h4>
        </div>

        <div class="row">

            <!-- MEMBER ID -->
            <div class="col-md-6 mb-3">
                <label>
                    Member ID
                </label>

                <input type="text"
                      
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- MEMBER NAME -->
            <div class="col-md-6 mb-3">
                <label>
                    Member Name
                </label>

                <input type="text"
                     
                       class="form-control"
                       placeholder="Enter Member Name">
            </div>


            <!-- ACTIVATION WALLET AMOUNT -->
            <div class="col-md-6 mb-3">
                <label>
                    Activation Wallet Amount (USDT)
                </label>

                <input type="text"
                       
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
                       class="form-control"
                       placeholder="Enter Amount">
            </div>

        </div>


        <!-- BUTTONS -->
        <div class="text-end mt-2">

            <button type="button"
                    class="btn btn-primary px-4"
                   >

                <i class="bi bi-arrow-right-circle"></i>
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