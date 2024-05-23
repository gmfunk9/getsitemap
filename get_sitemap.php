<?php

define('RATE_LIMIT', 5); // Number of allowed requests
define('RATE_LIMIT_WINDOW', 60); // Time window in seconds

/**
 * Rate limit function to control the number of requests from a specific IP address within a given time frame.
 * This function reads the request data from a file, checks if the current request is within the allowed limits,
 * and updates the request count accordingly. If the limit is exceeded, it returns false; otherwise, it returns true.
 *
 * @param string $ip The IP address of the client making the request.
 * @return bool Returns true if the request is within the allowed limit; otherwise, false.
 */
function rateLimit($ip) {
    $filePath = __DIR__ . "/ratelimit/{$ip}.json";
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

/**
 * Generates a list of URLs from a given sitemap URL, following any nested sitemap indexes up to a specified depth.
 * This function fetches the XML data from the sitemap URL, parses it to extract URLs, and recursively processes
 * any nested sitemap indexes. It returns a sorted list of all non-XML URLs found.
 *
 * @param string $url The URL of the sitemap to process.
 * @param int $maxDepth The maximum depth to follow nested sitemap indexes.
 * @return array Returns an array of URLs extracted from the sitemap.
 */
function generate($url, $maxDepth = 2) {
    $url = processURL($url);
    $options = setOptions();
    $data = makeGETRequest($url, $options);
    $sites = parseXMLData($data);
    $xmlUrls = array_filter($sites, function($site) {
        return substr($site, -4) === ".xml";
    });
    $urls = $sites;

    if ($maxDepth > 0) {
        foreach ($xmlUrls as $xmlUrl) {
            $urls = array_merge($urls, generate($xmlUrl, $maxDepth - 1));
        }
    }

    $urls = filterXMLUrls($urls);
    return sortUrls($urls);
}

/**
 * Processes a URL to ensure it is correctly formatted and points to the sitemap index if not specified.
 * This function adds "http://" if the URL does not start with "http" or "https", sets the protocol to "https:",
 * and modifies the path to "/sitemap_index.xml" if it is not already specified.
 *
 * @param string $url The URL to process.
 * @return string Returns the processed URL.
 */
function processURL($url) {
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = "http://" . $url;
    }

    $parsedUrl = parse_url($url);

    if (isset($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
        return $url;
    }

    $protocol = 'https://';
    $hostname = $parsedUrl['host'];

    $path = "/sitemap_index.xml";
    if (isset($parsedUrl['path']) && strpos($parsedUrl['path'], "/sitemap_index.xml") !== false) {
        $path = $parsedUrl['path'];
    }

    return $protocol . $hostname . $path;
}

/**
 * Makes an HTTP GET request to fetch the XML data from the given URL.
 * This function uses cURL to perform the HTTP request, handles SSL verification, and sets the necessary headers.
 * If the request is successful, it returns the response data; otherwise, it throws an exception.
 *
 * @param string $url The URL to fetch the XML data from.
 * @param array $options The options to set for the HTTP request.
 * @return string Returns the XML data fetched from the URL.
 * @throws Exception Throws an exception if the HTTP request fails.
 */
function makeGETRequest($url, $options) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    $data = curl_exec($ch);

    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new Exception("Failed to fetch the XML for the provided URL, status code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
    }

    curl_close($ch);
    return $data;
}

/**
 * Parses the XML data to extract URLs from <loc> tags.
 * This function uses a regular expression to match all <loc> tags in the XML data and extracts the URLs.
 * If no URLs are found, it throws an exception.
 *
 * @param string $data The XML data to parse.
 * @return array Returns an array of URLs extracted from the XML data.
 * @throws Exception Throws an exception if no URLs are found in the XML data.
 */
function parseXMLData($data) {
    preg_match_all('/<loc>(.*?)<\/loc>/', $data, $matches);
    if (empty($matches[1])) {
        throw new Exception("No sites found in the XML");
    }
    return $matches[1];
}

/**
 * Sets the options for the HTTP GET request, including the User-Agent header.
 * This function creates an array of options, setting an HTTPS agent that does not reject unauthorized SSL certificates
 * and setting the User-Agent header to a custom value.
 *
 * @return array Returns an array of options for the HTTP GET request.
 */
function setOptions() {
    return [
        'headers' => [
            "User-Agent: MyUserAgent/1.0",
        ],
    ];
}

/**
 * Filters out XML URLs from the given array of URLs.
 * This function removes any URLs that end with ".xml" from the provided array.
 *
 * @param array $urls The array of URLs to filter.
 * @return array Returns an array of URLs without XML URLs.
 */
function filterXMLUrls($urls) {
    return array_filter($urls, function($url) {
        return substr($url, -4) !== ".xml";
    });
}

/**
 * Sorts the given array of URLs in alphabetical order.
 * This function uses the locale-aware string comparison to sort the URLs.
 *
 * @param array $urls The array of URLs to sort.
 * @return array Returns the sorted array of URLs.
 */
function sortUrls($urls) {
    sort($urls);
    return $urls;
}

/**
 * Main entry point for handling the HTTP request.
 * This part of the script handles the incoming request, checks for the URL parameter, and applies rate limiting.
 * It calls the generate function to create the sitemap and returns the result as a JSON response.
 * If an error occurs, it returns an appropriate JSON error response.
 */
if (isset($_GET['url'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'];

    if (!rateLimit($clientIp)) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please try again later.']);
        exit;
    }

    try {
        $url = $_GET['url'];
        $urls = generate($url);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'url' => $url, 'sitemap' => $urls]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'url' => $url, 'message' => 'Error creating sitemap', 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL parameter is required']);
}

?>