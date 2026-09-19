<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('member.profile.show', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('member.profile.edit', [
            'member' => $this->currentMember($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $this->currentMember($request);

        $request->merge([
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
            'wallet_address' => trim((string) $request->input('wallet_address')),
        ]);

        $validated = $request->validate([
            'member_name' => ['required', 'string', 'min:3', 'regex:/^[A-Za-z ]+$/'],
            'email' => ['required', 'email', Rule::unique('members', 'email')->ignore($member->id)],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            'wallet_address' => ['nullable', 'string', 'max:255'],
        ]);

        if (Member::where('mobile_no', $validated['mobile_no'])
            ->whereKeyNot($member->id)
            ->count() >= 3) {
            return back()->withErrors([
                'mobile_no' => 'This mobile number has already been used 3 times.',
            ])->withInput();
        }

        $member->update($validated);

        return redirect()
            ->route('member.profile')
            ->with('success', 'Profile updated successfully.');
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }
}
