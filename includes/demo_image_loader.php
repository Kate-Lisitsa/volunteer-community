<?php
/**
 * Скачивает демо-изображения в assets/images/demo/ (если файла ещё нет).
 */
function demoEnsureLocalImage(string $relativePath, string $sourceUrl, bool $force = false): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $dir = dirname($fullPath);

    if (!$force && is_file($fullPath) && filesize($fullPath) > 1024) {
        return $relativePath;
    }

    if ($force && is_file($fullPath)) {
        @unlink($fullPath);
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $relativePath;
    }

    $data = demoDownloadUrl($sourceUrl);
    if ($data !== null && strlen($data) > 1024) {
        file_put_contents($fullPath, $data);
        return $relativePath;
    }

    if (!$force && demoCopyFallbackImage($relativePath)) {
        return $relativePath;
    }

    return $relativePath;
}

function demoRefreshLocalImage(string $relativePath, string $sourceUrl): string
{
    return demoEnsureLocalImage($relativePath, $sourceUrl, true);
}

function demoCopyFallbackImage(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath) && filesize($fullPath) > 1024) {
        return true;
    }

    $candidates = [
        'assets/images/hero/slide-1.jpg',
        'assets/images/hero/slide-2.jpg',
        'assets/images/hero/slide-3.jpg',
    ];

    $demoDir = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'demo';
    if (is_dir($demoDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($demoDir));
        foreach ($it as $file) {
            if ($file->isFile() && preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
                $rel = 'assets/images/demo/' . str_replace('\\', '/', substr($file->getPathname(), strlen($demoDir) + 1));
                $candidates[] = $rel;
            }
        }
    }

    $candidates = array_values(array_unique($candidates));
    if ($candidates === []) {
        return false;
    }

    $idx = abs(crc32($relativePath)) % count($candidates);
    for ($n = 0; $n < count($candidates); $n++) {
        $srcRel = $candidates[($idx + $n) % count($candidates)];
        $src = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $srcRel);
        if (!is_file($src) || filesize($src) < 1024) {
            continue;
        }
        $dir = dirname($fullPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        return copy($src, $fullPath);
    }

    return false;
}

function demoDownloadUrl(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'DobroHubDemoSeed/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) {
            return $body;
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 45,
            'user_agent' => 'DobroHubDemoSeed/1.0',
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);

    return ($body !== false && strlen($body) > 1024) ? $body : null;
}

function demoImageFileExists(?string $relativePath): bool
{
    if ($relativePath === null || $relativePath === '') {
        return false;
    }
    $full = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));

    return is_file($full) && filesize($full) > 1024;
}

function demoPrepareImageCatalog(array $catalog, array $forceKeys = []): array
{
    foreach ($catalog as $key => $item) {
        if (empty($item['file']) || empty($item['url'])) {
            continue;
        }
        if (in_array($key, $forceKeys, true)) {
            demoRefreshLocalImage($item['file'], $item['url']);
        } else {
            demoEnsureLocalImage($item['file'], $item['url']);
        }
    }

    return $catalog;
}
