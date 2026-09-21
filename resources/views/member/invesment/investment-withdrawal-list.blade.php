@extends('admin.layouts.app')

@section('content')
      <main class="dashboard-content">

    
          

    <div class="form-card">

        <!-- PAGE TITLE -->
        <div class="form-title">
            <h4>Withdrawn Investment List</h4>
        </div>

        <!-- SEARCH AREA -->
        <form method="GET" action="{{ route('admin.investments.investment-withdrawal-list') }}" id="investmentWithdrawalListForm">
        <div class="row align-items-end">

            <!-- MEMBER ID -->
            <div class="col-md-4 mb-3">
                <label>Member ID</label>

                <input type="text"
                      name="member_id"
                      value="{{ request('member_id') }}"
                       class="form-control"
                       placeholder="Enter Member ID">
            </div>


            <!-- FROM DATE -->
            <div class="col-md-3 mb-3">
                <label>From Withdraw Date</label>

                <input type="date"
                      name="from_date"
                      value="{{ request('from_date') }}"
                       class="form-control">
            </div>


            <!-- TO DATE -->
            <div class="col-md-3 mb-3">
                <label>To Withdraw Date</label>

                <input type="date"
                      name="to_date"
                      value="{{ request('to_date') }}"
                       class="form-control">
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
                Search
            </button>
            <button type="button" class="btn btn-primary" onclick="window.location.href='{{ route('admin.investments.investment-withdrawal-list') }}'">
                <i class="bi bi-arrow-counterclockwise"></i>
                Reset
            </button>
        </div>
        </form>
        <div class="col-md-2 mb-3">

                <div class="total-amount-box">

                    <span>Total Amount (USDT)</span>

                    <strong>{{ rtrim(rtrim(number_format((float) $totalAmount, 4, '.', ''), '0'), '.') ?: '0' }}</strong>
        <tbody>
            @forelse ($withdrawals as $withdrawal)
                <tr>
                    <td>{{ $withdrawals->firstItem() + $loop->index }}</td>
                    <td>{{ $withdrawal->member_id }}</td>
                    <td>{{ $withdrawal->member_name }}</td>
                    <td>{{ $withdrawal->investment_id }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $withdrawal->withdrawal_amount, 4, '.', ''), '0'), '.') ?: '0' }} USDT</td>
                    <td>{{ $withdrawal->investment?->created_at?->format('d-m-Y') }}</td>
                    <td>{{ $withdrawal->withdrawn_at?->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No investment withdrawals found.</td></tr>
            @endforelse
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

            <nav>
                {{ $withdrawals->links('pagination::bootstrap-5') }}
            </nav>

        </div>

    </div>


    


       </main>
@endsection