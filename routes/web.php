<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MemberController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\ActivationWalletController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\genealogyController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\LevelCommissionController;
use App\Http\Controllers\Member\NewMemberRegistrationController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\TeamController;
use App\Http\Controllers\Member\InvestController;
use App\Http\Controllers\PublicMemberAuthController;
use App\Models\Member;
use App\Models\User;


Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }

    if (session()->has('member_context_id')) {
        return redirect()->route('member.dashboard');
    }

    return redirect('/admin/login');
});


/*
|--------------------------------------------------------------------------
| Admin Login
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }

    if (session()->has('member_context_id')) {
        return redirect()->route('member.dashboard');
    }

    return view('admin.login.login');
})->name('login');

Route::get('/register', [PublicMemberAuthController::class, 'register'])
    ->name('member.register');

Route::post('/register', [PublicMemberAuthController::class, 'storeRegistration'])
    ->middleware('throttle:20,1')
    ->name('member.register.store');

Route::get('/register/check-sponsor', [PublicMemberAuthController::class, 'checkSponsor'])
    ->middleware('throttle:30,1')
    ->name('member.register.check-sponsor');

Route::get('/forgot-password', [PublicMemberAuthController::class, 'forgot'])
    ->name('member.forgot');

Route::post('/forgot-password/send-otp', [PublicMemberAuthController::class, 'sendOtp'])
    ->middleware('throttle:3,10')
    ->name('member.forgot.send');

Route::post('/forgot-password/resend-otp', [PublicMemberAuthController::class, 'resendOtp'])
    ->middleware('throttle:3,10')
    ->name('member.forgot.resend');

Route::get('/forgot-password/verify', [PublicMemberAuthController::class, 'verifyForm'])
    ->name('member.forgot.verify');

Route::post('/forgot-password/verify', [PublicMemberAuthController::class, 'verifyOtp'])
    ->middleware('throttle:10,10')
    ->name('member.forgot.verify.store');

Route::get('/forgot-password/reset', [PublicMemberAuthController::class, 'resetForm'])
    ->name('member.forgot.reset');

Route::post('/forgot-password/reset', [PublicMemberAuthController::class, 'resetPassword'])
    ->name('member.forgot.reset.store');


Route::post('/admin/login', function (Request $request) {

    $credentials = $request->validate([
        'member_id' => ['required_without:email', 'nullable', 'string'],
        'email' => ['required_without:member_id', 'nullable', 'email'],
        'password' => ['required', 'string'],
    ]);

    $memberId = trim($credentials['member_id'] ?? $credentials['email']);

    $member = Member::where('member_id', $memberId)
        ->where('status', 'active')
        ->first();

    if ($member && $member->password && Hash::check($credentials['password'], $member->password)) {
        $request->session()->regenerate();
        $request->session()->put('member_context_id', $member->member_id);

        return redirect()->intended(route('member.dashboard'));
    }

    if (strtoupper($memberId) === 'ST666666') {
        $admin = User::where('is_admin', true)->first();

        if ($admin && Hash::check($credentials['password'], $admin->password)) {
            Auth::login($admin, $request->boolean('remember'));
            $request->session()->regenerate();
            $request->session()->forget('member_context_id');

            return redirect()->intended('/dashboard');
        }
    }

    if (
        Auth::attempt(
            [
                'email' => $memberId,
                'password' => $credentials['password'],
            ],
            $request->boolean('remember')
        )
    ) {
        $request->session()->regenerate();
        $request->session()->forget('member_context_id');

        return redirect()->intended('/dashboard');
    }

    return back()
        ->withErrors([
            $request->filled('member_id') ? 'member_id' : 'email' => 'Invalid Member ID or password.',
        ])
        ->onlyInput($request->filled('member_id') ? 'member_id' : 'email');

})->middleware('throttle:5,1')->name('login.submit');


/*
|--------------------------------------------------------------------------
| Authenticated Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/member-panel/{member_id}', [MemberController::class, 'memberPanel'])
        ->name('admin.member-panel');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function (Request $request) {
        $request->session()->forget('member_context_id');

        return view('admin.dashboard.index', [
            'activationWalletTotal' => (float) \App\Models\Member::sum('activation_wallet_amount'),
            'workingWalletTotal' => (float) \App\Models\Member::sum('working_wallet_amount'),
            'roiWalletTotal' => (float) \App\Models\Member::sum('roi_wallet_amount'),
        ]);
    })->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/admin/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('admin.logout');


    /*
    |--------------------------------------------------------------------------
    | Members
    |--------------------------------------------------------------------------
    */

    Route::controller(MemberController::class)
        ->prefix('admin/members')
        ->name('admin.members.')
        ->group(function () {

            Route::get('/active', 'active')->name('active');

            Route::get('/inactive', 'inactive')->name('inactive');

            Route::get('/export/{status}', 'export')->name('export');

            Route::get('/registration', 'registration')->name('registration');

            Route::get('/update', 'update')->name('update');

            Route::get(
                '/fetch-details',
                'fetchMemberDetails'
            )->name('fetch-details');

            Route::get(
                '/check-member-id',
                'checkMemberIdAvailability'
            )->name('check-member-id');

            Route::get(
                '/check-sponsor-id',
                'checkSponsorIdAvailability'
            )->name('check-sponsor-id');

            Route::post('/store', 'store')->name('store');

            Route::post(
                '/update-member',
                'updateMember'
            )->name('update-member');
        });


    /*
    |--------------------------------------------------------------------------
    | Investments
    |--------------------------------------------------------------------------
    */

    Route::controller(InvestmentController::class)
        ->prefix('admin/investments')
        ->name('admin.investments.')
        ->group(function () {

            Route::get('/entry', 'investmentEntry')
                ->name('entry');

            Route::get('/member-lookup', 'memberLookup')
                ->name('member-lookup');

            Route::post('/store', 'storeInvestment')
                ->name('store');

            Route::get(
                '/investment-withdrawal-entry',
                'investmentWithdrawalEntry'
            )->name('investment-withdrawal-entry');

            Route::get(
                '/investment-withdrawal-lookup',
                'investmentWithdrawalLookup'
            )->name('investment-withdrawal-lookup');

            Route::get(
                '/investment-withdrawal-investment-lookup',
                'investmentWithdrawalInvestmentLookup'
            )->name('investment-withdrawal-investment-lookup');

            Route::post(
                '/investment-withdrawal-entry',
                'storeInvestmentWithdrawal'
            )->name('investment-withdrawal-store');
          
            Route::get(
                '/investment-withdrawal-list',
                'investmentWithdrawalList'
            )->name('investment-withdrawal-list');


            Route::get(
                '/active-investments',
                'activeInvestments'
            )->name('active-investments');

            Route::get(
                '/active-investments/export',
                'exportActiveInvestments'
            )->name('active-investments.export');

            Route::get(
                '/closed-investments/export',
                'exportClosedInvestments'
            )->name('closed-investments.export');

            Route::get(
                '/closed-investments',
                'closedInvestments'
            )->name('closed-investments');
        });


    /*
    |--------------------------------------------------------------------------
    | Activation Wallet
    |--------------------------------------------------------------------------
    */

    Route::controller(ActivationWalletController::class)
        ->prefix('admin/activation-wallet')
        ->name('admin.activation-wallet.')
        ->group(function () {

            Route::get(
                '/credit-entry',
                'creditEntry'
            )->name('credit-entry');

            Route::get(
                '/credit-entry/member-lookup',
                'memberLookup'
            )->name('credit-entry.member-lookup');

            Route::post(
                '/credit-entry',
                'storeCreditEntry'
            )->name('store-credit-entry');

            Route::get(
                '/credit-entry/export',
                'exportCreditEntries'
            )->name('credit-entry.export');

            Route::get(
                '/credit-entry/list',
                'listCreditEntries'
            )->name('credit-entry.list');

            Route::get(
                '/debit-entry',
                'debitEntry'
            )->name('debit-entry');

            Route::post(
                '/debit-entry',
                'storeDebitEntry'
            )->name('store-debit-entry');

            Route::get(
                '/debit-entry/list',
                'listDebitEntries'
            )->name('debit-entry.list');

            Route::get(
                '/summary/export',
                'exportSummary'
            )->name('summary.export');

            Route::get(
                '/summary',
                'summary'
            )->name('summary');
        });


    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    Route::controller(ChangePasswordController::class)
        ->prefix('admin/change-password')
        ->name('admin.change-password.')
        ->group(function () {

            Route::get(
                '/change-password',
                'index'
            )->name('index');

            Route::post(
                '/update-password',
                'update'
            )->name('update');
        });


    /*
    |--------------------------------------------------------------------------
    | Genealogy
    |--------------------------------------------------------------------------
    */

    Route::controller(genealogyController::class)
        ->prefix('admin/genealogy')
        ->name('admin.genealogy.')
        ->group(function () {

            Route::get(
                '/tree-view',
                'index'
            )->name('tree-view');
            Route::get(
                '/level-view',
                'levelView'
            )->name('level-view');
            Route::get(
                '/level-view/members',
                'levelMembers'
            )->name('level-view.members');
        });


    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    */

    Route::controller(SupportController::class)
        ->prefix('admin/support')
        ->name('admin.support.')
        ->group(function () {

            Route::get(
                '/pending-tickets',
                'pendingTickets'
            )->name('pending-tickets');

            Route::get(
                '/applied-tickets',
                'appliedTicketList'
            )->name('applied-tickets');

            Route::get(
                '/applied-tickets/export',
                'exportAppliedTickets'
            )->name('applied-tickets.export');
        });

    Route::get(
        '/admin/level-commission/statement',
        [LevelCommissionController::class, 'statement']
    )->name('admin.level-commission.statement');

    Route::post(
        '/admin/level-commission/statement/{levelCommission}',
        [LevelCommissionController::class, 'update']
    )->name('admin.level-commission.statement.update');

        /* 
    |--------------------------------------------------------------------------
    | Report
    |--------------------------------------------------------------------------
    */

    Route::controller(ReportController::class)
        ->prefix('admin/report')
        ->name('admin.report.')
        ->group(function () {

            Route::get(
                '/roi-report',
                'roiReport'
            )->name('roi-report');
            Route::get(
                '/roi-report/export',
                'exportRoiReport'
            )->name('roi-report.export');
            Route::get(
                '/level-income',
                'levelIncomeReport'
            )->name('level-income');
            Route::get(
                '/level-income/export',
                'exportLevelIncomeReport'
            )->name('level-income.export');
            Route::get(
                '/salary-report',
                'salaryReport'
            )->name('salary');
            Route::get(
                '/rank-achievement',
                'rankAchievementReport'
            )->name('rank-achievement');
            
        });
    
    
});

