<?php

use App\Mcp\Servers\PlugsentServer;
use Laravel\Mcp\Facades\Mcp;

// The MCP gateway: coding agents and chat clients talk to Plugsent over
// streamable HTTP, authenticated with a Sanctum bearer token generated on
// the My account page. Tools resolve through the same site policies as
// the dashboard, so a token never grants more than its owner could do.
Mcp::web('/mcp/plugsent', PlugsentServer::class)
    ->middleware(['auth:sanctum', 'throttle:120,1']);
