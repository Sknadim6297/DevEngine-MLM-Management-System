@extends('admin.layouts.app')

@section('content')
      <main class="dashboard-content">

    
          

    <div class="form-card">

        <!-- PAGE TITLE -->
        <div class="form-title">
            <h4>Withdrawn Investment List</h4>
        </div>

        <!-- SEARCH AREA -->
        <div class="row align-items-end">

            <!-- MEMBER ID -->
            <div class="col-md-4 mb-3">
                <label>Member ID</label>

                <input type="text"
                     
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- FROM DATE -->
            <div class="col-md-3 mb-3">
                <label>From Withdraw Date</label>

                <input type="date"
                      
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Withdraw Date</label>

                <input type="date"
                       
                       class="form-control">
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>
        <div class="col-md-2 mb-3">

                <div class="total-amount-box">

                    <span>Total Amount (USDT)</span>

                    <strong>160838.6922</strong>

                </div>

            </div>

        <!-- TABLE -->
        <div class="table-responsive">
    <table class="table custom-table align-middle">
        <thead>
            <tr>
                <th>Serial No</th>
                <th>Member ID</th>
                <th>Name</th>
                <th>Investment Id</th>
                <th>Investment Amount (USDT)</th>
                <th>Investment Date</th>
                <th>Withdraw Date</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>1</td>
                <td>MEM001</td>
                <td>John Doe</td>
                <td>INV001</td>
                <td>500 USDT</td>
                <td>26-08-2026</td>
                <td>26-09-2026</td>
            </tr>

            <tr>
                <td>2</td>
                <td>MEM002</td>
                <td>Rahul Das</td>
                <td>INV002</td>
                <td>1,000 USDT</td>
                <td>25-08-2026</td>
                <td>25-09-2026</td>
            </tr>
        </tbody>
    </table>
</div>


        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

            <div class="table-info">
                Showing 1 to 5 of 25 entries
            </div>

            <nav>
                <ul class="pagination pagination-sm mb-0">

                    <li class="page-item disabled">
                        <a class="page-link" href="#">Previous</a>
                    </li>

                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">4</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">5</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>

                </ul>
            </nav>

        </div>

    </div>


    


       </main>
@endsection