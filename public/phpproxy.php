<?php

$url = $_GET['url'] ?? '';
$serverMode = $_GET['s'] ?? $_GET['serverr'] ?? '0';

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid or missing URL';
    exit;
}

// Get file extension (lowercased)
$extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

// Default content-type fallback
$contentType = 'application/octet-stream';

if ($serverMode === '0') {


    header('Access-Control-Allow-Origin: *');
    header('Content-Type: video/mp4');

    // Forward content length if possible
    $headers = get_headers($url, 1);
    if (isset($headers['Content-Length'])) {
        header('Content-Length: ' . $headers['Content-Length']);
    }

    $context = stream_context_create(['http' => ['follow_location' => true]]);
    $stream = fopen($url, 'rb', false, $context);
    if ($stream) {
        fpassthru($stream);
        fclose($stream);
    } else {
        http_response_code(500);
        echo 'Failed to stream MP4';
    }

} else if ($serverMode === '1') {
    // === M3U8 or segment handling ===
    if ($extension === 'm3u8') {
        // --- Playlist Handling ---
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/vnd.apple.mpegurl');
        $playlist = file_get_contents($url);
        if ($playlist === false) {
            http_response_code(500);
            echo 'Failed to load playlist';
            exit;
        }


        $baseUrl = rtrim(dirname($url), '/');

        // Rewrite segment/playlist references
        $proxiedPlaylist = preg_replace_callback(
            '/^(?!#)(.+)$/m',
            function ($matches) use ($baseUrl) {
                $line = trim($matches[1]);
                if ($line === '') return '';

                $segmentUrl = parse_url($line, PHP_URL_SCHEME)
                    ? $line
                    : $baseUrl . '/' . ltrim($line, '/');

                return 'https://proxy-php-d724.onrender.com/?s=1&url=' . urlencode($segmentUrl);
            },
            $playlist
        );
        echo $proxiedPlaylist;
        exit;

    } else if (in_array($extension, ['ts', 'm4s', 'mp4'])) {
        // --- Media Segment Handling ---
        $contentTypes = [
            'ts' => 'video/mp2t',
            'm4s' => 'video/iso.segment',
            'mp4' => 'video/mp4'
        ];

        header('Access-Control-Allow-Origin: *');
        header('Content-Type: ' . ($contentTypes[$extension] ?? $contentType));

        $context = stream_context_create(['http' => ['follow_location' => true]]);
        $stream = fopen($url, 'rb', false, $context);
        if ($stream) {
            fpassthru($stream);
            fclose($stream);
        } else {
            http_response_code(500);
            echo 'Failed to load segment';
        }

    } else {
        http_response_code(400);
        echo 'Unsupported file type for s=1';
    }

} else {
    http_response_code(400);
    echo 'Invalid server mode value';
}
