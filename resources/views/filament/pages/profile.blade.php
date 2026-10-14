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

    {{-- MCP access --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>MCP access</h2>
            @if($this->hasMcpToken())
                <span class="plugsent-state plugsent-state-up">active</span>
            @else
                <span class="plugsent-state plugsent-state-inactive">no token</span>
            @endif
        </div>
        <div style="padding: 14px 18px 18px;">
            <p class="plugsent-note" style="margin-bottom: 12px;">
                Connect an AI chat client (ZCode, Claude, Cursor…) to Plugsent over the Model Context Protocol.
                Point the client at the URL below with a bearer token — it can list your sites, read their stats,
                and — with write access — queue plugin/theme/core updates with exactly the permissions your
                account has. Every command an agent queues is stamped "via MCP" with your account and token
                name in the site's activity history.
            </p>
            <p style="margin:0 0 12px;"><code class="plugsent-secret">{{ $this->mcpServerUrl() }}</code></p>

            <label style="display:flex; gap:8px; align-items:center; margin:0 0 12px; font-size:13px; color:#334155;">
                <input type="checkbox" wire:model.live="mcpReadOnly" />
                <span>
                    <strong>Read-only token</strong> — can inspect sites and pending updates but cannot
                    queue updates or rescans. Recommended; untick to let the agent run updates.
                    @if($this->hasMcpToken())
                        <em>(current token: {{ $this->tokenScope() }} — regenerate to change)</em>
                    @endif
                </span>
            </label>

            @if($mcpToken)
                <p class="plugsent-note"><strong>Store this token somewhere safe — it is shown only once.</strong></p>
                <p style="margin:0 0 12px;"><code class="plugsent-secret">{{ $mcpToken }}</code></p>
            @endif

            <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="generateMcpToken">
                {{ $this->hasMcpToken() ? 'Regenerate token' : 'Generate token' }}
            </button>
            @if($this->hasMcpToken())
                <button type="button" class="plugsent-btn plugsent-btn-icon-danger" wire:click="revokeMcpToken"
                        wire:confirm="Revoke MCP access? Any chat client using this token will stop working.">
                    Revoke
                </button>
            @endif
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
                        <div class="plugsent-2fa-qr">
                            @if($this->qrCodeSvg())
                                <img src="{{ $this->qrCodeSvg() }}" alt="2FA QR code" width="180" height="180">
                            @else
                                <p class="plugsent-error">Could not generate the QR code — add the secret above manually.</p>
                            @endif
                        </div>
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
    {{-- Passkeys --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Passkeys</h2>
            @if($this->userPasskeys()->isNotEmpty())
                <span class="plugsent-state plugsent-state-up">{{ $this->userPasskeys()->count() }} registered</span>
            @else
                <span class="plugsent-state plugsent-state-inactive">none</span>
            @endif
        </div>
        <div style="padding: 14px 18px 18px;">
            <p class="plugsent-note" style="margin-bottom: 12px;">
                Passkeys sign you in with your fingerprint, face, or device PIN — nothing to type and
                nothing to phish. A passkey also proves it is really you, so it replaces the 6-digit
                code at sign-in. They live in your device's password manager (iCloud Keychain, Google
                Password Manager, 1Password…) and sync to your other devices.
            </p>

            @if($this->userPasskeys()->isNotEmpty())
                <div class="plugsent-passkey-list">
                    @foreach($this->userPasskeys() as $passkey)
                        <div class="plugsent-passkey-row">
                            <div>
                                <strong>{{ $passkey->name }}</strong>
                                <span class="plugsent-muted">
                                    @if($passkey->authenticator)
                                        · {{ $passkey->authenticator }}
                                    @endif
                                    · added {{ $passkey->created_at->isoFormat('MMM D, YYYY') }}
                                    @if($passkey->last_used_at)
                                        · last used {{ $passkey->last_used_at->diffForHumans() }}
                                    @endif
                                </span>
                            </div>
                            <button type="button" class="plugsent-btn plugsent-btn-icon-danger"
                                    wire:click="deletePasskey({{ $passkey->id }})"
                                    wire:confirm="Remove this passkey? You will no longer be able to sign in with it.">
                                Remove
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="plugsent-form-grid cols-2" style="padding-top: 10px;">
                <div class="plugsent-field">
                    <label>Current password (to remove a passkey)</label>
                    <input type="password" class="plugsent-input" wire:model.lazy="passkeyPassword"
                           autocomplete="current-password" />
                    @error('passkeyPassword') <p class="plugsent-error">{{ $message }}</p> @enderror
                </div>
                <div class="plugsent-field">
                    <label>Device name (shown for the new passkey)</label>
                    <input type="text" id="passkey-name" class="plugsent-input"
                           placeholder="MacBook Touch ID" maxlength="60" />
                </div>
            </div>
            <button type="button" id="passkey-register" class="plugsent-btn plugsent-btn-primary"
                    onclick="plugsentRegisterPasskey(this)">
                Add passkey
            </button>
            <p id="passkey-feedback" class="plugsent-note" style="margin-top: 10px;" hidden></p>
        </div>
    </div>

</x-filament-panels::page>

@assets
    <script>
        function plugsentB64uEncode(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            bytes.forEach((byte) => { binary += String.fromCharCode(byte); });

            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }

        function plugsentB64uDecode(value) {
            let base64 = value.replace(/-/g, '+').replace(/_/g, '/');
            while (base64.length % 4 !== 0) {
                base64 += '=';
            }

            return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
        }

        function plugsentPasskeyFeedback(message, isError) {
            const el = document.getElementById('passkey-feedback');
            if (!el) {
                if (isError) { alert(message); }

                return;
            }
            el.textContent = message;
            el.hidden = false;
            el.style.color = isError ? '#dc2626' : '';
        }

        async function plugsentRegisterPasskey(button) {
            if (!window.PublicKeyCredential) {
                plugsentPasskeyFeedback('This browser does not support passkeys.', true);

                return;
            }

            const name = (document.getElementById('passkey-name')?.value ?? '').trim() || 'My device';
            button.disabled = true;

            try {
                const optionsRes = await fetch('{{ route('passkey.registration-options') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!optionsRes.ok) {
                    throw new Error('Could not start passkey setup — reload the page and try again.');
                }
                const { options } = await optionsRes.json();
                options.challenge = plugsentB64uDecode(options.challenge);
                if (options.user?.id) {
                    options.user.id = plugsentB64uDecode(options.user.id);
                }
                (options.excludeCredentials ?? []).forEach((descriptor) => {
                    descriptor.id = plugsentB64uDecode(descriptor.id);
                });

                const credential = await navigator.credentials.create({ publicKey: options });

                const saved = await fetch('{{ route('passkey.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        name,
                        credential: {
                            id: credential.id,
                            rawId: plugsentB64uEncode(credential.rawId),
                            type: credential.type,
                            response: {
                                clientDataJSON: plugsentB64uEncode(credential.response.clientDataJSON),
                                attestationObject: plugsentB64uEncode(credential.response.attestationObject),
                                transports: credential.response.getTransports?.() ?? [],
                            },
                        },
                    }),
                });
                if (!saved.ok) {
                    throw new Error('The passkey could not be saved — try again.');
                }

                window.location.reload();
            } catch (error) {
                if (error?.name === 'NotAllowedError' || error?.name === 'AbortError') {
                    plugsentPasskeyFeedback('Passkey setup was cancelled.', false);
                } else {
                    plugsentPasskeyFeedback(error?.message ?? 'Something went wrong.', true);
                }
            } finally {
                button.disabled = false;
            }
        }
    </script>
@endassets
