<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->validInvitation($token);

        return view('invitations.show', [
            'invitation' => $invitation,
            'authMatches' => auth()->check()
                && $invitation
                && strcasecmp(auth()->user()->email, $invitation->email) === 0,
            'emailTaken' => $invitation !== null && User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    /**
     * One-step join for invitees without an account: the invitation carries
     * the email and workspace, the guest only picks a name and password.
     * No second workspace is created — they land inside the inviter's.
     */
    public function register(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token);

        if (! $invitation) {
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        // Email already has an account? The normal accept path covers it.
        if (User::query()->where('email', $invitation->email)->exists()) {
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
            ]);

            $invitation->workspace->users()->attach($user->id, ['role' => $invitation->role]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        Filament::setTenant($invitation->workspace);

        return redirect()->to(
            SiteResource::getUrl('index', ['tenant' => $invitation->workspace]),
        );
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token);

        if (! $invitation) {
            return redirect()->route('invitations.show', ['token' => $token]);
        }

        Gate::authorize('accept', $invitation);

        $invitation->workspace->users()->attach($request->user()->id, ['role' => $invitation->role]);
        $invitation->forceFill(['accepted_at' => now()])->save();

        Filament::setTenant($invitation->workspace);

        return redirect()->to(
            SiteResource::getUrl('index', ['tenant' => $invitation->workspace]),
        );
    }

    private function validInvitation(string $token): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
