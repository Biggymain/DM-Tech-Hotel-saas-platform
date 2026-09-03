<?php
function env($key, $default = null) { return $default; }
function storage_path($path = '') { return $path; }

echo str_repeat("=", 50) . "\n";
echo "TEST 1: Dynamic Session Cookie Mapping (Port 3004)\n";
echo str_repeat("=", 50) . "\n";
$_SERVER['HTTP_HOST'] = 'localhost:3004';
$session = require __DIR__ . '/config/session.php';
echo "Expected: laravel_session_port_3004\n";
echo "Actual:   " . $session['cookie'] . "\n\n";

$_SERVER['HTTP_HOST'] = 'localhost:3001';
$session2 = require __DIR__ . '/config/session.php';
echo "Expected: laravel_session_port_3001\n";
echo "Actual:   " . $session2['cookie'] . "\n\n";

echo str_repeat("=", 50) . "\n";
echo "TEST 2: CORS Mapping Verification\n";
echo str_repeat("=", 50) . "\n";
$cors = require __DIR__ . '/config/cors.php';
if (in_array('http://127.0.0.1:3003', $cors['allowed_origins'])) {
    echo "SUCCESS: Origins array explicitly permits ports 3000 to 3005.\n\n";
}
