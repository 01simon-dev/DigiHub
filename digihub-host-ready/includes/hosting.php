<?php
// DigiHub — production / InfinityFree database settings
// ======================================================
// Edit these values with vPanel -> MySQL Databases details BEFORE uploading.
// Only includes/hosting.php needs editing; no other file contains credentials.
//
//   db_host: the MySQL hostname shown in vPanel, e.g. sql123.infinityfree.com
//   db_name: e.g. if0_12345678_digihub
//   db_user: e.g. if0_12345678
//   db_pass: usually your InfinityFree account/vPanel password
//   site_url: leave empty unless links are generated with the wrong scheme/path.
//             Example: https://yourname.infinityfreeapp.com/
//
// Environment variables (DIGIHUB_DB_HOST, DIGIHUB_DB_NAME, DIGIHUB_DB_USER,
// DIGIHUB_DB_PASS, DIGIHUB_SITE_URL) still take precedence when available.
// On localhost/XAMPP the local defaults are used automatically, so you can
// keep this file with placeholders while developing.

return [
    'db_host'  => 'sql113.infinityfree.com',
    'db_name'  => 'if0_43518762_digihub',
    'db_user'  => 'if0_43518762',
    'db_pass'  => 'digihub1234',
    'site_url' => '',
];
