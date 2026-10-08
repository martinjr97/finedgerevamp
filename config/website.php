<?php

return [
    /*
    | Shared secret for the standalone marketing website (server-to-server).
    | Send as header: X-Finedge-Website-Key
    */
    'api_key' => env('WEBSITE_API_KEY'),
];
