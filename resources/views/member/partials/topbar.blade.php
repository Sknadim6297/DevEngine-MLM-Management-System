<header class="top-navbar">

    <button class="menu-toggle" type="button" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="d-flex align-items-center gap-3 ms-3">
        <span class="fw-semibold">Welcome back, {{ $member->member_name }}!</span>

        <form action="{{ route('member.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-box-arrow-right"></i>
                logout
            </button>
        </form>
    </div>

</header>