<aside id="desktopSidebar" class="sidebar desktop-sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('assets/img/logo.png') }}" class="sidebar-logo-image" alt="Bright Stars">
    </div>
    <!-- give some alignment best visibility for the welcome message -->
     <div class="mb-2 fw-semibold text-center">Welcome</div>
     <div class="small text-muted text-center">{{ $member->member_id }}</div>

    <div class="sidebar-menu">
        <a href="{{ route('member.dashboard') }}" class="active">
            <i class="bi bi-house-door-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="#memberMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="memberMenu">
            <i class="bi bi-person"></i>
            <span>Member</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

     <div class="collapse submenu" id="memberMenu">

            <a href="{{ route('member.registration') }}">
                <i class="bi bi-person"></i>
                <span>New Member Registration</span>
            </a>

            <a href="{{ route('member.profile') }}">
                <i class="bi bi-person-plus"></i>
                <span>View Profile</span>
            </a>

            <a href="{{ route('member.team.direct') }}">
                <i class="bi bi-card-list"></i>
                <span>Direct Member List</span>
            </a>

            <a href="{{ route('member.team.whole') }}">
                <i class="bi bi-person-check"></i>
                <span>Team Member List</span>
            </a>

        </div>

        <a href="#genealogyMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="genealogyMenu">
            <i class="bi bi-diagram-3"></i>
            <span>Genealogy</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="genealogyMenu">
            <a href="{{ route('member.dashboard') }}#genealogy">
                <i class="bi bi-diagram-3"></i>
                <span>Network Overview</span>
            </a>
        </div>

        <a href="#walletMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="walletMenu">
            <i class="bi bi-wallet2"></i>
            <span>Wallets</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="walletMenu">
            <a href="{{ route('member.dashboard') }}#activation-wallet">
                <i class="bi bi-wallet2"></i>
                <span>Activation Wallet</span>
            </a>
            <a href="{{ route('member.dashboard') }}#roi-wallet">
                <i class="bi bi-pie-chart"></i>
                <span>ROI Wallet</span>
            </a>
            <a href="{{ route('member.dashboard') }}#working-wallet">
                <i class="bi bi-wallet"></i>
                <span>Working Wallet</span>
            </a>
            <a href="{{ route('member.dashboard') }}#salary-wallet">
                <i class="bi bi-cash-stack"></i>
                <span>Salary Wallet</span>
            </a>
        </div>

        <a href="#activityMenu" data-bs-toggle="collapse" role="button" aria-expanded="false"
            aria-controls="activityMenu">
            <i class="bi bi-bar-chart"></i>
            <span>Activity</span>
            <i class="bi bi-chevron-down ms-auto submenu-arrow"></i>
        </a>

        <div class="collapse submenu" id="activityMenu">
            <a href="{{ route('member.dashboard') }}#investment">
                <i class="bi bi-bar-chart"></i>
                <span>Investment</span>
            </a>
            <a href="{{ route('member.dashboard') }}#reports">
                <i class="bi bi-file-earmark-text"></i>
                <span>Reports</span>
            </a>
        </div>

        <a href="{{ route('member.dashboard') }}#support">
            <i class="bi bi-headset"></i>
            <span>Support</span>
        </a>

        <form action="{{ route('member.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link text-decoration-none p-0 d-flex align-items-center gap-2 w-100 text-start" style="color: #606770; font-size: 13px; padding: 11px 15px !important; border-radius: 5px;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Log Out</span>
            </button>
        </form>

    </div>

</aside>
