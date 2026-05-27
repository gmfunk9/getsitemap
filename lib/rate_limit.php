<?php

function rateLimit(string $clientAddress, array $config): bool {
    $directory = __DIR__ . '/../' . $config['rate_limit_storage'];
    ensureDirectory($directory);

    $filePath = getRateLimitFilePath($directory, $clientAddress);
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

function getRateLimitFilePath(string $directory, string $clientAddress): string {
    $hashedClientAddress = hash('sha256', $clientAddress);
    return $directory . '/' . $hashedClientAddress . '.json';
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
