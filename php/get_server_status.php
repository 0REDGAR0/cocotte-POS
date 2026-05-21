<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

const INTERNET_TIMEOUT_SECONDS = 0.25;
const BATTERY_WINDOWS_WEB_PROBE_ENABLED = false;

function jsonResponse(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo '{"server_time":"00:00:00","internet_status":"unknown","battery_status":"unknown","battery_level_band":"unknown","battery_available":false}';
        exit;
    }

    echo $json;
    exit;
}

function isDebugEnabled(): bool
{
    return isset($_GET['debug']) && $_GET['debug'] === '1';
}

function isFunctionDisabled(string $functionName): bool
{
    $disabled = (string) ini_get('disable_functions');
    if ($disabled === '') {
        return false;
    }

    $functions = array_map('trim', explode(',', $disabled));

    return in_array($functionName, $functions, true);
}

function shortenError(string $message): string
{
    $message = trim(preg_replace('/\s+/', ' ', $message));

    if ($message === '') {
        return '';
    }

    return strlen($message) > 120 ? substr($message, 0, 117) . '...' : $message;
}

function runCommand(string $command): ?string
{
    if (!function_exists('shell_exec') || isFunctionDisabled('shell_exec')) {
        return null;
    }

    $stderrRedirect = PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
    $output = shell_exec($command . $stderrRedirect);
    if (!is_string($output)) {
        return null;
    }

    $output = trim($output);

    return $output === '' ? null : $output;
}

function detectInternetStatus(): array
{
    $debug = [
        'method' => 'none',
        'error' => '',
    ];

    if (!function_exists('fsockopen') || isFunctionDisabled('fsockopen')) {
        $debug['method'] = 'fsockopen-unavailable';
        $debug['error'] = 'fsockopen unavailable';

        return [
            'internet_status' => 'unknown',
            'debug' => $debug,
        ];
    }

    $targets = [
        ['host' => '1.1.1.1', 'port' => 443],
        ['host' => '8.8.8.8', 'port' => 53],
    ];

    $errors = [];

    foreach ($targets as $target) {
        $debug['method'] = 'fsockopen';
        $errno = 0;
        $errstr = '';

        try {
            $socket = @fsockopen($target['host'], $target['port'], $errno, $errstr, INTERNET_TIMEOUT_SECONDS);
        } catch (Throwable $throwable) {
            $debug['error'] = shortenError($throwable->getMessage());

            return [
                'internet_status' => 'unknown',
                'debug' => $debug,
            ];
        }

        if (is_resource($socket)) {
            fclose($socket);

            return [
                'internet_status' => 'online',
                'debug' => $debug,
            ];
        }

        $errors[] = $target['host'] . ':' . $target['port'] . ' ' . ($errno !== 0 ? $errno : 'no-connect') . ' ' . ($errstr !== '' ? $errstr : 'connection failed');
    }

    $debug['error'] = shortenError(implode(' | ', $errors));

    return [
        'internet_status' => 'offline',
        'debug' => $debug,
    ];
}

function getBatteryBand(?int $level): string
{
    if ($level === null) {
        return 'unknown';
    }

    if ($level >= 80) {
        return 'full';
    }

    if ($level >= 40) {
        return 'medium';
    }

    if ($level >= 20) {
        return 'low';
    }

    return 'critical';
}

function normalizeBatteryPayload(bool $available, ?string $status, ?int $level, string $method, string $error = ''): array
{
    return [
        'battery_status' => $available && $status !== null ? $status : 'unknown',
        'battery_level_band' => $available ? getBatteryBand($level) : 'unknown',
        'battery_available' => $available,
        'debug' => [
            'method' => $method,
            'error' => shortenError($error),
        ],
    ];
}

function mapWindowsBatteryStatus(?int $statusCode, ?int $level): string
{
    if ($statusCode === null) {
        return $level !== null && $level >= 100 ? 'full' : 'unknown';
    }

    if ($statusCode === 3) {
        return 'full';
    }

    if (in_array($statusCode, [6, 7, 8, 9, 11], true)) {
        return 'charging';
    }

    if (in_array($statusCode, [4, 5], true)) {
        return 'discharging';
    }

    if ($level !== null && $level >= 100) {
        return 'full';
    }

    return 'unknown';
}

function detectBatteryFromWindowsWmic(): ?array
{
    $output = runCommand('wmic path Win32_Battery get EstimatedChargeRemaining,BatteryStatus /format:list');
    if ($output === null) {
        return null;
    }

    if (stripos($output, 'No Instance(s) Available') !== false) {
        return normalizeBatteryPayload(false, null, null, 'wmic', 'no battery instance');
    }

    $level = null;
    $statusCode = null;

    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));

        if ($key === 'EstimatedChargeRemaining' && $value !== '' && is_numeric($value)) {
            $level = max(0, min(100, (int) $value));
        }

        if ($key === 'BatteryStatus' && $value !== '' && is_numeric($value)) {
            $statusCode = (int) $value;
        }
    }

    if ($level === null && $statusCode === null) {
        return normalizeBatteryPayload(false, null, null, 'wmic', 'battery fields unavailable');
    }

    return normalizeBatteryPayload(true, mapWindowsBatteryStatus($statusCode, $level), $level, 'wmic');
}

