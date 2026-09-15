@extends('admin.layouts.app')

@section('content')
   
        <main class="dashboard-content">

    
           
           

    <div class="form-card">

        <!-- TITLE -->
        


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
                <label>From Transfer Date</label>

                <input type="date"
                      
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Transfer Date</label>

                <input type="date"
                       
                       class="form-control">
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div class="d-flex justify-content-end gap-2 mb-4">

            <button type="button"
                    class="btn btn-primary"
                    ">

                <i class="bi bi-file-earmark-excel"></i>
                Export to Excel

            </button>


            <button type="button"
                    class="btn btn-primary"
                    onclick="searchInvestment()">

                <i class="bi bi-search"></i>
                Search

            </button>


            <button type="button"
                    class="btn btn-primary"
                   ">

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset

            </button>

        </div>

          <div class="col-md-2 mb-3">

                <div class="total-amount-box">

                    <span>Total Amount (USDT)</span>

                    <strong>160838.6922</strong>

                </div>

            </div>
        <!-- TABLE -->
        <div class="table-responsive">

    <table class="table table-bordered table-hover member-table">

        <thead>
            <tr>
                <th>Serial No</th>
                <th>To Member ID</th>
                <th>To Member Name</th>
                <th>Amount (USDT)</th>
                <th>Transfer Date</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>1</td>
                <td>MB10001</td>
                <td>Rahul Das</td>
                <td>500.0000</td>
                <td>20-Aug-2026</td>
            </tr>

            <tr>
                <td>2</td>
                <td>MB10002</td>
                <td>Sumit Ghosh</td>
                <td>750.0000</td>
                <td>21-Aug-2026</td>
            </tr>

            <tr>
                <td>3</td>
                <td>MB10003</td>
                <td>Priya Das</td>
                <td>1000.0000</td>
                <td>22-Aug-2026</td>
            </tr>

        </tbody>

    </table>

</div>


        <!-- PAGINATION -->
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

            <div class="table-info">
                Showing 1 to 3 of 3 entries
            </div>

            <nav>

                <ul class="pagination pagination-sm mb-0">

                    <li class="page-item disabled">
                        <a class="page-link" href="#">Previous</a>
                    </li>

                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>

                    <li class="page-item disabled">
                        <a class="page-link" href="#">Next</a>
                    </li>

                </ul>

            </nav>

        </div>

    </div>


    

    


       </main>

@endsection