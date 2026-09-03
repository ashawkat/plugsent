<?php

return [
    /*
    |------------------------------------------------------------------
    | Connector long-polling
    |------------------------------------------------------------------
    |
    | How long (seconds) the connector's poll request may be held open on
    | the server while waiting for new commands. Set to 0 to disable
    | long-polling (instant empty responses). Keep this below your
    | web server's request timeout.
    */
    'long_poll_seconds' => env('PLUGSENT_LONG_POLL_SECONDS', 25),
];
