<?php

namespace Tests\Feature;

use App\Actions\EnqueueSiteCommand;
use App\Actions\IssuePairingCode;
use App\Models\InventoryItem;
use App\Models\PairingCode;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\SiteCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Plugsent\ConnectorSigning\Signer;
use Tests\TestCase;

class ConnectorProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pair_poll_results_cycle(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        // Pairing marked the site connected and queued an inventory command.
        $site->refresh();
        $this->assertSame('connected', $site->status);
        $this->assertSame('6.8.0', $site->wp_version);

        // --- Poll: the queued command is returned exactly once ---
        $pollResponse = $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.1', 'php_version' => '8.2.1', 'capabilities' => ['inventory.get']],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        );

        $pollResponse->assertOk()->assertJsonCount(1, 'commands');
        $command = $pollResponse->json('commands.0');
        $this->assertSame('inventory.get', $command['type']);

        // --- Results: report the inventory snapshot ---
        $resultsResponse = $this->signedCall(
            '/connector/v1/results',
            ['results' => [[
                'id' => $command['id'],
                'status' => 'ok',
                'data' => ['inventory' => $this->sampleInventory()],
            ]]],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        );

        $resultsResponse->assertOk()->assertJson(['processed' => 1]);

        $site->refresh();
        $this->assertSame('6.8.1', $site->wp_version);
        $this->assertNotNull($site->last_seen_at);
        $this->assertSame(4, InventoryItem::query()->where('site_id', $site->id)->count());
        $this->assertSame(
            SiteCommand::STATUS_COMPLETED,
            SiteCommand::query()->find($command['id'])->status,
        );
        $this->assertTrue(
            InventoryItem::query()->where('site_id', $site->id)->where('slug', 'akismet')->value('update_available'),
        );

        // --- A later poll returns nothing new ---
        $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.1'],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        )->assertOk()->assertJsonCount(0, 'commands');
    }

    public function test_pairing_code_is_single_use(): void
    {
        $site = $this->siteForWorkspace('Alpha');
        $pairing = app(IssuePairingCode::class)($site);

        $payload = [
            'code' => $pairing['code'],
            'site_url' => 'https://client-a.test',
            'wp_version' => '6.8.0',
        ];

        $this->postJson('/connector/v1/pair', $payload)->assertCreated();
        $this->postJson('/connector/v1/pair', $payload)->assertUnprocessable();
    }

    public function test_pairing_rejects_unknown_or_expired_codes(): void
    {
        $this->postJson('/connector/v1/pair', [
            'code' => 'PLSG-000000000000',
            'site_url' => 'https://client-a.test',
        ])->assertUnprocessable();

        $site = $this->siteForWorkspace('Alpha');
        $pairing = app(IssuePairingCode::class)($site);

        // Simulate expiry.
        PairingCode::query()->latest('id')->first()
            ->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/connector/v1/pair', [
            'code' => $pairing['code'],
            'site_url' => 'https://client-a.test',
        ])->assertUnprocessable();
    }

    public function test_unsigned_or_tampered_requests_are_rejected(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        $this->postJson('/connector/v1/poll', ['wp_version' => '6.8.0'])->assertUnauthorized();

        $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.0'],
            $keyPair['site_key'],
            $keyPair['site_secret'],
            ['signature' => str_repeat('a', 64)],
        )->assertUnauthorized();

        // Correct signature, wrong key.
        $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.0'],
            'pk_doesnotexist',
            $keyPair['site_secret'],
        )->assertUnauthorized();
    }

    public function test_stale_timestamps_are_rejected(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.0'],
            $keyPair['site_key'],
            $keyPair['site_secret'],
            ['timestamp' => time() - Signer::DEFAULT_TOLERANCE - 5],
        )->assertUnauthorized();
    }

    public function test_replayed_requests_are_rejected(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        $frozen = ['nonce' => (string) Str::uuid(), 'timestamp' => time()];

        $this->signedCall('/connector/v1/poll', ['wp_version' => '6.8.0'], $keyPair['site_key'], $keyPair['site_secret'], $frozen)
            ->assertOk();

        $this->signedCall('/connector/v1/poll', ['wp_version' => '6.8.0'], $keyPair['site_key'], $keyPair['site_secret'], $frozen)
            ->assertUnauthorized();
    }

    public function test_revoked_credentials_stop_working(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        SiteCredential::query()->where('site_key', $keyPair['site_key'])
            ->update(['status' => 'revoked']);

        $this->signedCall('/connector/v1/poll', ['wp_version' => '6.8.0'], $keyPair['site_key'], $keyPair['site_secret'])
            ->assertUnauthorized();
    }

    public function test_update_run_command_flow_refreshes_inventory(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        app(EnqueueSiteCommand::class)(
            $site,
            'update.run',
            ['context' => 'plugin', 'slug' => 'akismet'],
        );

        // Pairing queued an inventory command; clear it so the next poll
        // returns exactly the update command under test.
        SiteCommand::query()->where('site_id', $site->id)->where('type', 'inventory.get')->delete();

        // The site polls and receives exactly the update command.
        $pollResponse = $this->signedCall(
            '/connector/v1/poll',
            ['wp_version' => '6.8.1'],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        );
        $pollResponse->assertOk()->assertJsonCount(1, 'commands');
        $command = $pollResponse->json('commands.0');
        $this->assertSame('update.run', $command['type']);
        $this->assertSame('akismet', $command['payload']['slug']);

        // The site reports success.
        $this->signedCall(
            '/connector/v1/results',
            ['results' => [[
                'id' => $command['id'],
                'status' => 'ok',
                'data' => ['update' => ['context' => 'plugin', 'slug' => 'akismet', 'ok' => true, 'message' => 'Updated.', 'version' => '5.3.3']],
            ]]],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        )->assertOk();

        // A follow-up inventory command is automatically queued.
        $this->assertDatabaseHas('site_commands', [
            'site_id' => $site->id,
            'type' => 'inventory.get',
            'status' => SiteCommand::STATUS_PENDING,
        ]);
    }

    public function test_update_run_failures_are_recorded(): void
    {
        [$site, $keyPair] = $this->pairedSite();

        app(EnqueueSiteCommand::class)($site, 'update.run', ['context' => 'plugin', 'slug' => 'nope']);

        $pollResponse = $this->signedCall(
            '/connector/v1/poll',
            [],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        );
        $command = $pollResponse->json('commands.0');

        $this->signedCall(
            '/connector/v1/results',
            ['results' => [[
                'id' => $command['id'],
                'status' => 'failed',
                'error' => 'Plugin not found.',
            ]]],
            $keyPair['site_key'],
            $keyPair['site_secret'],
        )->assertOk();

        $this->assertDatabaseHas('site_commands', [
            'id' => $command['id'],
            'status' => SiteCommand::STATUS_FAILED,
        ]);
    }

    /**
     * @return array{0: Site, 1: array{site_key: string, site_secret: string}}
     */
    private function pairedSite(): array
    {
        $site = $this->siteForWorkspace('Alpha');
        $pairing = app(IssuePairingCode::class)($site);

        $response = $this->postJson('/connector/v1/pair', [
            'code' => $pairing['code'],
            'site_url' => 'https://client-a.test',
            'wp_version' => '6.8.0',
            'php_version' => '8.2.0',
            'capabilities' => ['inventory.get'],
        ]);

        $response->assertCreated();

        return [$site->refresh(), $response->json()];
    }

    private function siteForWorkspace(string $workspaceName): Site
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => $workspaceName, 'owner_id' => $user->id]);
        $workspace->users()->attach($user, ['role' => 'owner']);

        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Client A',
            'url' => 'https://client-a.test',
            'status' => 'pending',
        ]);
    }

    private function sampleInventory(): array
    {
        return [
            'core' => [[
                'slug' => 'wordpress', 'name' => 'WordPress', 'version' => '6.8.1',
                'update_available' => true, 'update_version' => '6.9', 'active' => true,
            ]],
            'plugins' => [
                ['slug' => 'akismet', 'name' => 'Akismet', 'version' => '5.3.2', 'update_available' => true, 'update_version' => '5.3.3', 'active' => true],
                ['slug' => 'hello', 'name' => 'Hello Dolly', 'version' => '1.7.2', 'update_available' => false, 'update_version' => null, 'active' => false],
            ],
            'themes' => [[
                'slug' => 'twentytwentyfive', 'name' => 'Twenty Twenty-Five', 'version' => '1.2',
                'update_available' => false, 'update_version' => null, 'active' => true,
            ]],
        ];
    }

    /**
     * POST an exact raw JSON body with protocol v1 headers.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{timestamp?: int, nonce?: string, signature?: string}  $overrides
     */
    private function signedCall(string $uri, array $payload, string $key, string $secret, array $overrides = [])
    {
        $body = json_encode($payload);
        $timestamp = $overrides['timestamp'] ?? time();
        $nonce = $overrides['nonce'] ?? (string) Str::uuid();
        $signature = $overrides['signature'] ?? Signer::sign($secret, $timestamp, $body);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PLUGSENT_KEY' => $key,
            'HTTP_X_PLUGSENT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_PLUGSENT_NONCE' => $nonce,
            'HTTP_X_PLUGSENT_SIGNATURE' => $signature,
        ], $body);
    }
}
