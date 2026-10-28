<?php

function ensureArticleReferencesColumn(): void {
    if (!function_exists('db') || !defined('DB_NAME')) {
        return;
    }

    $db = db();
    $schema = $db->real_escape_string(DB_NAME);
    $exists = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$schema}' AND TABLE_NAME = 'news' AND COLUMN_NAME = 'article_references' LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
        $db->query("ALTER TABLE news ADD COLUMN article_references JSON DEFAULT NULL AFTER inline_images");
    }
}

function decodeArticleReferences(?string $json): array {
    if (!$json) {
        return [];
    }

    $references = json_decode($json, true);
    if (!is_array($references)) {
        return [];
    }

    $validReferences = [];
    foreach ($references as $reference) {
        if (!is_array($reference) || !is_string($reference['title'] ?? null) || !is_string($reference['url'] ?? null)) {
            continue;
        }
        $title = trim($reference['title']);
        $url = trim($reference['url']);
        $parts = parse_url($url);
        if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            continue;
        }
        $validReferences[] = ['title' => $title, 'url' => $url];
    }

    return $validReferences;
}

function validateArticleReferences(array $titles, array $urls, ?string &$error = null): ?array {
    $references = [];
    $rowCount = max(count($titles), count($urls));

    for ($i = 0; $i < $rowCount; $i++) {
        $titleValue = $titles[$i] ?? '';
        $urlValue = $urls[$i] ?? '';
        if (!is_scalar($titleValue) || !is_scalar($urlValue)) {
            $error = 'Reference titles and URLs must be plain text.';
            return null;
        }
        $title = trim((string)$titleValue);
        $url = trim((string)$urlValue);
        if ($title === '' && $url === '') {
            continue;
        }
        if ($title === '' || $url === '') {
            $error = 'Each reference needs both a title and a URL.';
            return null;
        }
        if (mb_strlen($title) > 200 || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Enter a link title up to 200 characters and a valid URL up to 2048 characters.';
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            $error = 'Reference links must use a valid HTTP or HTTPS URL.';
            return null;
        }

        $references[] = ['title' => $title, 'url' => $url];
    }

    return $references;
}
