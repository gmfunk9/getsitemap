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
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = "http://" . $url;
    }

    $parsedUrl = parse_url($url);
    
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
        throw new Exception("Invalid URL format");
    }

    if (isset($parsedUrl['path']) && $parsedUrl['path'] !== '/' && strpos($parsedUrl['path'], '.xml') !== false) {
        return $url;
    }

    $protocol = 'https://';
    $hostname = $parsedUrl['host'];
    $path = "/sitemap_index.xml";

    return $protocol . $hostname . $path;
}

function makeGETRequest($url) {
    $ch = curl_init();
    $verbose = fopen('php://temp', 'w+');

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => TIMEOUT,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/113 Safari/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verbose,
        
    ]);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        rewind($verbose);
        $log = stream_get_contents($verbose);
        error_log("cURL error: $error\nVerbose log:\n$log");
        curl_close($ch);
        fclose($verbose);
        throw new Exception("cURL error: $error");
    }

    curl_close($ch);
    fclose($verbose);

    if ($httpCode !== 200) {
        throw new Exception("HTTP error: " . $httpCode);
    }

    return $data;
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

function crawlSitemap($url, $depth = MAX_DEPTH, &$xmlCount = 0) {
    if ($depth < 0 || $xmlCount >= MAX_XML_FETCH) {
        return [];
    }
    
    $xmlCount++;
    
    try {
        $data = makeGETRequest($url);
        $urls = parseXMLData($data);
        $allUrls = $urls;
        
        if ($depth > 0) {
            $xmlUrls = array_filter($urls, function($u) {
                return substr($u, -4) === '.xml';
            });
            
            foreach ($xmlUrls as $xmlUrl) {
                if ($xmlCount >= MAX_XML_FETCH) {
                    break;
                }
                
                try {
                    $subUrls = crawlSitemap($xmlUrl, $depth - 1, $xmlCount);
                    $allUrls = array_merge($allUrls, $subUrls);
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        return $allUrls;
        
    } catch (Exception $e) {
        throw $e;
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