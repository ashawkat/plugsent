#!/usr/bin/env php
<?php

/**
 * Simulates a WordPress site running the Plugsent connector against a live server.
 *
 * Usage: php scripts/simulate-site.php [server-url] [pairing-code]
 *   server-url    default http://127.0.0.1:8000
 *   pairing-code  a fresh code from the "Connect site" page; if omitted, the
 *                 script only polls (expects PLUGSENT_KEY/PLUGSENT_SECRET env).
 *
 * This is the same handshake, signing, poll, and results flow the WP plugin runs.
 */

require __DIR__.'/../vendor/autoload.php';

use Plugsent\ConnectorSigning\Signer;

$server = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$pairingCode = $argv[2] ?? null;

function http_post(string $url, array $body, ?string $key = null, ?string $secret = null): array
{
    $json = json_encode($body);
    $headers = ['Content-Type: application/json'];

    if ($key !== null) {
        $timestamp = time();
        $headers[] = 'X-Plugsent-Key: '.$key;
        $headers[] = 'X-Plugsent-Timestamp: '.$timestamp;
        $headers[] = 'X-Plugsent-Nonce: '.bin2hex(random_bytes(16));
        $headers[] = 'X-Plugsent-Signature: '.Signer::sign($secret, $timestamp, $json);
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $json,
        'ignore_errors' => true,
    ]]);

    $raw = file_get_contents($url, false, $context);
    $status = (int) (explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1] ?? 0);

    return [$status, json_decode((string) $raw, true)];
}

$key = getenv('PLUGSENT_KEY') ?: null;
$secret = getenv('PLUGSENT_SECRET') ?: null;

if ($pairingCode !== null) {
    echo "1. Pairing with {$server} using {$pairingCode}...\n";
    [$status, $payload] = http_post($server.'/connector/v1/pair', [
        'code' => $pairingCode,
        'site_url' => 'https://client-a.test',
        'name' => 'Client A',
        'wp_version' => '6.8.1',
        'php_version' => '8.2.0',
        'capabilities' => ['inventory.get', 'update.run', 'admin.login', 'plugin.activate', 'plugin.deactivate', 'plugin.delete', 'theme.activate', 'theme.delete'],
    ]);

    if ($status !== 201) {
        echo "   Pairing failed (HTTP {$status}): ".json_encode($payload)."\n";
        exit(1);
    }

    $key = $payload['site_key'];
    $secret = $payload['site_secret'];
    echo "   Paired. site_key = {$key}\n";
    echo "   (site_secret saved for this session only — the plugin stores it locally)\n";
} elseif ($key === null || $secret === null) {
    echo "Usage: php scripts/simulate-site.php [server-url] <pairing-code>\n";
    exit(1);
}

$inventory = [
    'core' => [
        ['slug' => 'wordpress', 'name' => 'WordPress', 'version' => '6.8.1', 'update_available' => true, 'update_version' => '6.9', 'active' => true],
    ],
    'plugins' => [
        ['slug' => 'akismet', 'name' => 'Akismet', 'version' => '5.3.2', 'update_available' => true, 'update_version' => '5.3.3', 'active' => true],
        ['slug' => 'woocommerce', 'name' => 'WooCommerce', 'version' => '9.4.1', 'update_available' => false, 'update_version' => null, 'active' => true],
        ['slug' => 'hello', 'name' => 'Hello Dolly', 'version' => '1.7.2', 'update_available' => false, 'update_version' => null, 'active' => false],
    ],
    'themes' => [
        ['slug' => 'twentytwentyfive', 'name' => 'Twenty Twenty-Five', 'version' => '1.2', 'update_available' => true, 'update_version' => '1.3', 'active' => true],
    ],
];

foreach ([1, 2] as $cycle) {
    echo "2. Poll #{$cycle}...\n";
    [$status, $payload] = http_post($server.'/connector/v1/poll', [
        'wp_version' => '6.8.1',
        'php_version' => '8.2.0',
        'capabilities' => ['inventory.get', 'update.run', 'admin.login', 'plugin.activate', 'plugin.deactivate', 'plugin.delete', 'theme.activate', 'theme.delete'],
        'health' => ['status' => 'ok'],
    ], $key, $secret);

    if ($status !== 200) {
        echo "   Poll failed (HTTP {$status})\n";
        exit(1);
    }

    $commands = $payload['commands'] ?? [];
    echo '   received '.count($commands)." command(s)\n";

    if ($commands === []) {
        continue;
    }

    $results = [];
    foreach ($commands as $command) {
        if ($command['type'] === 'inventory.get') {
            $results[] = ['id' => $command['id'], 'status' => 'ok', 'data' => ['inventory' => $inventory]];
            echo "   executed inventory.get (id {$command['id']}): 1 core, ".count($inventory['plugins']).' plugins, '.count($inventory['themes'])." theme\n";
        } else {
            $results[] = ['id' => $command['id'], 'status' => 'failed', 'error' => 'unsupported_command'];
        }
    }

    echo "3. Posting results...\n";
    [$status, $payload] = http_post($server.'/connector/v1/results', ['results' => $results], $key, $secret);
    echo '   processed: '.($payload['processed'] ?? '?')."\n";
}

echo "Done. Site should show as Connected in the Plugsent dashboard.\n";
