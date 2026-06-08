<?php
/**
 * Linode Object Storage (S3 compatible) helper
 * Sin dependencias externas - usa AWS Signature V4
 */

function s3Upload($localPath, $remotePath, $contentType = null) {
    if (!file_exists($localPath)) {
        return false;
    }
    
    $content = file_get_contents($localPath);
    if ($content === false) {
        return false;
    }
    
    if (!$contentType) {
        $ext = strtolower(pathinfo($remotePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    }
    
    return s3PutObject($remotePath, $content, $contentType);
}

function s3PutObject($key, $content, $contentType = 'application/octet-stream') {
    $bucket = S3_BUCKET;
    $region = S3_REGION;
    $accessKey = S3_KEY;
    $secretKey = S3_SECRET;
    $host = str_replace('https://', '', S3_ENDPOINT);
    
    $date = gmdate('Ymd\THis\Z');
    $dateShort = gmdate('Ymd');
    $contentHash = hash('sha256', $content);
    
    // Headers canónicos
    $headers = [
        'host' => $bucket . '.' . $host,
        'x-amz-content-sha256' => $contentHash,
        'x-amz-date' => $date,
        'content-type' => $contentType,
        'x-amz-acl' => 'public-read',
    ];
    
    ksort($headers);
    
    $canonicalHeaders = '';
    $signedHeaders = [];
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        $signedHeaders[] = strtolower($k);
    }
    $signedHeadersStr = implode(';', $signedHeaders);
    
    // Request canónico
    $canonicalRequest = "PUT\n/" . $key . "\n\n" . $canonicalHeaders . "\n" . $signedHeadersStr . "\n" . $contentHash;
    
    // String to sign
    $scope = $dateShort . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    
    // Firma
    $kDate = hash_hmac('sha256', $dateShort, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    // Authorization header
    $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";
    
    // Hacer request
    $url = 'https://' . $bucket . '.' . $host . '/' . $key;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $content,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Host: ' . $bucket . '.' . $host,
            'Content-Type: ' . $contentType,
            'x-amz-content-sha256: ' . $contentHash,
            'x-amz-date: ' . $date,
            'x-amz-acl: public-read',
            'Authorization: ' . $authorization,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return S3_URL . '/' . $key;
    }
    
    error_log("S3 Upload Error: HTTP $httpCode - $error - Response: $response");
    return false;
}

function s3Delete($key) {
    $bucket = S3_BUCKET;
    $region = S3_REGION;
    $accessKey = S3_KEY;
    $secretKey = S3_SECRET;
    $host = str_replace('https://', '', S3_ENDPOINT);
    
    $date = gmdate('Ymd\THis\Z');
    $dateShort = gmdate('Ymd');
    $contentHash = hash('sha256', '');
    
    $headers = [
        'host' => $bucket . '.' . $host,
        'x-amz-content-sha256' => $contentHash,
        'x-amz-date' => $date,
    ];
    
    ksort($headers);
    
    $canonicalHeaders = '';
    $signedHeaders = [];
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        $signedHeaders[] = strtolower($k);
    }
    $signedHeadersStr = implode(';', $signedHeaders);
    
    $canonicalRequest = "DELETE\n/" . $key . "\n\n" . $canonicalHeaders . "\n" . $signedHeadersStr . "\n" . $contentHash;
    
    $scope = $dateShort . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    
    $kDate = hash_hmac('sha256', $dateShort, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";
    
    $url = 'https://' . $bucket . '.' . $host . '/' . $key;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Host: ' . $bucket . '.' . $host,
            'x-amz-content-sha256: ' . $contentHash,
            'x-amz-date: ' . $date,
            'Authorization: ' . $authorization,
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

function getS3Url($key) {
    return S3_URL . '/' . $key;
}