Route::middleware('member.context')->group(function () {
    Route::controller(InvestController::class)
        ->prefix('member/investments')
        ->name('member.investments.')
        ->group(function () {
            Route::get('/entry', 'entry')->name('entry');
            Route::post('/entry', 'store')->name('store');
            Route::get('/active', 'active')->name('active');
            Route::get('/closed', 'closed')->name('closed');
        });

    Route::get('/member/profile', [ProfileController::class, 'show'])
        ->name('member.profile');

    Route::get('/member/profile/edit', [ProfileController::class, 'edit'])
        ->name('member.profile.edit');

    Route::put('/member/profile', [ProfileController::class, 'update'])
        ->name('member.profile.update');

    Route::get('/member/team/direct', [TeamController::class, 'direct'])
        ->name('member.team.direct');

    Route::get('/member/team', [TeamController::class, 'whole'])
        ->name('member.team.whole');

    Route::get('/member/registration', [NewMemberRegistrationController::class, 'create'])
        ->name('member.registration');

    Route::get('/member/registration/check-sponsor', [NewMemberRegistrationController::class, 'checkSponsor'])
        ->name('member.registration.check-sponsor');

    Route::post('/member/registration', [NewMemberRegistrationController::class, 'store'])
        ->name('member.registration.store');

    Route::get('/member/dashboard', function (Request $request) {
        $member = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();

        return app(MemberController::class)->memberPanel($member->member_id, $request);
    })->name('member.dashboard');

   Route::post('/member/logout', function (Request $request) {
    $isAdminPreview = $request->user()?->is_admin === true;

    $request->session()->forget('member_context_id');

    return $isAdminPreview
        ? redirect()->route('admin.login')
        : redirect()->route('admin.login');
})->name('member.logout');
});