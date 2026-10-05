<?php

namespace App\Mcp\Support;

use Laravel\Mcp\Request;

/**
 * What the caller's Sanctum token may do. Tokens minted before the
 * read/write split carried a single "mcp" ability — grandfathered as full
 * access; new tokens carry "mcp:read" and, when the owner opted into
 * writes, "mcp:write".
 */
class TokenAccess
{
    public const READ = 'mcp:read';

    public const WRITE = 'mcp:write';

    public static function canRead(Request $request): bool
    {
        $abilities = self::abilities($request);

        // No token capabilities at all (non-Sanctum auth) can still read —
        // reads are harmless; writes fail closed below.
        if ($abilities === []) {
            return true;
        }

        return in_array('mcp', $abilities) || in_array(self::READ, $abilities);
    }

    public static function canWrite(Request $request): bool
    {
        $abilities = self::abilities($request);

        return in_array('mcp', $abilities) || in_array(self::WRITE, $abilities);
    }

    /**
     * "user@example.com · Claude Desktop" — the audit-trail label stamped
     * onto commands the token enqueues.
     */
    public static function actor(Request $request): string
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        return trim(($user?->email ?? 'unknown').' · '.($token?->name ?? 'token'));
    }

    /**
     * @return list<string>
     */
    private static function abilities(Request $request): array
    {
        $token = $request->user()?->currentAccessToken();

        if ($token === null) {
            return [];
        }

        // NewAccessToken (from createToken) wraps the PersonalAccessToken
        // model that actually holds the abilities.
        if (isset($token->accessToken)) {
            $token = $token->accessToken;
        }

        return array_values((array) ($token->abilities ?? []));
    }
}