function detectBatteryFromWindowsPowerShell(): ?array
{
    $command = 'powershell -NoProfile -Command "$battery = Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue | Select-Object -First 1 EstimatedChargeRemaining,BatteryStatus; if ($null -eq $battery) { @{ available = $false } | ConvertTo-Json -Compress } else { $battery | ConvertTo-Json -Compress }"';
    $output = runCommand($command);
    if ($output === null) {
        return null;
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        return normalizeBatteryPayload(false, null, null, 'powershell-cim', 'invalid json from powershell');
    }

    if (array_key_exists('available', $decoded) && $decoded['available'] === false) {
        return normalizeBatteryPayload(false, null, null, 'powershell-cim', 'no battery instance');
    }

    $level = isset($decoded['EstimatedChargeRemaining']) && is_numeric($decoded['EstimatedChargeRemaining'])
        ? max(0, min(100, (int) $decoded['EstimatedChargeRemaining']))
        : null;
    $statusCode = isset($decoded['BatteryStatus']) && is_numeric($decoded['BatteryStatus'])
        ? (int) $decoded['BatteryStatus']
        : null;

    if ($level === null && $statusCode === null) {
        return normalizeBatteryPayload(false, null, null, 'powershell-cim', 'battery fields unavailable');
    }

    return normalizeBatteryPayload(true, mapWindowsBatteryStatus($statusCode, $level), $level, 'powershell-cim');
}

function detectBatteryFromTermux(): ?array
{
    $output = runCommand('termux-battery-status');
    if ($output === null) {
        return null;
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        return normalizeBatteryPayload(false, null, null, 'termux-battery-status', 'invalid json from termux');
    }

    $level = isset($decoded['percentage']) && is_numeric($decoded['percentage'])
        ? max(0, min(100, (int) $decoded['percentage']))
        : null;

    $rawStatus = isset($decoded['status']) ? strtoupper((string) $decoded['status']) : '';
    $rawPlugged = isset($decoded['plugged']) ? strtoupper((string) $decoded['plugged']) : '';

    if ($rawStatus === 'FULL') {
        $status = 'full';
    } elseif ($rawStatus === 'CHARGING' || ($rawPlugged !== '' && $rawPlugged !== 'UNPLUGGED')) {
        $status = 'charging';
    } elseif ($rawStatus === 'DISCHARGING' || $rawStatus === 'NOT_CHARGING' || $rawPlugged === 'UNPLUGGED') {
        $status = 'discharging';
    } else {
        $status = 'unknown';
    }

    if ($level === null && $status === 'unknown') {
        return normalizeBatteryPayload(false, null, null, 'termux-battery-status', 'no usable battery fields');
    }

    return normalizeBatteryPayload(true, $status, $level, 'termux-battery-status');
}

function detectBatteryStatus(): array
{
    $family = PHP_OS_FAMILY;

    if ($family === 'Windows') {
        if (PHP_SAPI !== 'cli' && BATTERY_WINDOWS_WEB_PROBE_ENABLED === false) {
            return normalizeBatteryPayload(false, null, null, 'windows-web-skip', 'battery probe skipped on windows web');
        }

        $wmic = detectBatteryFromWindowsWmic();
        if ($wmic !== null) {
            return $wmic;
        }

        $powerShell = detectBatteryFromWindowsPowerShell();
        if ($powerShell !== null) {
            return $powerShell;
        }

        return normalizeBatteryPayload(false, null, null, 'windows-fallback', 'no battery probe available');
    }

    $termux = detectBatteryFromTermux();
    if ($termux !== null) {
        return $termux;
    }

    return normalizeBatteryPayload(false, null, null, 'unsupported-platform', 'battery probe unavailable');
}

$payload = [
    'server_time' => date('H:i:s'),
    'internet_status' => 'unknown',
    'battery_status' => 'unknown',
    'battery_level_band' => 'unknown',
    'battery_available' => false,
];

$debug = [
    'internet_method' => 'none',
    'internet_error' => '',
    'battery_method' => 'none',
    'battery_error' => '',
];

try {
    $internet = detectInternetStatus();
    $payload['internet_status'] = $internet['internet_status'];
    $debug['internet_method'] = $internet['debug']['method'];
    $debug['internet_error'] = $internet['debug']['error'];
} catch (Throwable $throwable) {
    $payload['internet_status'] = 'unknown';
    $debug['internet_method'] = 'exception';
    $debug['internet_error'] = shortenError($throwable->getMessage());
}

try {
    $battery = detectBatteryStatus();
    $payload['battery_status'] = $battery['battery_status'];
    $payload['battery_level_band'] = $battery['battery_level_band'];
    $payload['battery_available'] = $battery['battery_available'];
    $debug['battery_method'] = $battery['debug']['method'];
    $debug['battery_error'] = $battery['debug']['error'];
} catch (Throwable $throwable) {
    $payload['battery_status'] = 'unknown';
    $payload['battery_level_band'] = 'unknown';
    $payload['battery_available'] = false;
    $debug['battery_method'] = 'exception';
    $debug['battery_error'] = shortenError($throwable->getMessage());
}

if (isDebugEnabled()) {
    $payload['debug'] = $debug;
}

jsonResponse($payload);
