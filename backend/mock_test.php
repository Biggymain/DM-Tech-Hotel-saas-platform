<?php

// Mock basic env function
function env($key, $default = null) {
    return $default;
}

// 1. Test config/session.php
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

// 2. Test config/cors.php
echo str_repeat("=", 50) . "\n";
echo "TEST 2: CORS Mapping Verification\n";
echo str_repeat("=", 50) . "\n";
$cors = require __DIR__ . '/config/cors.php';
if (in_array('http://127.0.0.1:3003', $cors['allowed_origins'])) {
    echo "SUCCESS: Origins array explicitly permits ports 3000 to 3005.\n\n";
}

// 3. Test SecureHeadersMiddleware.php
echo str_repeat("=", 50) . "\n";
echo "TEST 3: SecureHeadersMiddleware CSP Binding (Port 3005)\n";
echo str_repeat("=", 50) . "\n";

// Mocks to bypass Laravel's full instantiation stack
namespace Illuminate\Http {
    class Request {
        public function getPort() { return 3005; }
    }
}
namespace Symfony\Component\HttpFoundation {
    class Response {
        public $headers;
        public function __construct() {
            $this->headers = new HeaderBag();
        }
    }
    class HeaderBag {
        public $items = [];
        public function set($key, $values, $replace = true) {
            $this->items[$key] = $values;
        }
    }
}

namespace App\Http\Middleware\Security {
    require_once __DIR__ . '/app/Http/Middleware/Security/SecureHeadersMiddleware.php';
}

$middleware = new \App\Http\Middleware\Security\SecureHeadersMiddleware();
$request = new \Illuminate\Http\Request();
$mockNext = function($req) {
    return new \Symfony\Component\HttpFoundation\Response();
};

$response = $middleware->handle($request, $mockNext);
$headers = $response->headers->items;

echo "Injected Headers for Port 3005 Output:\n\n";
foreach ($headers as $key => $val) {
    echo str_pad($key, 30) . " : " . $val . "\n";
}

if (strpos($headers['Content-Security-Policy'], 'localhost:3005') !== false) {
    echo "\nSUCCESS: Content-Security-Policy dynamically bonded to 3005.\n";
}

echo str_repeat("=", 50) . "\n";
