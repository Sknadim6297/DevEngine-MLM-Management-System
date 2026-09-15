<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupportController extends Controller
{
   
    public function pendingTickets()
    {
        return view('admin.support.pending-ticket-list');
    }
     public function appliedTicketList()
    {
        return view('admin.support.applied-ticket-list');
    }

}
