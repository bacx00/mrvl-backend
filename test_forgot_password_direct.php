<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

echo "\n🔐 Testing Forgot Password Directly\n";
echo "====================================\n\n";

try {
    // Create a request object with proper JSON content
    $data = ['email' => 'jhonny@ar-mediia.com'];
    $symfonyRequest = SymfonyRequest::create(
        '/api/auth/forgot-password',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($data)
    );
    
    $request = Illuminate\Http\Request::createFromBase($symfonyRequest);
    $request->setJson(new \Symfony\Component\HttpFoundation\InputBag($data));
    
    // Create controller instance
    $controller = new AuthController();
    
    // Call the method directly
    echo "📧 Calling forgot password endpoint...\n";
    $response = $controller->sendPasswordResetLinkEmail($request);
    
    // Get the response content
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    echo "\n📬 Response:\n";
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
    if ($data['success'] ?? false) {
        echo "\n✅ Forgot password request successful!\n";
        echo "   Email sent to: jhonny@ar-mediia.com\n";
        echo "   Check logs at: storage/logs/mail.log\n";
    } else {
        echo "\n❌ Forgot password request failed\n";
        echo "   Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}

echo "\n";