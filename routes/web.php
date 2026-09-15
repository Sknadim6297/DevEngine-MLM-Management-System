<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MemberController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\ActivationWalletController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\genealogyController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ReportController;


Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
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

    return view('admin.login.login');
})->name('login');


Route::post('/admin/login', function (Request $request) {

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (
        Auth::attempt(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
            $request->boolean('remember')
        )
    ) {
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    return back()
        ->withErrors([
            'email' => 'Invalid email or password.',
        ])
        ->onlyInput('email');

})->name('login.submit');


/*
|--------------------------------------------------------------------------
| Authenticated Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        return view('admin.dashboard.index');
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

        return redirect('/admin/login');

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
                '/active-investments/export',
                'exportActiveInvestments'
            )->name('active-investments.export');

            Route::get(
                '/active-investments',
                'activeInvestments'
            )->name('active-investments');
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
                '/level-income',
                'levelIncomeReport'
            )->name('level-income');    
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