<?php

namespace App\Actions;

use App\Models\Site;
use Illuminate\Support\Facades\Http;

/**
 * Look up when a site's SSL certificate and domain expire, and cache both
 * on the site record. SSL expiry comes from the served certificate itself;
 * domain expiry from the free RDAP protocol (rdap.org). Run at most once
 * a day — both lookups take a second or two and change rarely.
 */
class CheckDomainSsl
{
    public function __invoke(Site $site): void
    {
        if ($site->domain_checked_at?->gt(now()->subDay())) {
            return;
        }

        $host = $this->host($site);

        if ($host === null) {
            return;
        }

        $sslExpires = $this->sslExpiry($host);
        $domainExpires = $this->domainExpiry($host);

        $site->forceFill(array_filter([
            'ssl_expires_at' => $sslExpires,
            'domain_expires_at' => $domainExpires,
            'domain_checked_at' => now(),
        ], fn ($value) => $value !== null))->save();
    }

    private function host(Site $site): ?string
    {
        $host = parse_url((string) $site->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * @return \Carbon\CarbonInterface|null
     */
    private function sslExpiry(string $host): ?object
    {
        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_session_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'timeout' => 6,
                ],
            ]);

            $client = @stream_socket_client(
                'ssl://'.$host.':443',
                $errno,
                $errstr,
                6,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if ($client === false) {
                return null;
            }

            $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
            fclose($client);

            if ($cert === false || $cert === null) {
                return null;
            }

            $info = openssl_x509_parse($cert);

            if (! is_array($info) || empty($info['validTo_time_t'])) {
                return null;
            }

            return now()->setTimestamp((int) $info['validTo_time_t']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return \Carbon\CarbonInterface|null
     */
    private function domainExpiry(string $host): ?object
    {
        try {
            $response = Http::timeout(8)
                ->accept('application/rdap+json')
                ->get('https://rdap.org/domain/'.$host);

            if (! $response->successful()) {
                return null;
            }

            foreach ((array) $response->json('events') as $event) {
                if (($event['eventAction'] ?? null) === 'expiration' && ! empty($event['eventDate'])) {
                    return now()->parse($event['eventDate']);
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
