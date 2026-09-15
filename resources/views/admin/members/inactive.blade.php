     
    @extends('admin.layouts.app')

@section('content')
    <main class="member-content">
        <div class="form-card">
            <div class="form-title">
                <h4>Inactive Member List</h4>
            </div>

            <form method="GET" action="{{ route('admin.members.inactive') }}" class="row align-items-end mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <label>Member Name <span class="text-danger">*</span></label>
                    <input type="text" id="memberName" name="member_name" value="{{ $memberName }}" class="form-control" placeholder="Enter Member Name">
                </div>

                <div class="col-md-4 mb-3 mb-md-0">
                    <label>Member ID <span class="text-danger">*</span></label>
                    <input type="text" id="memberId" name="member_id" value="{{ $memberId }}" class="form-control" placeholder="Enter Member ID">
                </div>

                <div class="col-md-2 mb-3 mb-md-0">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                        Search
                    </button>
                </div>

                <div class="col-md-2">
                    <button type="button" class="btn btn-success w-100" onclick="window.location.href='{{ route('admin.members.export', ['status' => 'inactive']) }}?' + new URLSearchParams(new FormData(this.form)).toString()">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table" id="memberTable">
                    <thead>
                        <tr>
                            <th>Serial No</th>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Joining Date</th>
                            <th>Sponsor ID</th>
                            <th>Sponsor Name</th>
                            <th>Mobile No</th>
                            <th>PAN Card Number</th>
                            <th>Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr>
                                <td>{{ $member['serial'] }}</td>
                                <td>{{ $member['member_id'] }}</td>
                                <td>{{ $member['name'] }}</td>
                                <td>{{ $member['joining_date'] }}</td>
                                <td>{{ $member['sponsor_id'] }}</td>
                                <td>{{ $member['sponsor_name'] }}</td>
                                <td>{{ $member['mobile'] }}</td>
                                <td>{{ $member['pan_card_no'] }}</td>
                                <td>
                                    <span class="password-value" data-password="{{ $member['password'] }}">******</span>
                                    <button type="button" class="btn btn-link p-0 ms-1 password-toggle" aria-label="Show password" title="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No inactive members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="table-info">
                    Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ $members->total() }} inactive members
                </div>

                <nav>
                    {{ $members->links('pagination::bootstrap-5') }}
                </nav>
            </div>
        </div>
    </main>
@endsection

@section('scripts')
    <script>
        document.querySelectorAll('.password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const value = button.previousElementSibling;
                const visible = value.textContent !== '******';
                value.textContent = visible ? '******' : value.dataset.password;
                button.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
                button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                button.setAttribute('title', visible ? 'Show password' : 'Hide password');
            });
        });
    </script>
@endsection