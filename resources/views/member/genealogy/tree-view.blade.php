@extends('member.layouts.app')
@section('content')
   <main class="dashboard-content">

    
          

    <div class="form-card">

        <!-- TITLE -->
        <div class="form-title">
            <h4>Tree View</h4>
        </div>

        <!-- ID SEARCH -->
        <div class="row align-items-end mb-4">

            <div class="col-md-6">
                <label>ID <span class="text-danger">*</span></label>

                <form method="GET" action="{{ route('member.genealogy.tree-view') }}">
                    <input type="text"
                           class="form-control"
                           id="memberTreeId"
                           name="member_id"
                           value="{{ $selectedMemberId ?? '' }}"
                           placeholder="Enter Member ID"
                           data-member-autocomplete
                           autocomplete="off">
                </form>
            </div>

            <div class="col-md-auto mt-3 mt-md-0">

                <button type="button"
                        class="btn btn-primary px-4"
                        onclick="document.getElementById('memberTreeId').closest('form').submit();">

                    <i class="bi bi-search"></i>
                    Search

                </button>

            </div>

        </div>


        <!-- TREE -->
        <div class="tree-container" id="treeContainer">
            @if (($memberNotFound ?? false))
                <div class="alert alert-danger">Member not found.</div>
            @elseif (empty($tree))
                <div class="alert alert-info">No members found.</div>
            @else
                <ul class="member-tree">
                    @foreach ($tree as $node)
                        @include('member.genealogy.partials.tree-node', ['node' => $node])
                    @endforeach
                </ul>
            @endif
        </div>

    </div>



       </main>
 @endsection