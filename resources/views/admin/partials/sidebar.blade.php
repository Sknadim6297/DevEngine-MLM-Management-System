<aside id="desktopSidebar" class="sidebar desktop-sidebar">

    <div class="sidebar-logo">
        <img src="{{ asset('assets/img/logo.png') }}" class="sidebar-logo-image" alt="DevEngine Logo">
    </div>

    <div class="sidebar-menu">

        <a href="{{ route('dashboard') }}" class="active">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="#memberMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="memberMenu">

            <i class="bi bi-grid"></i>
            <span>Member</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="memberMenu">

            <a href="{{ route('admin.members.registration') }}">
                <i class="bi bi-person"></i>
                <span>Member Registration</span>
            </a>

            <a href="{{ route('admin.members.update') }}">
                <i class="bi bi-person-plus"></i>
                <span>View/Update Member Profile</span>
            </a>

            <a href="{{ route('admin.members.inactive') }}">
                <i class="bi bi-card-list"></i>
                <span>Inactive Member List</span>
            </a>

            <a href="{{ route('admin.members.active') }}">
                <i class="bi bi-person-check"></i>
                <span>Active Member List</span>
            </a>

        </div>

        <a href="#genealogyMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="genealogyMenu">

            <i class="bi bi-layers"></i>
            <span>Genealogy</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="genealogyMenu">

            <a href="{{ route('admin.genealogy.tree-view') }}">
                <i class="bi bi-diagram-3"></i>
                <span>Tree View</span>
            </a>

            <a href="{{ route('admin.genealogy.level-view') }}">
                <i class="bi bi-people"></i>
                <span>Level View</span>
            </a>

        </div>

        <a href="#activationWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="activationWalletMenu">

            <i class="bi bi-bar-chart"></i>
            <span>Activation Wallet</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="activationWalletMenu">

            <a href="{{ route('admin.activation-wallet.credit-entry') }}">
                <i class="bi bi-wallet2"></i>
                <span>Transfer to Activation Wallet Entry</span>
            </a>

            <a href="{{ route('admin.activation-wallet.credit-entry.list') }}">
                <i class="bi bi-arrow-right-circle"></i>
                <span>Transfer to Activation Wallet from admin list</span>
            </a>

            <a href="{{ asset('admin2/Debit5.html') }}">
                <i class="bi bi-person-lines-fill"></i>
                <span>Force Debit from activation wallet entry</span>
            </a>

            <a href="{{ asset('admin2/DebitList5.html') }}">
                <i class="bi bi-list-ul"></i>
                <span>Force Debit from activation wallet entry</span>
            </a>

            <a href="{{ asset('admin2/ActSummary.html') }}">
                <i class="bi bi-clock-history"></i>
                <span>Activation Wallet Summary</span>
            </a>

        </div>
        <a href="#investmentMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="investmentMenu">

            <i class="bi bi-pencil-square"></i>
            <span>Investment</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="investmentMenu">

            <a href="{{ route('admin.investments.entry') }}">
                <i class="bi bi-plus-circle"></i>
                <span>Investment Entry</span>
            </a>

            <a href="{{ route('admin.investments.active-investments') }}">
                <i class="bi bi-list-ul"></i>
                <span>Active Investment List</span>
            </a>

            <a href="{{ asset('admin2/ExpList.html') }}">
                <i class="bi bi-cash-stack"></i>
                <span>Closed Investment List</span>
            </a>

            <a href="{{ asset('admin2/WithdrawEntry.html') }}">
                <i class="bi bi-arrow-repeat"></i>
                <span>Investment Withdrawl Entry</span>
            </a>

            <a href="{{ asset('admin2/WithdrawList.html') }}">
                <i class="bi bi-clock-history"></i>
                <span>Investment Withdrawl List</span>
            </a>

        </div>

        <a href="#roiWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="roiWalletMenu">

            <i class="bi bi-pencil"></i>
            <span>ROI Wallet</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="roiWalletMenu">

            <a href="{{ asset('admin2/WithdrawRequest.html') }}">
                <i class="bi bi-wallet2"></i>
                <span>Pending List</span>
            </a>

            <a href="{{ asset('admin2/PaidList.html') }}">
                <i class="bi bi-arrow-down-circle"></i>
                <span>Paid List</span>
            </a>

            <a href="{{ asset('admin2/RejList.html') }}">
                <i class="bi bi-arrow-up-circle"></i>
                <span>Rrejected List</span>
            </a>

            <a href="{{ asset('admin2/Credit.html') }}">
                <i class="bi bi-arrow-left-right"></i>
                <span>Force Credit to ROI Wallet Entry</span>
            </a>

            <a href="{{ asset('admin2/CreditList.html') }}">
                <i class="bi bi-list-ul"></i>
                <span>Force Credit to ROI Wallet List</span>
            </a>

            <a href="{{ asset('admin2/Debit.html') }}">
                <i class="bi bi-clock-history"></i>
                <span>Force Debit from ROI Wallet Entry</span>
            </a>

            <a href="{{ asset('admin2/DebitList.html') }}">
                <i class="bi bi-exclamation-circle"></i>
                <span>Force Debit from ROI Wallet List</span>
            </a>

            <a href="{{ asset('admin2/TrList.html') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>Member ROI Wallet to ROI Wallet Transfer List</span>
            </a>

            <a href="{{ asset('admin2/LeaderList.html') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>Leaderwise ROI Wallet List</span>
            </a>

        </div>

        <a href="#workingWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="workingWalletMenu">

            <i class="bi bi-pie-chart"></i>
            <span>Working Wallet</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="workingWalletMenu">

            <a href="{{ asset('admin2/WithdrawRequest2.html') }}">
                <i class="bi bi-wallet2"></i>
                <span>Pending List</span>
            </a>

            <a href="{{ asset('admin2/PaidList2.html') }}">
                <i class="bi bi-arrow-down-circle"></i>
                <span>Paid List</span>
            </a>

            <a href="{{ asset('admin2/RejList2.html') }}">
                <i class="bi bi-arrow-up-circle"></i>
                <span>Rejected List</span>
            </a>

            <a href="{{ asset('admin2/Credit2.html') }}">
                <i class="bi bi-arrow-left-right"></i>
                <span>Force Credit to working wallet Entry</span>
            </a>

            <a href="{{ asset('admin2/CreditList2.html') }}">
                <i class="bi bi-person-lines-fill"></i>
                <span>Force Credit to working wallet Entry</span>
            </a>

            <a href="{{ asset('admin2/Debit2.html') }}">
                <i class="bi bi-list-ul"></i>
                <span>Force debit from working wallet Entry</span>
            </a>

            <a href="{{ asset('admin2/DebitList2.html') }}">
                <i class="bi bi-exclamation-circle"></i>
                <span>Force debit from working wallet List</span>
            </a>

            <a href="{{ asset('admin2/TrList2.html') }}">
                <i class="bi bi-clock-history"></i>
                <span>Member working wallet to working wallet Transfer List</span>
            </a>

            <a href="{{ asset('admin2/LeaderList2.html') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>Leaderwise working wallet list</span>
            </a>

        </div>

        <a href="#salaryWalletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="salaryWalletMenu">

            <i class="bi bi-table"></i>
            <span>Salary Wallet</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="salaryWalletMenu">

            <a href="{{ asset('admin2/WithdrawRequest3.html') }}">
                <i class="bi bi-wallet2"></i>
                <span>Pending List</span>
            </a>

            <a href="{{ asset('admin2/PaidList3.html') }}">
                <i class="bi bi-arrow-down-circle"></i>
                <span>Paid List</span>
            </a>

            <a href="{{ asset('admin2/RejList3.html') }}">
                <i class="bi bi-arrow-up-circle"></i>
                <span>Rejected List</span>
            </a>

            <a href="{{ asset('admin2/Credit4.html') }}">
                <i class="bi bi-arrow-left-right"></i>
                <span>Force credit to salary wallet entry</span>
            </a>

            <a href="{{ asset('admin2/CreditList4.html') }}">
                <i class="bi bi-person-lines-fill"></i>
                <span>Force credit to salary wallet List</span>
            </a>

            <a href="{{ asset('admin2/Debit4.html') }}">
                <i class="bi bi-list-ul"></i>
                <span>Force debit from salary wallet entry</span>
            </a>

            <a href="{{ asset('admin2/DebitList4.html') }}">
                <i class="bi bi-exclamation-circle"></i>
                <span>Force debit from salary wallet list</span>
            </a>

            <a href="{{ asset('admin2/TrList4.html') }}">
                <i class="bi bi-clock-history"></i>
                <span>Member salary wallet to salary wallet transfer list</span>
            </a>

            <a href="{{ asset('admin2/LeaderList3.html') }}">
                <i class="bi bi-file-earmark-text"></i>
                <span>Leaderwise salary wallet list</span>
            </a>

        </div>

        <a href="#reportsMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="reportsMenu">

            <i class="bi bi-search"></i>
            <span>Reports</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="reportsMenu">

            <a href="{{ route('admin.report.roi-report') }}">
                <i class="bi bi-person-lines-fill"></i>
                <span>ROI</span>
            </a>

            <a href="{{ route('admin.report.level-income') }}">
                <i class="bi bi-wallet2"></i>
                <span>Level Income</span>
            </a>

            <a href="{{ route('admin.report.salary') }}">
                <i class="bi bi-cash-stack"></i>
                <span>Salary</span>
            </a>

            <a href="{{ route('admin.report.rank-achievement') }}">
                <i class="bi bi-bar-chart"></i>
                <span>Rank Achievement Report</span>
            </a>

        </div>

        <a href="#notificationMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="notificationMenu">

            <i class="bi bi-bell"></i>
            <span>Suport</span>

            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="notificationMenu">

            <a href="{{ route('admin.support.pending-tickets') }}">
                <i class="bi bi-headset"></i>
                <span>Pending Ticket List</span>
            </a>

            <a href="{{ route('admin.support.applied-tickets') }}">
                <i class="bi bi-bell"></i>
                <span>Applied Ticket List</span>
            </a>

        </div>

        <a href="{{ route('admin.change-password.index') }}">
            <i class="bi bi-exclamation-circle"></i>
            <span>Change Password</span>
        </a>

        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2 w-100 text-start" style="color: #606770; font-size: 13px; padding: 11px 15px !important; border-radius: 5px;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </form>

    </div>

</aside>
