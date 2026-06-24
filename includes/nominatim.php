<?php
/**
 * Поиск адреса через Nominatim (OpenStreetMap). Без API-ключа.
 * Соблюдайте лимит ~1 запрос/сек с одного IP (см. rate limit в location_suggest.php).
 */

function nominatimHttpJson($url) {
    $ua = NOMINATIM_USER_AGENT;
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: {$ua}\r\nAccept: application/json\r\nAccept-Language: ru,be,en\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false && extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $ua,
                'Accept: application/json',
                'Accept-Language: ru,be,en',
            ],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Точки сбора — только Республика Беларусь (ISO 3166-1 alpha-2: by).
 */
function nominatimRowIsBelarus(array $row) {
    if (empty($row['address']) || !is_array($row['address'])) {
        return false;
    }
    $cc = isset($row['address']['country_code']) ? strtolower((string)$row['address']['country_code']) : '';
    return $cc === 'by';
}

/**
 * @return list<array{display_name:string,lat:string,lon:string,osm_type:string,osm_id:int}>
 */
function nominatimSearchSuggestions($query) {
    $q = trim((string)$query);
    $len = mb_strlen($q, 'UTF-8');
    if ($len < 3) {
        return [];
    }
    if ($len > 180) {
        $q = mb_substr($q, 0, 180, 'UTF-8');
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q' => $q,
        'limit' => 12,
        'addressdetails' => 1,
        'countrycodes' => 'by',
        'accept-language' => 'ru,be,en',
    ], '', '&', PHP_QUERY_RFC3986);

    $data = nominatimHttpJson($url);
    if ($data === null || !is_array($data)) {
        return [];
    }

    $out = [];
    foreach ($data as $row) {
        if (empty($row['display_name']) || empty($row['osm_type']) || !isset($row['osm_id'])) {
            continue;
        }
        $type = strtolower((string)$row['osm_type']);
        if (!in_array($type, ['node', 'way', 'relation'], true)) {
            continue;
        }
        if (!nominatimRowIsBelarus($row)) {
            continue;
        }
        $out[] = [
            'display_name' => (string)$row['display_name'],
            'lat' => isset($row['lat']) ? (string)$row['lat'] : '',
            'lon' => isset($row['lon']) ? (string)$row['lon'] : '',
            'osm_type' => $type,
            'osm_id' => (int)$row['osm_id'],
        ];
        if (count($out) >= 7) {
            break;
        }
    }
    return $out;
}

/**
 * Проверка, что объект OSM существует; возвращает запись с display_name.
 *
 * @return array{display_name?:string,...}|null
 */
function nominatimLookupOsm($osmType, $osmId) {
    $t = strtolower(trim((string)$osmType));
    if (!in_array($t, ['node', 'way', 'relation'], true)) {
        return null;
    }
    $id = (int)$osmId;
    if ($id < 1) {
        return null;
    }
    $prefix = ['node' => 'N', 'way' => 'W', 'relation' => 'R'][$t];
    $url = 'https://nominatim.openstreetmap.org/lookup?' . http_build_query([
        'osm_ids' => $prefix . $id,
        'format' => 'json',
        'addressdetails' => 1,
        'accept-language' => 'ru,be,en',
    ], '', '&', PHP_QUERY_RFC3986);

    $data = nominatimHttpJson($url);
    if ($data === null || !isset($data[0]) || !is_array($data[0])) {
        return null;
    }
    return $data[0];
}

/**
 * Проверка места по выбранной подсказке карты; в $normalized подставляется строка для БД.
 */
function nominatimValidateAndNormalizeLocation($osmType, $osmId, &$normalized) {
    $normalized = null;
    $place = nominatimLookupOsm($osmType, $osmId);
    if (!$place || empty($place['display_name'])) {
        return 'Не удалось подтвердить точку на карте. Выберите адрес из подсказок ещё раз.';
    }
    if (!nominatimRowIsBelarus($place)) {
        return 'Точка сбора может быть только на территории Беларуси. Выберите адрес из подсказок.';
    }
    $dn = trim((string)$place['display_name']);
    if (mb_strlen($dn, 'UTF-8') > 300) {
        $dn = mb_substr($dn, 0, 300, 'UTF-8');
    }
    $normalized = $dn;
    return '';
}
