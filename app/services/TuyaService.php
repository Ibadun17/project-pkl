<?php
// app/services/TuyaService.php

class TuyaService {
  private array $cfg;

  public function __construct() {
    $this->cfg = [
      'client_id' => getenv('TUYA_CLIENT_ID') ?: '',
      'secret'    => getenv('TUYA_CLIENT_SECRET') ?: '',
      'base_url'  => rtrim(getenv('TUYA_BASE_URL') ?: 'https://openapi-sg.iotbing.com', '/'),
    ];
  }

  private function ensureConfig(): void {
    if (!$this->cfg['client_id'] || !$this->cfg['secret'] || !$this->cfg['base_url']) {
      throw new Exception('Config Tuya belum lengkap. Cek TUYA_CLIENT_ID, TUYA_CLIENT_SECRET, TUYA_BASE_URL di .env');
    }
  }

  private function nowMs(): string {
    return (string)round(microtime(true) * 1000);
  }

  private function nonce(): string {
    return bin2hex(random_bytes(16));
  }

  private function sha256Hex(string $body): string {
    return hash('sha256', $body);
  }

  private function hmac(string $str): string {
    return strtoupper(hash_hmac('sha256', $str, $this->cfg['secret']));
  }

  /**
   * Signature v2 (new signature) untuk general API:
   * str = client_id + access_token + t + nonce + stringToSign
   * stringToSign = METHOD + "\n" + sha256(body) + "\n" + "\n" + pathWithQuery
   * Lihat doc "new signature" :contentReference[oaicite:3]{index=3}
   */
  private function signV2(string $accessToken, string $t, string $nonce, string $method, string $pathWithQuery, string $bodyJson): string {
    $contentSha256 = $this->sha256Hex($bodyJson);
    $stringToSign = strtoupper($method) . "\n" . $contentSha256 . "\n" . "\n" . $pathWithQuery;
    $str = $this->cfg['client_id'] . $accessToken . $t . $nonce . $stringToSign;
    return $this->hmac($str);
  }

  /**
   * Signature untuk token API (tanpa access_token):
   * str = client_id + t + nonce + stringToSign  (untuk new signature token)
   * beberapa akun masih menerima versi lama. Kalau token gagal, nanti kita adjust.
   */
  private function signTokenV2(string $t, string $nonce, string $method, string $pathWithQuery, string $bodyJson): string {
    $contentSha256 = $this->sha256Hex($bodyJson);
    $stringToSign = strtoupper($method) . "\n" . $contentSha256 . "\n" . "\n" . $pathWithQuery;
    $str = $this->cfg['client_id'] . $t . $nonce . $stringToSign;
    return $this->hmac($str);
  }

  private function curl(string $method, string $url, array $headers = [], ?string $bodyJson = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $hdr = [];
    foreach ($headers as $k => $v) $hdr[] = $k . ': ' . $v;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);

    if ($bodyJson !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    }

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
      throw new Exception('cURL error: ' . $err);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      throw new Exception("Response bukan JSON (HTTP $code): " . substr($raw, 0, 200));
    }

    // jika Tuya balikin success=false
    if (isset($data['success']) && $data['success'] === false) {
      $msg = $data['msg'] ?? $data['message'] ?? 'Tuya API error';
      $codeTuya = $data['code'] ?? '';
      throw new Exception("Tuya error $codeTuya: $msg");
    }

    return $data;
  }

  public function getAccessToken(): string {
    $this->ensureConfig();

    $t = $this->nowMs();
    $nonce = $this->nonce();

    $path = "/v1.0/token?grant_type=1";
    $url = $this->cfg['base_url'] . $path;

    $bodyJson = ""; // GET no body
    $sign = $this->signTokenV2($t, $nonce, 'GET', $path, $bodyJson);

    $headers = [
      'client_id' => $this->cfg['client_id'],
      'sign' => $sign,
      't' => $t,
      'nonce' => $nonce,
      'sign_method' => 'HMAC-SHA256',
    ];

    $res = $this->curl('GET', $url, $headers, null);
    $token = $res['result']['access_token'] ?? null;
    if (!$token) throw new Exception('Gagal ambil access_token dari Tuya');
    return $token;
  }

  public function getDeviceStatus(string $deviceId): array {
    $this->ensureConfig();
    $token = $this->getAccessToken();

    $t = $this->nowMs();
    $nonce = $this->nonce();

    $path = "/v1.0/devices/{$deviceId}/status";
    $url  = $this->cfg['base_url'] . $path;

    $bodyJson = "";
    $sign = $this->signV2($token, $t, $nonce, 'GET', $path, $bodyJson);

    $headers = [
      'client_id' => $this->cfg['client_id'],
      'access_token' => $token,
      'sign' => $sign,
      't' => $t,
      'nonce' => $nonce,
      'sign_method' => 'HMAC-SHA256',
      'Content-Type' => 'application/json',
    ];

    return $this->curl('GET', $url, $headers, null);
  }

  public function getDeviceDetail(string $deviceId): array {
    $this->ensureConfig();
    $token = $this->getAccessToken();

    $t = $this->nowMs();
    $nonce = $this->nonce();

    $path = "/v1.0/devices/{$deviceId}";
    $url  = $this->cfg['base_url'] . $path;

    $bodyJson = "";
    $sign = $this->signV2($token, $t, $nonce, 'GET', $path, $bodyJson);

    $headers = [
      'client_id' => $this->cfg['client_id'],
      'access_token' => $token,
      'sign' => $sign,
      't' => $t,
      'nonce' => $nonce,
      'sign_method' => 'HMAC-SHA256',
      'Content-Type' => 'application/json',
    ];

    return $this->curl('GET', $url, $headers, null);
  }

  public function setSwitch(string $deviceId, bool $on, string $code = 'switch_1'): array {
    $this->ensureConfig();
    $token = $this->getAccessToken();

    $t = $this->nowMs();
    $nonce = $this->nonce();

    $path = "/v1.0/devices/{$deviceId}/commands";
    $url  = $this->cfg['base_url'] . $path;

    $body = [
      'commands' => [
        ['code' => $code, 'value' => $on]
      ]
    ];
    $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);

    $sign = $this->signV2($token, $t, $nonce, 'POST', $path, $bodyJson);

    $headers = [
      'client_id' => $this->cfg['client_id'],
      'access_token' => $token,
      'sign' => $sign,
      't' => $t,
      'nonce' => $nonce,
      'sign_method' => 'HMAC-SHA256',
      'Content-Type' => 'application/json',
    ];

    return $this->curl('POST', $url, $headers, $bodyJson);
  }
}
