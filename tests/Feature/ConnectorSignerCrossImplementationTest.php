<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Plugsent\ConnectorSigning\Signer;

/**
 * The WordPress plugin vendors its own signer (WP-style class, PHP 7.4 syntax).
 * Both implementations must agree on every vector, or the protocol breaks.
 */
class ConnectorSignerCrossImplementationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir().'/');
        }

        require_once __DIR__.'/../../plugins/plugsent-connector/includes/class-plugsent-signer.php';
    }

    public function test_plugin_signer_matches_the_package_signer(): void
    {
        $vectors = [
            ['s3cret', 1700000000, '{"a":1}'],
            ['', 0, ''],
            ['клю'.'č', 1234567890, 'unicode ✓ body'],
            [str_repeat('k', 200), 4102444800, str_repeat('b', 5000)],
        ];

        foreach ($vectors as [$secret, $timestamp, $body]) {
            $package = Signer::sign($secret, $timestamp, $body);
            $plugin = \Plugsent_Connector_Signer::sign($secret, $timestamp, $body);

            $this->assertSame($package, $plugin, "signature mismatch for timestamp {$timestamp}");
        }
    }

    public function test_plugin_verifier_agrees_with_the_package_verifier(): void
    {
        $timestamp = time();
        $body = '{"results":[]}';
        $signature = Signer::sign('k', $timestamp, $body);

        $this->assertTrue(\Plugsent_Connector_Signer::verify('k', $timestamp, $body, $signature));
        $this->assertTrue(Signer::verify('k', $timestamp, $body, $signature));
        $this->assertFalse(\Plugsent_Connector_Signer::verify('k', $timestamp, 'tampered', $signature));
        $this->assertFalse(Signer::verify('k', $timestamp, 'tampered', $signature));
    }

    public function test_plugin_helpers_match_package_formats(): void
    {
        $pair = \Plugsent_Connector_Signer::generate_key_pair();

        $this->assertMatchesRegularExpression('/^pk_[0-9a-f]{24}$/', $pair['site_key']);
        $this->assertMatchesRegularExpression('/^PLSG-[A-F0-9]{12}$/', \Plugsent_Connector_Signer::pairing_code());
        $this->assertSame(
            Signer::codeHash('PLSG-A1B2C3D4E5F6'),
            \Plugsent_Connector_Signer::code_hash('plsg-a1b2c3d4e5f6'),
        );
    }
}
