<?php

declare(strict_types=1);

final class LmsClient
{
    private string $baseUrl;

    private string $cookieFile;

    private ?string $csrfToken = null;

    public function __construct(string $baseUrl = 'http://127.0.0.1:8080')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir().'/lms_cookies_'.uniqid().'.txt';
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function initSession(): void
    {
        $ch = curl_init("{$this->baseUrl}/login");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
        ]);
        $html = (string) curl_exec($ch);
        curl_close($ch);

        if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $html, $m)) {
            $this->csrfToken = $m[1];
        } elseif (preg_match('/name="_token"\s+value="([^"]+)"/i', $html, $m)) {
            $this->csrfToken = $m[1];
        }
    }

    public function refreshCsrf(): void
    {
        $ch = curl_init("{$this->baseUrl}/my");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
        ]);
        $html = (string) curl_exec($ch);
        curl_close($ch);

        if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $html, $m)) {
            $this->csrfToken = $m[1];
        }
    }

    public function login(string $login, string $password): bool
    {
        $this->initSession();

        $ch = curl_init("{$this->baseUrl}/login");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'login' => $login,
                'password' => $password,
                '_token' => $this->csrfToken,
            ]),
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                "X-CSRF-TOKEN: {$this->csrfToken}",
            ],
        ]);
        $res = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->refreshCsrf();

        return in_array($status, [200, 204, 302], true);
    }

    public function get(string $path): array
    {
        $url = str_starts_with($path, 'http') ? $path : "{$this->baseUrl}/".ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $content = (string) curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        curl_close($ch);

        return [
            'status' => $status,
            'time_ms' => round($time, 2),
            'content_length' => strlen($content),
            'content' => $content,
        ];
    }

    public function post(string $path, array $data = [], array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : "{$this->baseUrl}/".ltrim($path, '/');
        $ch = curl_init($url);
        $data['_token'] = $this->csrfToken;
        $defaultHeaders = [
            'Accept: application/json',
            "X-CSRF-TOKEN: {$this->csrfToken}",
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, ['Content-Type: application/json'], $headers),
        ]);
        $content = (string) curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        curl_close($ch);

        return [
            'status' => $status,
            'time_ms' => round($time, 2),
            'content_length' => strlen($content),
            'content' => $content,
        ];
    }
}
