<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';

        if ($secret === '') {
            throw new RuntimeException(
                'JWT_SECRET no está configurado.'
            );
        }

        $ttl = (int) ($_ENV['JWT_TTL'] ?? 86400);

        if ($ttl <= 0) {
            throw new RuntimeException(
                'JWT_TTL debe ser mayor que cero.'
            );
        }

        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    /**
     * @param array $claims
     * @param int|null $ttlSegundos Si se envía, sustituye el TTL por defecto (útil para enlace de invitado).
     */
    public function emitir(array $claims, ?int $ttlSegundos = null): string
    {
        $ahora = time();
        $ttl = $ttlSegundos !== null && $ttlSegundos > 0
            ? $ttlSegundos
            : $this->ttl;

        $payload = array_merge($claims, [
            'iat' => $ahora,
            'exp' => $ahora + $ttl,
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verificar(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
