<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewMemberRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $member = $this->currentMember($request);
        $sponsorId = old('sponsor_id', $member->member_id);
        $sponsor = Member::where('member_id', $sponsorId)->first();

        return view('member.create-member.new-member-registration', [
            'member' => $member,
            'generated_member_id' => $this->generateMemberId(),
            'default_sponsor_id' => $sponsor?->member_id ?? $member->member_id,
            'default_sponsor_name' => $sponsor?->member_name ?? $member->member_name,
        ]);
    }

    public function checkSponsor(Request $request): JsonResponse
    {
        $currentMember = $this->currentMember($request);
        $sponsorId = strtoupper(trim((string) $request->query('sponsor_id', '')));

        if ($sponsorId === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Sponsor ID is required.',
            ]);
        }

        $authorizedIds = Member::authorizedMemberIds($currentMember->member_id);
        $sponsor = Member::query()
            ->whereIn('member_id', $authorizedIds)
            ->where('member_id', $sponsorId)
            ->first();

        return response()->json([
            'exists' => $sponsor !== null,
            'sponsor_name' => $sponsor?->member_name,
            'message' => $sponsor ? 'Sponsor ID is valid.' : 'Unauthorized sponsor ID.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'sponsor_id' => strtoupper(trim((string) $request->input('sponsor_id'))),
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
        ]);

        $currentMember = $this->currentMember($request);
        $authorizedIds = Member::authorizedMemberIds($currentMember->member_id);

        $validator = 
            \Illuminate\Support\Facades\Validator::make($request->all(), [
                'member_name' => ['required', 'string', 'min:3', 'regex:/^[A-Za-z ]+$/'],
                'sponsor_id' => ['required', 'string', Rule::in($authorizedIds)],
                'email' => ['required', 'email', 'unique:members,email'],
                'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            ], [
                'sponsor_id.in' => 'Unauthorized sponsor ID.',
            ]);

        if ($validator->fails()) {
            return redirect()->route('member.registration')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $sponsor = Member::where('member_id', $validated['sponsor_id'])->first();

        if (! $sponsor) {
            throw ValidationException::withMessages([
                'sponsor_id' => 'Invalid Sponsor ID.',
            ]);
        }

        $member = DB::transaction(function () use ($validated, $sponsor) {
            $memberId = $this->generateMemberId();

            return Member::create([
                'member_id' => $memberId,
                'sponsor_id' => $sponsor->member_id,
                'sponsor_name' => $sponsor->member_name,
                'member_name' => $validated['member_name'],
                'email' => $validated['email'],
                'mobile_no' => $validated['mobile_no'],
                'status' => 'inactive',
            ]);
        });

        return redirect()
            ->route('member.registration')
            ->with('success', 'Member registered successfully. Member ID: ' . $member->member_id);
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function generateMemberId(): string
    {
        for ($counter = 100000; $counter <= 999999; $counter++) {
            $candidate = 'ST' . $counter;

            if (! Member::where('member_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No available Member ID found.');
    }
}
