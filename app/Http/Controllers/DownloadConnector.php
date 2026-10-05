<?php

namespace App\Http\Controllers;

use App\Support\ConnectorRelease;
use Illuminate\Http\RedirectResponse;

class DownloadConnector extends Controller
{
    /**
     * Send the browser straight to the connector's latest release ZIP on
     * GitHub (or the releases page when the API cannot be reached) — the
     * panel never stores or proxies the ZIP itself.
     */
    public function __invoke(): RedirectResponse
    {
        return redirect()->away(ConnectorRelease::downloadUrl());
    }
}
