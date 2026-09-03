<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugsent\ConnectorSigning\Signer;

class SignerTest extends TestCase
{
    public function test_sign_matches_the_protocol_reference(): void
    {
        // Protocol v1: hex(hmac_sha256(secret, "{timestamp}.{body}"))
        $this->assertSame(
            hash_hmac('sha256', '1700000000.{"a":1}', 's3cret'),
            Signer::sign('s3cret', 1700000000, '{"a":1}'),
        );
    }

    public function test_verify_accepts_a_valid_signature(): void
    {
        $timestamp = time();
        $body = '{"wp_version":"6.8"}';
        $signature = Signer::sign('k', $timestamp, $body);

        $this->assertTrue(Signer::verify('k', $timestamp, $body, $signature));
    }

    public function test_verify_rejects_wrong_secret_or_body(): void
    {
        $timestamp = time();
        $signature = Signer::sign('k', $timestamp, '{"a":1}');

        $this->assertFalse(Signer::verify('other', $timestamp, '{"a":1}', $signature));
        $this->assertFalse(Signer::verify('k', $timestamp, '{"a":2}', $signature));
        $this->assertFalse(Signer::verify('k', $timestamp, '{"a":1}', $signature.'00'));
    }

    public function test_verify_rejects_stale_timestamps(): void
    {
        $timestamp = time() - Signer::DEFAULT_TOLERANCE - 1;
        $signature = Signer::sign('k', $timestamp, 'body');

        $this->assertFalse(Signer::verify('k', $timestamp, 'body', $signature));
    }

    public function test_verify_rejects_malformed_signatures(): void
    {
        $timestamp = time();

        $this->assertFalse(Signer::verify('k', $timestamp, 'body', 'zzzz'));
        $this->assertFalse(Signer::verify('k', $timestamp, 'body', str_repeat('a', 63)));
        $this->assertFalse(Signer::verify('k', $timestamp, 'body', strtoupper(Signer::sign('k', $timestamp, 'other'))));
    }

    public function test_key_pair_and_pairing_code_shapes(): void
    {
        $pair = Signer::generateKeyPair();

        $this->assertMatchesRegularExpression('/^pk_[0-9a-f]{24}$/', $pair['site_key']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pair['site_secret']);
        $this->assertMatchesRegularExpression('/^PLSG-[A-F0-9]{12}$/', Signer::pairingCode());

        $this->assertSame(
            hash('sha256', 'PLSG-ABCDEF123456'),
            Signer::codeHash('plsg-abcdef123456 '),
        );
    }
}
