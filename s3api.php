<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/S3CompatApi.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, x-amz-date, x-amz-content-sha256');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/xml');

try {
    $api = new S3CompatApi();
    $result = $api->handleRequest();
    
    if (is_array($result)) {
        if (isset($result['error'])) {
            $code = $result['code'] ?? 400;
            http_response_code($code);
            echo xmlResponse(['Error' => ['Message' => $result['error']]]);
        } else {
            echo xmlResponse($result);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo xmlResponse(['Error' => ['Message' => $e->getMessage()]]);
}

function xmlResponse($data) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root />');
    arrayToXml($data, $xml);
    return $xml->asXML();
}

function arrayToXml($data, &$xml) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }
            $subnode = $xml->addChild($key);
            arrayToXml($value, $subnode);
        } else {
            $xml->addChild("$key", htmlspecialchars("$value"));
        }
    }
}