<?php

$config = require __DIR__ . '/config/app.php';

setJsonHeaders();
handleOptionsRequest($_SERVER['REQUEST_METHOD'] ?? 'GET');
handleRequest($_GET, $_SERVER, $config);

function setJsonHeaders(): void {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
}

function handleOptionsRequest(string $method): void {
    if ($method !== 'OPTIONS') {
        return;
    }

    exit(0);
}

function handleRequest(array $query, array $server, array $config): void {
    if (!array_key_exists('url', $query)) {
        respondWithJson(400, buildResponse(false, [
            'message' => 'Missing field url; add to query.'
        ]));
        return;
    }

    $clientIpAddress = '127.0.0.1';
    if (array_key_exists('REMOTE_ADDR', $server)) {
        $clientIpAddress = $server['REMOTE_ADDR'];
    }

    $isAllowed = rateLimit($clientIpAddress, $config);
    if ($isAllowed === false) {
        respondWithJson(429, buildResponse(false, [
            'message' => 'Rate limit exceeded for address ' . $clientIpAddress . '. Try again later.'
        ]));
        return;
    }

    try {
        $inputUrl = $query['url'];
        $resolvedSitemap = resolveSitemapUrl($inputUrl, $config);
        $processedCount = 0;
        $collectedUrls = crawlSitemap($resolvedSitemap, $config['max_depth'], $config, $processedCount);
        $finalUrls = filterAndSortUrls($collectedUrls);

        respondWithJson(200, buildResponse(true, [
            'url' => $inputUrl,
            'sitemap' => $finalUrls,
            'xml_files_processed' => $processedCount
        ]));
    } catch (Exception $error) {
        respondWithJson(400, buildResponse(false, [
            'url' => $query['url'],
            'message' => 'Error processing sitemap: ' . $error->getMessage()
        ]));
    }
}

function respondWithJson(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload);
}

function buildResponse(bool $success, array $data): array {
    $response = ['success' => $success];

    foreach ($data as $key => $value) {
        $response[$key] = $value;
    }

    return $response;
}

function rateLimit(string $clientAddress, array $config): bool {
    $directory = __DIR__ . '/' . $config['rate_limit_storage'];
    ensureDirectory($directory);

    $filePath = $directory . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $clientAddress) . '.json';
    $currentTime = time();

    $data = loadRateLimitData($filePath, $currentTime);
    $elapsed = $currentTime - $data['start_time'];
    $windowExpired = $elapsed > $config['rate_limit_window_seconds'];
    if ($windowExpired) {
        $data = [
            'requests' => 0,
            'start_time' => $currentTime
        ];
    }

    $isLimited = $data['requests'] >= $config['rate_limit_requests'];
    if ($isLimited) {
        return false;
    }

    $data['requests'] = $data['requests'] + 1;
    file_put_contents($filePath, json_encode($data));

    return true;
}

function ensureDirectory(string $directory): void {
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0755, true);
}

function loadRateLimitData(string $filePath, int $currentTime): array {
    if (!file_exists($filePath)) {
        return [
            'requests' => 0,
            'start_time' => $currentTime
        ];
    }

    $rawContent = file_get_contents($filePath);
    if ($rawContent === false) {
        return [
            'requests' => 0,
            'start_time' => $currentTime
        ];
    }

    $decodedData = json_decode($rawContent, true);
    if (!is_array($decodedData)) {
        return [
            'requests' => 0,
            'start_time' => $currentTime
        ];
    }

    if (!array_key_exists('requests', $decodedData)) {
        $decodedData['requests'] = 0;
    }

    if (!array_key_exists('start_time', $decodedData)) {
        $decodedData['start_time'] = $currentTime;
    }

    return $decodedData;
}

function resolveSitemapUrl(string $rawUrl, array $config): string {
    $normalizedInput = normalizeInputUrl($rawUrl);
    $parsedUrl = parse_url($normalizedInput);
    if ($parsedUrl === false) {
        throw new Exception('Invalid URL format; parsing failed.');
    }

    $hasHost = array_key_exists('host', $parsedUrl);
    if (!$hasHost) {
        throw new Exception('Invalid URL format; missing host.');
    }

    $pathContainsXml = false;
    if (array_key_exists('path', $parsedUrl)) {
        $pathContainsXml = strpos($parsedUrl['path'], '.xml') !== false;
    }

    if ($pathContainsXml) {
        return validateSitemapUrl($normalizedInput, $config);
    }

    $hostName = $parsedUrl['host'];
    return discoverSitemapUrl($hostName, $config);
}

function normalizeInputUrl(string $input): string {
    $trimmed = trim($input);
    if ($trimmed === '') {
        throw new Exception('Missing URL value; provide ?url=example.com');
    }

    $hasProtocol = preg_match('/^https?:\/\//i', $trimmed) === 1;
    if ($hasProtocol) {
        return $trimmed;
    }

    return 'https://' . $trimmed;
}

function validateSitemapUrl(string $sitemapUrl, array $config): string {
    $response = makeGETRequest($sitemapUrl, $config);
    $isSitemap = responseHasSitemap($response, $config['min_body_length']);
    if ($isSitemap) {
        return $sitemapUrl;
    }

    throw new Exception('Provided XML URL did not return sitemap content.');
}

function discoverSitemapUrl(string $hostName, array $config): string {
    $baseUrl = 'https://' . $hostName;
    $candidates = [
        $baseUrl . '/sitemap.xml',
        $baseUrl . '/sitemap_index.xml',
        $baseUrl . '/wp-sitemap.xml',
        $baseUrl . '/sitemap.xml.gz'
    ];

    foreach ($candidates as $candidateUrl) {
        try {
            $response = makeGETRequest($candidateUrl, $config);
            $isSitemap = responseHasSitemap($response, $config['min_body_length']);
            if ($isSitemap) {
                return $candidateUrl;
            }
        } catch (Exception $error) {
            continue;
        }
    }

    throw new Exception('No sitemap found at standard locations. Checked sitemap.xml, sitemap_index.xml, wp-sitemap.xml, sitemap.xml.gz.');
}

