<x-filament-panels::page>
    @php $smtpReady = $this->mailSmtpConfigured(); @endphp

    {{-- Delivery status --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Email delivery</h2>
        </div>
        <p class="plugsent-note plugsent-card-body">
            @if($smtpReady)
                <span class="plugsent-state plugsent-state-active">SMTP</span>
                Invitations are delivered over SMTP from {{ app(\App\Support\MailSettings::class)->get('mail_from_address') }}.
            @else
                <span class="plugsent-state plugsent-state-inactive">Log driver</span>
                Emails are written to the app log instead of being delivered. Configure SMTP below so invitations reach inboxes.
            @endif
        </p>
    </div>

    {{-- Mail settings --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Mail</h2>
        </div>

        <div class="plugsent-form-grid">
            <div class="plugsent-field">
                <label>Mailer</label>
                <select class="plugsent-select" wire:model.lazy="mailer">
                    <option value="log">Log driver (no real delivery)</option>
                    <option value="smtp">SMTP</option>
                </select>
            </div>
        </div>

        @if($this->mailer === 'smtp')
            <div class="plugsent-form-grid cols-3">
                <div class="plugsent-field">
                    <label>SMTP host</label>
                    <input type="text" class="plugsent-input" placeholder="smtp.example.com"
                           wire:model.lazy="host" />
                </div>
                <div class="plugsent-field">
                    <label>Port</label>
                    <input type="number" class="plugsent-input" placeholder="587"
                           wire:model.lazy="port" />
                </div>
                <div class="plugsent-field">
                    <label>Encryption</label>
                    <select class="plugsent-select" wire:model.lazy="encryption">
                        <option value="smtp">STARTTLS (port 587)</option>
                        <option value="smtps">Implicit TLS (port 465)</option>
                        <option value="none">None</option>
                    </select>
                </div>
            </div>
            <div class="plugsent-form-grid cols-2">
                <div class="plugsent-field">
                    <label>Username</label>
                    <input type="text" class="plugsent-input" wire:model.lazy="username" />
                </div>
                <div class="plugsent-field">
                    <label>Password</label>
                    <input type="password" class="plugsent-input" placeholder="Leave blank to keep current"
                           wire:model.lazy="password" />
                </div>
            </div>
        @endif

        <div class="plugsent-form-grid cols-2">
            <div class="plugsent-field">
                <label>From address</label>
                <input type="email" class="plugsent-input" placeholder="hello@yourdomain.com"
                       wire:model.lazy="fromAddress" />
            </div>
            <div class="plugsent-field">
                <label>From name</label>
                <input type="text" class="plugsent-input" placeholder="Plugsent"
                       wire:model.lazy="fromName" />
            </div>
        </div>

        @if($errors->any())
            <p class="plugsent-error">{{ $errors->first() }}</p>
        @endif

        <div class="plugsent-form-actions">
            <button type="button" class="plugsent-btn plugsent-btn-primary" wire:click="save">
                Save settings
            </button>
        </div>

        <p class="plugsent-note plugsent-card-body">
            Saved values override your .env file; .env stays the fallback when nothing is saved here.
            The password is stored encrypted and never sent back to the browser.
        </p>
    </div>

    {{-- Test email --}}
    <div class="plugsent-category">
        <div class="plugsent-category-head">
            <h2>Send a test email</h2>
        </div>
        <div class="plugsent-invite-form">
            <input type="email" class="plugsent-input" placeholder="you@example.com"
                   wire:model.lazy="testEmail" />
            <button type="button" class="plugsent-btn" wire:click="sendTestEmail">
                Send test email
            </button>
        </div>
        <p class="plugsent-note plugsent-card-body">
            Sends through the settings saved above. If it fails, the exact error from your SMTP server is shown.
        </p>
    </div>
</x-filament-panels::page>
