<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function index()
    {
        return view('admin.change-password.index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => 'Old Password is required.',
            'new_password.required' => 'New Password is required.',
            'new_password.min' => 'New Password must be at least 8 characters.',
            'new_password.confirmed' => 'New Password and Confirm New Password do not match.',
            'new_password_confirmation.required' => 'Confirm New Password is required.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }
}
