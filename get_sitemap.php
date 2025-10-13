<?php

define('RATE_LIMIT', 10);
define('RATE_LIMIT_WINDOW', 60);
define('MAX_DEPTH', 3);
define('MAX_XML_FETCH', 25);
define('TIMEOUT', 30);

function rateLimit($ip) {
    $dir = __DIR__ . '/ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . "/{$ip}.json";
    $currentTime = time();

    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
    } else {
        $data = ['requests' => 0, 'start_time' => $currentTime];
    }

    if ($currentTime - $data['start_time'] > RATE_LIMIT_WINDOW) {
        $data = ['requests' => 0, 'start_time' => $currentTime];
    }

    if ($data['requests'] >= RATE_LIMIT) {
        return false;
    }

    $data['requests']++;
    file_put_contents($filePath, json_encode($data));
    return true;
}

function processURL($url) {
    $hasProtocol = preg_match('/^https?:\/\//i', $url);
    if (!$hasProtocol) {
        $url = 'https://' . $url;
    }

    $parsedUrl = parse_url($url);
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
        throw new Exception('Invalid URL format');
    }

    $pathHasXml = isset($parsedUrl['path']) && strpos($parsedUrl['path'], '.xml') !== false;
    if ($pathHasXml) {
        try {
            $response = makeGETRequest($url);
            $body = $response['body'] ?? '';
            $contentType = $response['headers']['content-type'] ?? '';
            $isXmlType = stripos($contentType, 'xml') !== false;
            $hasUrlset = stripos($body, '<urlset') !== false;
            $hasIndex = stripos($body, '<sitemapindex') !== false;
            $okCode = $response['http_code'] === 200;
            $hasBody = strlen(trim($body)) > 32;

            if ($okCode && $hasBody && ($isXmlType || $hasUrlset || $hasIndex)) {
                return $url;
            }

            throw new Exception('Provided XML URL did not return sitemap content');
        } catch (Exception $e) {
            throw new Exception('Provided XML URL failed validation: ' . $e->getMessage());
        }
    }

    $base = 'https://' . $parsedUrl['host'];
    $candidates = [
        $base . '/sitemap.xml',
        $base . '/sitemap_index.xml',
        $base . '/wp-sitemap.xml',
        $base . '/sitemap.xml.gz'
    ];

    foreach ($candidates as $candidate) {
        try {
            $response = makeGETRequest($candidate);
            $body = $response['body'] ?? '';
            $contentType = $response['headers']['content-type'] ?? '';
            $isXmlType = stripos($contentType, 'xml') !== false;
            $hasUrlset = stripos($body, '<urlset') !== false;
            $hasIndex = stripos($body, '<sitemapindex') !== false;
            $okCode = $response['http_code'] === 200;
            $hasBody = strlen(trim($body)) > 32;

            if ($okCode && $hasBody && ($isXmlType || $hasUrlset || $hasIndex)) {
                return $candidate;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    throw new Exception('No sitemap found at standard locations (checked sitemap.xml, sitemap_index.xml, wp-sitemap.xml, sitemap.xml.gz). See debug log.');
}


// --- drop-in replacement ---
function makeGETRequest($targetUrl) {
    $curlHandle = curl_init();
    $verboseHandle = fopen('php://temp', 'w+');

    curl_setopt_array($curlHandle, [
        CURLOPT_URL => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => TIMEOUT,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/113 Safari/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
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
        error_log("cURL error: " . $curlError . "\nVerbose log:\n" . $verboseLog);
        curl_close($curlHandle);
        fclose($verboseHandle);
        throw new Exception('cURL error: ' . $curlError);
    }

    curl_close($curlHandle);
    fclose($verboseHandle);

    if ($httpCode !== 200) {
        throw new Exception('HTTP error: ' . $httpCode);
    }

    $headers = [];
    $headers['content-type'] = is_string($contentType) ? $contentType : '';

    return [
        'http_code' => $httpCode,
        'headers' => $headers,
        'body' => $responseBody
    ];
}


function parseXMLData($data) {
    $urls = [];

    if (preg_match_all('/<loc[^>]*>(.*?)<\/loc>/i', $data, $matches)) {
        foreach ($matches[1] as $url) {
            $url = trim($url);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $urls[] = $url;
            }
        }
    }

    if (empty($urls)) {
        throw new Exception("No valid URLs found in sitemap");
    }

    return $urls;
}

// --- replace your crawlSitemap() with this version to read ['body'] ---
function crawlSitemap($sitemapUrl, $remainingDepth = MAX_DEPTH, &$xmlProcessedCount = 0) {
    if ($remainingDepth < 0) {
        return [];
    }

    if ($xmlProcessedCount >= MAX_XML_FETCH) {
        return [];
    }

    $xmlProcessedCount = $xmlProcessedCount + 1;

    try {
        $response = makeGETRequest($sitemapUrl);
        $xmlData = $response['body'];
        $parsedUrls = parseXMLData($xmlData);
        $allCollectedUrls = $parsedUrls;

        $shouldRecurse = $remainingDepth > 0;
        if ($shouldRecurse) {
            $xmlUrls = [];

            foreach ($parsedUrls as $parsedUrl) {
                $endsWithXml = substr($parsedUrl, -4) === '.xml';
                if ($endsWithXml) {
                    $xmlUrls[] = $parsedUrl;
                }
            }

            foreach ($xmlUrls as $childXmlUrl) {
                if ($xmlProcessedCount >= MAX_XML_FETCH) {
                    break;
                }

                try {
                    $nestedUrls = crawlSitemap($childXmlUrl, $remainingDepth - 1, $xmlProcessedCount);
                    $allCollectedUrls = array_merge($allCollectedUrls, $nestedUrls);
                } catch (Exception $nestedError) {
                    continue;
                }
            }
        }

        return $allCollectedUrls;
    } catch (Exception $crawlError) {
        throw $crawlError;
    }
}


function filterAndSortUrls($urls) {
    $filtered = array_filter($urls, function($url) {
        return substr($url, -4) !== '.xml' && filter_var($url, FILTER_VALIDATE_URL);
    });

    $unique = array_unique($filtered);
    sort($unique);

    return array_values($unique);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL parameter is required']);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (!rateLimit($clientIp)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

try {
    $inputUrl = $_GET['url'];
    $processedUrl = processURL($inputUrl);
    $xmlCount = 0;
    $urls = crawlSitemap($processedUrl, MAX_DEPTH, $xmlCount);
    $finalUrls = filterAndSortUrls($urls);

    echo json_encode([
        'success' => true,
        'url' => $inputUrl,
        'sitemap' => $finalUrls,
        'xml_files_processed' => $xmlCount
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'url' => $_GET['url'],
        'message' => 'Error processing sitemap: ' . $e->getMessage()
    ]);
}

?>