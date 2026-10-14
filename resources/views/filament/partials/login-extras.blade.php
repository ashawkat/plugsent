<div class="plugsent-login-extras">
    <div class="plugsent-login-divider" aria-hidden="true"><span>or</span></div>

    <button type="button" id="passkey-login" class="plugsent-btn plugsent-login-passkey"
            onclick="plugsentPasskeyLogin(this)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
             stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
        </svg>
        Sign in with a passkey
    </button>

    <p id="passkey-login-message" class="plugsent-login-message" hidden></p>

    <p class="plugsent-login-support">
        Plugsent is free and open source —
        <a href="https://ko-fi.com/adnanshawkat" target="_blank" rel="noopener noreferrer">support the work on Ko-fi&nbsp;&hearts;</a>
    </p>
</div>

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

        function plugsentPasskeyMessage(message) {
            const el = document.getElementById('passkey-login-message');
            if (el) {
                el.textContent = message;
                el.hidden = false;
            } else {
                alert(message);
            }
        }

        async function plugsentPasskeyLogin(button) {
            if (!window.PublicKeyCredential) {
                plugsentPasskeyMessage('This browser does not support passkeys — sign in with your password.');

                return;
            }

            button.disabled = true;

            try {
                const optionsRes = await fetch('{{ route('passkey.login-options') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!optionsRes.ok) {
                    throw new Error('Passkey sign-in is not available right now — use your password.');
                }
                const { options } = await optionsRes.json();
                options.challenge = plugsentB64uDecode(options.challenge);
                (options.allowCredentials ?? []).forEach((descriptor) => {
                    descriptor.id = plugsentB64uDecode(descriptor.id);
                });

                const credential = await navigator.credentials.get({ publicKey: options });

                const res = await fetch('{{ route('passkey.login') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        credential: {
                            id: credential.id,
                            rawId: plugsentB64uEncode(credential.rawId),
                            type: credential.type,
                            response: {
                                clientDataJSON: plugsentB64uEncode(credential.response.clientDataJSON),
                                authenticatorData: plugsentB64uEncode(credential.response.authenticatorData),
                                signature: plugsentB64uEncode(credential.response.signature),
                                userHandle: credential.response.userHandle
                                    ? plugsentB64uEncode(credential.response.userHandle)
                                    : null,
                            },
                        },
                    }),
                });
                if (!res.ok) {
                    throw new Error('That passkey did not work — sign in with your password.');
                }
                const { redirect } = await res.json();
                window.location.href = redirect;
            } catch (error) {
                if (error?.name !== 'NotAllowedError' && error?.name !== 'AbortError') {
                    // The user dismissing the authenticator prompt is normal; everything
                    // else is worth a message next to the button.
                    plugsentPasskeyMessage(error?.message ?? 'Passkey sign-in failed — use your password.');
                }
            } finally {
                button.disabled = false;
            }
        }
    </script>
@endassets
