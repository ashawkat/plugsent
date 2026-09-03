<?php

namespace Plugsent\ConnectorSigning;

/**
 * Connector protocol v1 request signing.
 *
 * Every connector request carries:
 *   X-Plugsent-Key:       the public site key (pk_...)
 *   X-Plugsent-Timestamp: unix seconds
 *   X-Plugsent-Nonce:     a unique value per request (replay protection)
 *   X-Plugsent-Signature: hex(hmac_sha256(secret, "{timestamp}.{raw body}"))
 *
 * Implementations must be framework-free and PHP 7.4-compatible so the
 * WordPress plugin can vendor this file verbatim.
 */
final class Signer
{
    public const DEFAULT_TOLERANCE = 300;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(
        string $secret,
        int $timestamp,
        string $body,
        string $signature,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): bool {
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        if (! ctype_xdigit($signature) || strlen($signature) !== 64) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), strtolower($signature));
    }

    /**
     * @return array{site_key: string, site_secret: string}
     */
    public static function generateKeyPair(): array
    {
        return [
            'site_key' => 'pk_'.bin2hex(random_bytes(12)),
            'site_secret' => bin2hex(random_bytes(32)),
        ];
    }

    public static function pairingCode(): string
    {
        return 'PLSG-'.strtoupper(bin2hex(random_bytes(6)));
    }

    public static function codeHash(string $code): string
    {
        return hash('sha256', trim(strtoupper($code)));
    }
}
