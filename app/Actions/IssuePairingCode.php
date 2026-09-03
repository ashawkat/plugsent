<?php

namespace App\Actions;

use App\Models\PairingCode;
use App\Models\Site;
use Carbon\CarbonInterface;
use Plugsent\ConnectorSigning\Signer;

class IssuePairingCode
{
    public const TTL_MINUTES = 15;

    /**
     * @return array{code: string, expires_at: CarbonInterface}
     */
    public function __invoke(Site $site): array
    {
        $code = Signer::pairingCode();

        PairingCode::query()->create([
            'site_id' => $site->getKey(),
            'token_hash' => Signer::codeHash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return [
            'code' => $code,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ];
    }
}
