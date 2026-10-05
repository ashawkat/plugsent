<?php

namespace Tests\Feature;

use App\Support\ConnectorRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectorDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(ConnectorRelease::CACHE_KEY);
        Cache::forget(ConnectorRelease::FAILURE_KEY);
    }

    public function test_download_redirects_to_the_latest_release_zip(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v0.13.0',
                'name' => 'v0.13.0 — Security scans & hardening',
                'assets' => [
                    ['name' => 'plugsent-connector-0.13.0.zip', 'browser_download_url' => 'https://github.com/ashawkat/plugsent-connector/releases/download/v0.13.0/plugsent-connector-0.13.0.zip'],
                    ['name' => 'source-code.zip', 'browser_download_url' => 'https://example.com/source.zip'],
                ],
            ]),
        ]);

        $response = $this->get('/connector/download');

        $response->assertRedirect('https://github.com/ashawkat/plugsent-connector/releases/download/v0.13.0/plugsent-connector-0.13.0.zip');

        // The resolved release is cached — a second request makes no API call.
        Http::fake([]);
        $this->get('/connector/download')
            ->assertRedirect('https://github.com/ashawkat/plugsent-connector/releases/download/v0.13.0/plugsent-connector-0.13.0.zip');
    }

    public function test_picks_the_zip_asset_and_exposes_release_details(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v0.14.0',
                'name' => 'v0.14.0',
                'assets' => [
                    ['name' => 'checksums.txt', 'browser_download_url' => 'https://example.com/checksums.txt'],
                    ['name' => 'plugsent-connector-0.14.0.zip', 'browser_download_url' => 'https://example.com/connector.zip'],
                ],
            ]),
        ]);

        $release = ConnectorRelease::latest();

        $this->assertSame('v0.14.0', $release['tag']);
        $this->assertSame('https://example.com/connector.zip', $release['zip']);
    }

    public function test_falls_back_to_the_releases_page_when_github_is_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 502)]);

        $this->assertNull(ConnectorRelease::latest());
        $this->assertSame(
            'https://github.com/ashawkat/plugsent-connector/releases/latest',
            ConnectorRelease::downloadUrl(),
        );

        // Failures are remembered briefly so page renders don't hammer GitHub.
        $this->assertTrue(Cache::has(ConnectorRelease::FAILURE_KEY));
    }

    public function test_a_release_without_a_zip_asset_falls_back_gracefully(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases/latest' => Http::response([
                'tag_name' => 'v0.15.0',
                'assets' => [],
            ]),
        ]);

        $this->assertNull(ConnectorRelease::latest());
        $this->assertSame(
            'https://github.com/ashawkat/plugsent-connector/releases/latest',
            ConnectorRelease::downloadUrl(),
        );
    }
}
