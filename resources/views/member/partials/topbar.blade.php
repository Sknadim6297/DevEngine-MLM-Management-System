<header class="top-navbar">

    <button class="menu-toggle" type="button" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="d-flex align-items-center gap-3 ms-3">
        <span class="fw-semibold">Welcome back, {{ $member->member_name }}!</span>
        <a href="{{ route('member.dashboard') }}#investment" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>
            Invest
        </a>
    </div>

</header>
