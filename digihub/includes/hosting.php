<?php
// DigiHub — INFINITYFREE DATABASE SETTINGS
// ========================================
// Edit this ONE file with the details from vPanel -> MySQL Databases,
// then upload the contents of this folder into htdocs. Do not rename it.
//
// InfinityFree free hosting does not support SetEnv, so environment variables
// are not used here. If you move to a host that does provide them, DIGIHUB_DB_*
// variables still take precedence over the values below.

return [
    // MySQL hostname shown in vPanel, e.g. sql123.infinityfree.com
    'db_host' => 'sqlXXX.infinityfree.com',

    // Database name, e.g. if0_12345678_digihub
    'db_name' => 'if0_XXXXXXX_digihub',

    // Database username, e.g. if0_12345678
    'db_user' => 'if0_XXXXXXX',

    // Usually your InfinityFree account/vPanel password
    'db_pass' => 'YOUR_VPANEL_PASSWORD',

    // Leave empty to auto-detect. If links appear as http:// on an https site,
    // set this to your real URL, e.g. 'https://YOURNAME.infinityfreeapp.com/'
    'site_url' => '',
];
