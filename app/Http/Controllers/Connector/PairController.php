<?php

namespace App\Http\Controllers\Connector;

use App\Actions\EnqueueSiteCommand;
use App\Http\Controllers\Controller;
use App\Models\PairingCode;
use App\Models\SiteCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugsent\ConnectorSigning\Signer;

class PairController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'site_url' => ['required', 'url'],
            'name' => ['nullable', 'string', 'max:255'],
            'wp_version' => ['nullable', 'string', 'max:50'],
            'php_version' => ['nullable', 'string', 'max:50'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
        ]);

        $pairingCode = PairingCode::query()
            ->where('token_hash', Signer::codeHash($validated['code']))
            ->first();

        if ($pairingCode === null || ! $pairingCode->isUsable()) {
            return response()->json([
                'message' => 'Pairing code is invalid, expired, or already used.',
            ], 422);
        }

        $site = $pairingCode->site;

        $keyPair = Signer::generateKeyPair();

        SiteCredential::query()->updateOrCreate(
            ['site_id' => $site->getKey()],
            [
                'site_key' => $keyPair['site_key'],
                'secret' => $keyPair['site_secret'],
                'status' => 'active',
            ],
        );

        $site->forceFill([
            'status' => 'connected',
            'last_seen_at' => now(),
            'wp_version' => $validated['wp_version'] ?? $site->wp_version,
            'php_version' => $validated['php_version'] ?? $site->php_version,
            'capabilities' => $validated['capabilities'] ?? null,
        ])->save();

        $pairingCode->forceFill(['used_at' => now()])->save();

        app(EnqueueSiteCommand::class)($site, 'inventory.get');

        return response()->json([
            'site_key' => $keyPair['site_key'],
            'site_secret' => $keyPair['site_secret'],
        ], 201);
    }
}
