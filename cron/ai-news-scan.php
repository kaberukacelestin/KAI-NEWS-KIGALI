<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/ai-news.php';

try {
    $result = aiNewsRunScheduledScan();
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    $hasErrors = !empty($result['errors']) || !empty($result['drafts']['errors']);
    exit($hasErrors ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'AI news scan failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
