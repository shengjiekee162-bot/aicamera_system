<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = str_replace('\\', '/', dirname($script));

    if (preg_match('#/(admin|cashier|api)$#', $base)) {
        $base = str_replace('\\', '/', dirname($base));
    }

    // On Windows dirname('/admin') may return a backslash. Never let that
    // become "//admin/...", which browsers interpret as the host "admin".
    $base = trim($base, "/\\.");
    $base = $base === '' ? '' : '/' . $base;

    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419); exit('Invalid CSRF token. Please refresh and try again.');
    }
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(?string $role = null): void {
    if (!user()) redirect('login.php');
    if ($role && user()['role'] !== $role) { http_response_code(403); exit('Access denied.'); }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function setting(string $key, string $default = ''): string {
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]); return (string)($stmt->fetchColumn() ?: $default);
}
function money(float|string $amount): string { return 'RM' . number_format((float)$amount, 2); }
function payment_label(string $method): string {
    return match ($method) {
        'cash' => 'Cash',
        'tng', 'qr' => 'TNG eWallet',
        'card' => 'Card',
        default => strtoupper($method),
    };
}
function curl_tls_options(): array {
    $options = [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    // PHP on Windows may have no curl.cainfo configured. In that case use
    // the trusted certificates maintained by Windows instead of disabling TLS.
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }

    return $options;
}
function gemini_configurations(): array {
    $configs = [];
    // One shared API key is used for every model fallback slot.
    $sharedKey = trim(setting('gemini_api_key', setting('gemini_api_key_1')));
    if ($sharedKey === '') {
        return [];
    }
    for ($slot = 1; $slot <= 5; $slot++) {
        $legacyModel = $slot === 1 ? setting('gemini_model', 'gemini-3.7-flash') : '';
        $model = trim(setting('gemini_model_' . $slot, $legacyModel));
        if ($model !== '') {
            $configs[] = ['slot' => $slot, 'model' => $model, 'key' => $sharedKey];
        }
    }
    $activeSlot = max(1, min(5, (int)setting('gemini_active_slot', '1')));
    usort($configs, static function (array $a, array $b) use ($activeSlot): int {
        $aOrder = ($a['slot'] - $activeSlot + 5) % 5;
        $bOrder = ($b['slot'] - $activeSlot + 5) % 5;
        return $aOrder <=> $bOrder;
    });
    return $configs;
}
function gemini_generate(array $payload, int $timeout = 45): array {
    $configs = gemini_configurations();
    if (!$configs) {
        return ['success' => false, 'code' => 0, 'message' => 'No Gemini model and API key are configured.'];
    }

    $attempts = [];
    foreach ($configs as $index => $config) {
        $apiTemplate = setting('gemini_api_url', 'https://generativelanguage.googleapis.com/v1/models/{model}:generateContent');
        $endpoint = str_replace('{model}', rawurlencode($config['model']), $apiTemplate);
        $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . 'key=' . rawurlencode($config['key']);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
        ] + curl_tls_options());
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string)$body, true);
        $message = $decoded['error']['message'] ?? ($curlError ?: 'Gemini returned HTTP ' . $code . '.');
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'code' => $code, 'body' => $decoded, 'model' => $config['model'], 'slot' => $config['slot'], 'attempts' => $attempts];
        }

        $attempts[] = 'Slot ' . $config['slot'] . ' (' . $config['model'] . '): ' . $message;
        $isLimit = $code === 429 || stripos($message, 'quota') !== false || stripos($message, 'resource_exhausted') !== false || stripos($message, 'rate limit') !== false;
        $isTemporary = in_array($code, [404, 500, 502, 503, 504], true) || $curlError !== '';
        if (!$isLimit && !$isTemporary) {
            break;
        }
    }

    return ['success' => false, 'code' => $code ?? 0, 'message' => implode(' | ', $attempts), 'attempts' => $attempts];
}
function audit(string $action, string $details = ''): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([user()['id'] ?? null, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
