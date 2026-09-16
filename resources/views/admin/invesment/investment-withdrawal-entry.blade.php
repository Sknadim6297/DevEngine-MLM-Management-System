@extends('admin.layouts.app')

@section('content')
        <main class="dashboard-content">

    
           
           

    <div class="form-card">

       

        <div class="row">

            <div class="col-md-6 mb-3">
                <label>Member ID</label>
                <input type="text" class="form-control" placeholder="Enter Member ID">
            </div>

            <div class="col-md-6 mb-3">
                <label>Member Name</label>
                <input type="text" class="form-control" placeholder="Enter Member Name">
            </div>

            <div class="col-md-6 mb-3">
                <label>Investment ID</label>
                <input type="text" class="form-control" placeholder="Enter Sponsor ID">
            </div>

          
            <div class="col-md-6 mb-3">
                <label>Investment Amount (USDT)</label>
                <input type="text" class="form-control" placeholder="Enter Mobile No">
            </div>

        </div>

        <div class="text-end mt-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-circle"></i>
                Submit
            </button>
        </div>

    </div>


    

    


       </main>
@endsection