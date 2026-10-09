<?php
/**
 * ========================================================
 * 🚀 PHP BACKEND ДЛЯ ПОЛУЧЕНИЯ ДАННЫХ ИЗ STEAM
 * ========================================================
 * Данный скрипт опционален, но крайне рекомендуется при размещении
 * загрузочного экрана на хостинге с поддержкой PHP (Apache / Nginx).
 * Он безопасно запрашивает аватарку и ник игрока/куратора из Steam
 * и кэширует результаты во избежание блокировок и задержек.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Ключ Steam Web API (получить на https://steamcommunity.com/dev/apikey)
// Если оставить пустым, скрипт попытается получить данные через Steam Community XML
$STEAM_API_KEY = ""; 

$steamid = isset($_GET['steamid']) ? trim($_GET['steamid']) : '';

if (empty($steamid) || !preg_match('/^\d{17}$/', $steamid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Некорректный SteamID64'
    ]);
    exit;
}

// Папка для кэширования профилей (на 24 часа)
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . '/' . md5($steamid) . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    echo file_get_contents($cacheFile);
    exit;
}

$playerData = null;

// Вариант 1: Использование официального Steam Web API (если указан ключ)
if (!empty($STEAM_API_KEY)) {
    $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$STEAM_API_KEY}&steamids={$steamid}";
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'header' => "User-Agent: Garry's Mod Loading\r\n"]
    ]);
    $json = @file_get_contents($apiUrl, false, $ctx);
    if ($json) {
        $res = json_decode($json, true);
        if (!empty($res['response']['players'][0])) {
            $p = $res['response']['players'][0];
            $playerData = [
                'status' => 'ok',
                'steamid' => $steamid,
                'personaname' => $p['personaname'] ?? 'Unknown',
                'avatar' => $p['avatarfull'] ?? ($p['avatarmedium'] ?? '')
            ];
        }
    }
}

// Вариант 2: Запрос через Steam Community XML
if (!$playerData) {
    $xmlUrl = "https://steamcommunity.com/profiles/{$steamid}?xml=1";
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"]
    ]);
    $xmlContent = @file_get_contents($xmlUrl, false, $ctx);
    if ($xmlContent) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml !== false && isset($xml->steamID)) {
            $playerData = [
                'status' => 'ok',
                'steamid' => $steamid,
                'personaname' => (string)$xml->steamID,
                'avatar' => (string)($xml->avatarFull ?: $xml->avatarMedium)
            ];
        }
    }
}

// Если данные успешно получены, сохраняем в кэш
if ($playerData) {
    $out = json_encode($playerData, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $out);
    echo $out;
} else {
    echo json_encode([
        'status' => 'fallback',
        'steamid' => $steamid,
        'personaname' => 'Player',
        'avatar' => ''
    ]);
}
