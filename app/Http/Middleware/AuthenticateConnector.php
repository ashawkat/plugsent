<?php

namespace App\Http\Middleware;

use App\Models\SiteCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Plugsent\ConnectorSigning\Signer;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateConnector
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-Plugsent-Key', '');
        $timestamp = (string) $request->header('X-Plugsent-Timestamp', '');
        $nonce = (string) $request->header('X-Plugsent-Nonce', '');
        $signature = (string) $request->header('X-Plugsent-Signature', '');

        if ($key === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return $this->reject();
        }

        $credentials = SiteCredential::query()
            ->where('site_key', $key)
            ->where('status', 'active')
            ->first();

        if ($credentials === null) {
            return $this->reject();
        }

        $verified = Signer::verify(
            $credentials->secret,
            (int) $timestamp,
            (string) $request->getContent(),
            $signature,
        );

        if (! $verified) {
            return $this->reject();
        }

        // Replay protection: a nonce may only ever be used once.
        $seen = Cache::add(
            "connector:nonce:{$key}:".hash('sha256', $nonce),
            1,
            now()->addMinutes(10),
        );

        if (! $seen) {
            return $this->reject();
        }

        $request->attributes->set('connector.site', $credentials->site);

        return $next($request);
    }

    private function reject(): Response
    {
        return response()->json(['message' => 'Invalid or expired connector credentials.'], 401);
    }
}
