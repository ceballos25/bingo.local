<?php

/**
 * Valida JWT Bearer (o ?token= para SSE) y deja el usuario en AuthContext.
 */
final class AuthGuard
{
    public function __construct(
        private JwtService $jwtService,
        private UsuarioRepository $usuarioRepository
    ) {
    }

    /**
     * @param list<string> $rolesPermitidos vacío = cualquier rol autenticado
     */
    public function exigir(array $rolesPermitidos = []): array
    {
        $token = $this->extraerToken();

        if ($token === null) {
            $this->fallar(401, 'Debes enviar el token Bearer.');
        }

        try {
            $claims = $this->jwtService->verificar($token);
        } catch (Throwable $exception) {
            $this->fallar(401, 'Token inválido o expirado.');
        }

        $usuarioId = (int) ($claims->sub ?? 0);

        if ($usuarioId <= 0) {
            $this->fallar(401, 'Token inválido o expirado.');
        }

        $fila = $this->usuarioRepository->buscarPorId($usuarioId);

        if ($fila === null) {
            $this->fallar(401, 'Token inválido o expirado.');
        }

        if (($fila['estado'] ?? '') !== 'activo') {
            $this->fallar(403, 'El usuario está suspendido.');
        }

        unset($fila['password']);

        if (!empty($claims->soloJugador)) {
            $fila['rol'] = 'jugador';
        }

        $rol = (string) ($fila['rol'] ?? '');

        if (
            $rolesPermitidos !== []
            && !in_array($rol, $rolesPermitidos, true)
        ) {
            $this->fallar(403, 'No tienes permiso para esta acción.');
        }

        AuthContext::establecer($fila);

        return $fila;
    }

    private function extraerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            foreach ($headers as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) {
                    $header = $valor;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $coincidencias)) {
            return $coincidencias[1];
        }

        // EventSource no envía headers: permite ?token= solo para SSE.
        $queryToken = $_GET['token'] ?? null;

        if (is_string($queryToken) && trim($queryToken) !== '') {
            return trim($queryToken);
        }

        return null;
    }

    private function fallar(int $codigoHttp, string $mensaje): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            ['error' => $mensaje],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        exit;
    }
}
