<x-filament-panels::page>
    {{-- Profile --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head"><h2>Profile</h2></div>
        <div class="plugsent-form-grid cols-2" style="padding-top: 14px;">
            <div class="plugsent-field">
                <label>Name</label>
                <input type="text" class="plugsent-input" wire:model.lazy="name" />
            </div>
            <div class="plugsent-field">
                <label>Email</label>
                <input type="email" class="plugsent-input" wire:model.lazy="email" />
            </div>
        </div>
        @error('name') <p class="plugsent-error">{{ $message }}</p> @enderror
        @error('email') <p class="plugsent-error">{{ $message }}</p> @enderror
        <div style="padding: 4px 18px 18px;">
            <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="saveProfile">Save profile</button>
        </div>
    </div>

    {{-- Password --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head"><h2>Password</h2></div>
        <div class="plugsent-form-grid cols-3" style="padding-top: 14px;">
            <div class="plugsent-field">
                <label>Current password</label>
                <div class="plugsent-pw-wrap" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" class="plugsent-input plugsent-pw-input"
                           wire:model.lazy="current_password" autocomplete="current-password" />
                    <button type="button" class="plugsent-pw-eye" @click="show = !show"
                            :title="show ? 'Hide password' : 'Show password'" aria-label="Toggle password visibility">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                @error('current_password') <p class="plugsent-error">{{ $message }}</p> @enderror
            </div>
            <div class="plugsent-field">
                <label>New password</label>
                <div class="plugsent-pw-wrap" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" class="plugsent-input plugsent-pw-input"
                           wire:model.lazy="new_password" autocomplete="new-password" />
                    <button type="button" class="plugsent-pw-eye" @click="show = !show"
                            :title="show ? 'Hide password' : 'Show password'" aria-label="Toggle password visibility">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                @error('new_password') <p class="plugsent-error">{{ $message }}</p> @enderror
            </div>
            <div class="plugsent-field">
                <label>Confirm new password</label>
                <div class="plugsent-pw-wrap" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" class="plugsent-input plugsent-pw-input"
                           wire:model.lazy="new_password_confirmation" autocomplete="new-password" />
                    <button type="button" class="plugsent-pw-eye" @click="show = !show"
                            :title="show ? 'Hide password' : 'Show password'" aria-label="Toggle password visibility">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                @error('new_password_confirmation') <p class="plugsent-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div style="padding: 4px 18px 18px;">
            <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="savePassword">Change password</button>
            <span class="plugsent-muted" style="margin-left: 10px; font-size: 0.8rem;">
                Changing your password signs out your other browser sessions and emails you a confirmation.
            </span>
        </div>
    </div>

    {{-- Email preferences --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head"><h2>Email preferences</h2></div>
        <div class="plugsent-form-grid cols-3" style="padding-top: 14px;">
            <label class="plugsent-field plugsent-check">
                <input type="checkbox" wire:model="prefUptime" /> Uptime alerts (site down / recovered)
            </label>
            <label class="plugsent-field plugsent-check">
                <input type="checkbox" wire:model="prefSecurity" /> Security alerts (new vulnerabilities)
            </label>
            <label class="plugsent-field plugsent-check">
                <input type="checkbox" wire:model="prefUpdates" /> Daily updates digest
            </label>
        </div>
        <div style="padding: 4px 18px 18px;">
            <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="saveEmailPreferences">Save preferences</button>
        </div>
    </div>

    {{-- Two-factor authentication --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Two-factor authentication</h2>
            @if(auth()->user()->hasTwoFactorEnabled())
                <span class="plugsent-state plugsent-state-up">enabled</span>
            @else
                <span class="plugsent-state plugsent-state-inactive">disabled</span>
            @endif
        </div>

        @if(auth()->user()->hasTwoFactorEnabled())
            <div style="padding: 14px 18px 18px;">
                <p class="plugsent-note" style="margin-bottom: 12px;">
                    Your account requires a 6-digit code from your authenticator app at every sign-in.
                </p>

                @if($recoveryCodes)
                    <p class="plugsent-note"><strong>Store these recovery codes somewhere safe — they are shown only once.</strong></p>
                    <div class="plugsent-recovery-codes">
                        @foreach($recoveryCodes as $code)
                            <code>{{ $code }}</code>
                        @endforeach
                    </div>
                @endif

                <div class="plugsent-form-grid cols-2" style="padding-top: 6px;">
                    <div class="plugsent-field">
                        <label>Current password (to disable or regenerate codes)</label>
                        <div class="plugsent-pw-wrap" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" class="plugsent-input plugsent-pw-input"
                                   wire:model.lazy="current_password" autocomplete="current-password" />
                            <button type="button" class="plugsent-pw-eye" @click="show = !show" aria-label="Toggle password visibility">
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        @error('current_password') <p class="plugsent-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="plugsent-field" style="align-self: end;">
                        <button type="button" class="plugsent-btn" wire:click="regenerateRecoveryCodes"
                                @click="$wire.current_password || alert('Enter your current password first')"
                                wire:confirm="Generate new recovery codes? Any previously shown codes stop working.">
                            Regenerate recovery codes
                        </button>
                        <button type="button" class="plugsent-btn plugsent-btn-icon-danger" wire:click="disableTwoFactor">
                            Disable 2FA
                        </button>
                    </div>
                </div>
            </div>
        @elseif($pendingSecret)
            <div style="padding: 14px 18px 18px;">
                <div class="plugsent-2fa-setup">
                    <div>
                        <p class="plugsent-note">
                            1. Scan this QR code with your authenticator app (Google Authenticator, 1Password, Authy…)
                            or add this secret manually:
                        </p>
                        <p style="margin:0 0 10px;"><code class="plugsent-secret">{{ $pendingSecret }}</code></p>
                        <div class="plugsent-2fa-qr">{!! $this->qrCodeSvg() !!}</div>
                    </div>
                    <div>
                        <p class="plugsent-note">2. Enter the 6-digit code from the app to confirm and enable 2FA:</p>
                        <div class="plugsent-field">
                            <input type="text" class="plugsent-input" wire:model.lazy="twoFactorCode"
                                   inputmode="numeric" placeholder="123456" maxlength="6" style="max-width: 160px;" />
                            @error('twoFactorCode') <p class="plugsent-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="confirmTwoFactor">Confirm & enable</button>
                        <button type="button" class="plugsent-btn" wire:click="$set('pendingSecret', null)">Cancel</button>
                    </div>
                </div>
            </div>
        @else
            <div style="padding: 14px 18px 18px;">
                <p class="plugsent-note" style="margin-bottom: 12px;">
                    Add a second factor at sign-in: after your password, you enter a 6-digit code from an
                    authenticator app. Recovery codes let you back in if you lose the device.
                </p>
                <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="startTwoFactor">Enable 2FA</button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
