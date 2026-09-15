@extends('admin.layouts.app')

@section('content')

        <main class="dashboard-content">

    
          

     <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Levelwise Members</h4>
        </div>

        <!-- SEARCH FORM -->
        <div class="row align-items-end">

            <!-- MEMBER ID -->
            <div class="col-md-5 mb-3">
                <label>
                    Member ID
                </label>

                <input type="text"
                       id="levelMemberId"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- LEVEL -->
            <div class="col-md-4 mb-3">

                <label>
                    Level
                </label>

               <input type="text"
                       id="Text1"
                       class="form-control"
                       placeholder="Enter Level">

            </div>


            <!-- BUTTONS -->
            <div class="col-md-3 mb-3">

                <div class="d-flex gap-2">

                    <button type="button"
                            class="btn btn-primary"
                           >

                        <i class="bi bi-search"></i>
                        Search

                    </button>


                    <button type="button"
                            class="btn btn-secondary"
                           >

                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset

                    </button>

                </div>

            </div>

        </div>

    </div>



       </main>
@endsection
