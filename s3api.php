<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/S3CompatApi.php';

header('Content-Type: application/xml');

try {
    $api = new S3CompatApi();
    $result = $api->handleRequest();
    
    if (is_array($result)) {
        if (isset($result['error'])) {
            http_response_code(400);
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