function makeGETRequest(string $targetUrl, array $config): array {
    $curlHandle = curl_init();
    $verboseHandle = fopen('php://temp', 'w+');

    curl_setopt_array($curlHandle, [
        CURLOPT_URL => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => $config['request_timeout_seconds'],
        CURLOPT_USERAGENT => $config['user_agent'],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => $config['max_redirects'],
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verboseHandle
    ]);

    curl_setopt($curlHandle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $responseBody = curl_exec($curlHandle);
    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE);

    if (curl_errno($curlHandle)) {
        $curlError = curl_error($curlHandle);
        rewind($verboseHandle);
        $verboseLog = stream_get_contents($verboseHandle);
        error_log('cURL error: ' . $curlError . "\nVerbose log:\n" . $verboseLog);
        curl_close($curlHandle);
        fclose($verboseHandle);
        throw new Exception('Request failed: ' . $curlError);
    }

    curl_close($curlHandle);
    fclose($verboseHandle);

    if ($httpCode !== 200) {
        throw new Exception('HTTP error: ' . $httpCode);
    }

    $headers = ['content-type' => ''];
    if (is_string($contentType)) {
        $headers['content-type'] = $contentType;
    }

    if (!is_string($responseBody)) {
        throw new Exception('Empty response body received.');
    }

    return [
        'http_code' => $httpCode,
        'headers' => $headers,
        'body' => $responseBody
    ];
}

function responseHasSitemap(array $response, int $minBodyLength): bool {
    if ($response['http_code'] !== 200) {
        return false;
    }

    $body = '';
    if (array_key_exists('body', $response)) {
        $body = (string) $response['body'];
    }

    $trimmed = trim($body);
    $hasContent = strlen($trimmed) > $minBodyLength;
    if (!$hasContent) {
        return false;
    }

    $headers = '';
    if (array_key_exists('headers', $response)) {
        if (array_key_exists('content-type', $response['headers'])) {
            $headers = (string) $response['headers']['content-type'];
        }
    }

    $isXmlHeader = stripos($headers, 'xml') !== false;
    if ($isXmlHeader) {
        return true;
    }

    $hasUrlset = stripos($body, '<urlset') !== false;
    if ($hasUrlset) {
        return true;
    }

    $hasIndex = stripos($body, '<sitemapindex') !== false;
    if ($hasIndex) {
        return true;
    }

    return false;
}

function crawlSitemap(string $sitemapUrl, int $remainingDepth, array $config, int &$xmlProcessedCount): array {
    if ($remainingDepth < 0) {
        return [];
    }

    if ($xmlProcessedCount >= $config['max_xml_fetch']) {
        return [];
    }

    $xmlProcessedCount = $xmlProcessedCount + 1;
    $response = makeGETRequest($sitemapUrl, $config);
    $xmlData = $response['body'];
    $parsedUrls = extractUrlsFromXml($xmlData);
    $allCollectedUrls = $parsedUrls;

    if ($remainingDepth === 0) {
        return $allCollectedUrls;
    }

    $childXmlUrls = filterXmlLinks($parsedUrls);

    foreach ($childXmlUrls as $childXmlUrl) {
        if ($xmlProcessedCount >= $config['max_xml_fetch']) {
            break;
        }

        try {
            $nestedUrls = crawlSitemap($childXmlUrl, $remainingDepth - 1, $config, $xmlProcessedCount);
            $allCollectedUrls = array_merge($allCollectedUrls, $nestedUrls);
        } catch (Exception $error) {
            continue;
        }
    }

    return $allCollectedUrls;
}

function extractUrlsFromXml(string $xmlContent): array {
    $matches = [];
    preg_match_all('/<loc[^>]*>(.*?)<\/loc>/i', $xmlContent, $matches);

    $extractedUrls = [];
    if (!array_key_exists(1, $matches)) {
        throw new Exception('No valid URLs found in sitemap.');
    }

    foreach ($matches[1] as $rawLocation) {
        $cleanUrl = trim($rawLocation);
        if ($cleanUrl === '') {
            continue;
        }

        $isValid = filter_var($cleanUrl, FILTER_VALIDATE_URL) !== false;
        if (!$isValid) {
            continue;
        }

        $extractedUrls[] = $cleanUrl;
    }

    if (count($extractedUrls) === 0) {
        throw new Exception('No valid URLs found in sitemap.');
    }

    return $extractedUrls;
}

function filterXmlLinks(array $candidateUrls): array {
    $xmlLinks = [];

    foreach ($candidateUrls as $candidateUrl) {
        $isXml = substr($candidateUrl, -4) === '.xml';
        if (!$isXml) {
            continue;
        }

        $xmlLinks[] = $candidateUrl;
    }

    return $xmlLinks;
}

function filterAndSortUrls(array $collectedUrls): array {
    $filtered = [];

    foreach ($collectedUrls as $candidateUrl) {
        $isXml = substr($candidateUrl, -4) === '.xml';
        if ($isXml) {
            continue;
        }

        $isValid = filter_var($candidateUrl, FILTER_VALIDATE_URL) !== false;
        if (!$isValid) {
            continue;
        }

        $filtered[] = $candidateUrl;
    }

    $unique = array_values(array_unique($filtered));
    sort($unique);

    return $unique;
}
