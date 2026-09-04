<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\WorkspaceInvitation;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        return view('invitations.show', [
            'invitation' => $invitation,
            'authMatches' => auth()->check()
                && $invitation
                && strcasecmp(auth()->user()->email, $invitation->email) === 0,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

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
}
