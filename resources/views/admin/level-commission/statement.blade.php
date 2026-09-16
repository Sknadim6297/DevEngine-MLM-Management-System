@extends('admin.layouts.app')

@section('content')
    <main class="dashboard-content">
        <div class="form-card">
            <div class="form-title">
                <h4>Level Commission Statement</h4>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Commission Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td>Level {{ $rate['level'] }}</td>
                                <td>{{ $rate['percentage'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
