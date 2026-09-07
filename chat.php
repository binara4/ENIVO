<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$apiKey = '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';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['messages']) || !is_array($data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Messages array is required']);
    exit();
}

$postFields = json_encode([
    'model' => 'openai/gpt-oss-20b',
    'messages' => $data['messages'],
    'temperature' => 0.3
]);

$response = false;
$httpCode = 500;

// Method 1: Try cURL if available
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

// Method 2: Fallback to stream context / file_get_contents
if ($response === false) {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer $apiKey\r\nContent-Type: application/json\r\n",
            'content' => $postFields,
            'ignore_errors' => true,
            'timeout' => 30
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $context);
    
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('#HTTP/\d\.\d\s+(\d+)#i', $hdr, $matches)) {
                $httpCode = intval($matches[1]);
                break;
            }
        }
    }
}

if ($response === false || empty($response)) {
    http_response_code(502);
    echo json_encode([
        'choices' => [
            [
                'message' => [
                    'role' => 'assistant',
                    'content' => "Welcome to Envio! I am here to help you with sizes, products, and styles. Feel free to browse our collection at store.html or contact us at +94 707 606 555 / enivo.clothing@gmail.com. Please visit our website to complete your purchase!"
                ]
            ]
        ],
        'fallback' => true
    ]);
    exit();
}

http_response_code($httpCode > 0 ? $httpCode : 200);
echo $response;
?>