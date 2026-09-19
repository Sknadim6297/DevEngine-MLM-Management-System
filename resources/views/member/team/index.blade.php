@extends('member.layouts.app')

@section('title', 'Member Team')

@section('content')
    <main class="member-content">
        <div class="form-card">
            <div class="form-title d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h4 class="mb-1">Member List</h4>
                    <div class="text-muted small">Your member network</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('member.team.direct') }}" class="btn {{ $view === 'direct' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Direct Members ({{ $directMembers->count() }})
                    </a>
                    <a href="{{ route('member.team.whole') }}" class="btn {{ $view === 'whole' ? 'btn-primary' : 'btn-outline-primary' }}">
                        Whole Team ({{ $wholeTeam->count() }})
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="summary-card card-blue h-100">
                        <div class="summary-number">{{ $directMembers->count() }}</div>
                        <div class="summary-name">Direct Members</div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="summary-card card-purple h-100">
                        <div class="summary-number">{{ $wholeTeam->count() }}</div>
                        <div class="summary-name">Whole Team</div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table align-middle">
                    <thead>
                        <tr>
                            @if ($view === 'whole')
                                <th>Level</th>
                            @endif
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Sponsor ID</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Joining Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($members = $view === 'direct' ? $directMembers : $wholeTeam)
                        @forelse ($members as $teamMember)
                            <tr>
                                @if ($view === 'whole')
                                    <td>Level {{ $teamMember['level'] }}</td>
                                @endif
                                <td>{{ $teamMember['member_id'] }}</td>
                                <td>{{ $teamMember['member_name'] }}</td>
                                <td>{{ $teamMember['sponsor_id'] }}</td>
                                <td>{{ $teamMember['email'] }}</td>
                                <td>{{ $teamMember['mobile_no'] }}</td>
                                <td>
                                    <span class="badge {{ strtolower($teamMember['status']) === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ ucfirst($teamMember['status']) }}
                                    </span>
                                </td>
                                <td>{{ $teamMember['created_at']?->format('d-M-Y') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $view === 'whole' ? 8 : 7 }}" class="text-center">No team members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
