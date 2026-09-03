<?php
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
namespace {
    // We also need Closure to be typed cleanly in PHP 8? Closure is native.
    require_once __DIR__ . '/app/Http/Middleware/Security/SecureHeadersMiddleware.php';
    
    echo str_repeat("=", 50) . "\n";
    echo "TEST 3: SecureHeadersMiddleware CSP Binding (Port 3005)\n";
    echo str_repeat("=", 50) . "\n";

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
}
