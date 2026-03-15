<?php
class JWT {
    public static function encode(array $payload): string {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $payload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
        $signature = base64_encode($signature);
        return "$header.$payload.$signature";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $sig] = $parts;
        $validSig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        if (!hash_equals($validSig, $sig)) return null;
        $data = json_decode(base64_decode($payload), true);
        if (!$data || $data['exp'] < time()) return null;
        return $data;
    }

    public static function getFromRequest(): ?array {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return self::decode($m[1]);
        }
        // Also check cookie for web sessions
        if (!empty($_COOKIE['admin_token'])) {
            return self::decode($_COOKIE['admin_token']);
        }
        return null;
    }

    public static function requireAuth(): array {
        $payload = self::getFromRequest();
        if (!$payload) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }
        return $payload;
    }
}