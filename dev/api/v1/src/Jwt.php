<?php
/**
 * Implementação mínima de JWT (HS256), sem dependências externas.
 */
class Jwt
{
    private static function b64(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64dec(string $d): string
    {
        return base64_decode(strtr($d, '-_', '+/')) ?: '';
    }

    public static function gerar(array $dados): string
    {
        $cfg     = (require __DIR__ . '/../config.php')['jwt'];
        $payload = $dados + ['iat' => time(), 'exp' => time() + $cfg['validade']];
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256', "$h.$p", $cfg['segredo'], true));
        return "$h.$p.$s";
    }

    /** Retorna o payload se o token for válido e não estiver expirado. */
    public static function validar(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) { return null; }
        $cfg = (require __DIR__ . '/../config.php')['jwt'];
        $esperada = self::b64(hash_hmac('sha256', "$partes[0].$partes[1]", $cfg['segredo'], true));
        if (!hash_equals($esperada, $partes[2])) { return null; }
        $payload = json_decode(self::b64dec($partes[1]), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) { return null; }
        return $payload;
    }
}
