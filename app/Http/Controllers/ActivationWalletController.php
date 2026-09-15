<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivationWalletController extends Controller
{
    public function creditEntry()
    {
        return view('admin.activation-wallet.credit-entry');
    }
}
