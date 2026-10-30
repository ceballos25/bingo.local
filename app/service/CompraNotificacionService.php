<?php

/**
 * Confirmación de compra: correo + WhatsApp.
 * Incluye enlace mágico con JWT (#/acceso?token=…) para entrar y jugar.
 */
final class CompraNotificacionService
{
    private const TTL_ENLACE_DIAS = 30;

    public function __construct(
        private MailerService $mailer,
        private WhatsAppService $whatsApp,
        private JwtService $jwtService,
        private UsuarioRepository $usuarioRepository,
        private PartidaRepository $partidaRepository,
        private CartonRepository $cartonRepository
    ) {
    }

    /**
     * @param list<int> $cartonIds
     */
    public function notificarCompra(
        int $usuarioId,
        int $partidaId,
        array $cartonIds
    ): void {
        $datos = $this->prepararDatos($usuarioId, $partidaId, $cartonIds);

        if ($datos === null) {
            return;
        }

        $this->enviarCorreo($datos);
        $this->enviarWhatsApp($datos);
    }

    /**
     * Enlace wa.me para que la mesa reenvíe a mano.
     *
     * @param list<int> $cartonIds
     */
    public function urlWhatsAppManual(
        ?string $celular,
        string $nombreCliente,
        string $nombrePartida,
        array $cartonIds,
        ?int $usuarioId = null
    ): ?string {
        $ids = array_values(array_filter(array_map('intval', $cartonIds)));
        $lista = $ids === []
            ? 'tus cartones'
            : implode(', ', array_map(static fn (int $id): string => '#' . $id, $ids));
        $n = count($ids);
        $etiqueta = $n <= 0 ? 'tus cartones' : ($n === 1 ? '1 cartón' : "{$n} cartones");
        $nombre = trim($nombreCliente) !== '' ? trim($nombreCliente) : 'jugador';

        $urlJugar = EmailPlantilla::urlApp('#/mis-cartones');

        if ($usuarioId !== null && $usuarioId > 0) {
            $usuario = $this->usuarioRepository->buscarPorId($usuarioId);
            if ($usuario !== null) {
                $urlJugar = $this->urlAccesoConToken($usuario);
            }
        }

        return WhatsAppService::urlManual(
            $celular,
            $this->textoWhatsApp($nombre, $nombrePartida, $etiqueta, $lista, $urlJugar)
        );
    }

    /**
     * @param list<int> $cartonIds
     * @return array<string, mixed>|null
     */
    private function prepararDatos(
        int $usuarioId,
        int $partidaId,
        array $cartonIds
    ): ?array {
        $cartonIds = array_values(array_unique(array_map('intval', $cartonIds)));
        $cartonIds = array_values(array_filter($cartonIds, static fn (int $id): bool => $id > 0));

        if ($usuarioId <= 0 || $partidaId <= 0 || $cartonIds === []) {
            return null;
        }

        $usuario = $this->usuarioRepository->buscarPorId($usuarioId);
        $partida = $this->partidaRepository->buscarPorId($partidaId);

        if ($usuario === null || $partida === null) {
            return null;
        }

        $cartones = [];

        foreach ($cartonIds as $cartonId) {
            $fila = $this->cartonRepository->buscarPorId($cartonId);

            if ($fila === null) {
                continue;
            }

            if ((int) ($fila['usuarioId'] ?? 0) !== $usuarioId) {
                continue;
            }

            $cartones[] = [
                'id' => (int) $fila['id'],
                'numeros' => is_array($fila['numeros'] ?? null) ? $fila['numeros'] : [],
            ];
        }

        if ($cartones === []) {
            return null;
        }

        $nombre = trim(
            trim((string) ($usuario['nombre'] ?? ''))
            . ' '
            . trim((string) ($usuario['apellido'] ?? ''))
        );

        if ($nombre === '') {
            $nombre = 'jugador';
        }

        $nombrePartida = (string) ($partida['nombre'] ?? 'Bingo');
        $urlJugar = $this->urlAccesoConToken($usuario);
        $listaIds = implode(', ', array_map(
            static fn (array $c): string => '#' . $c['id'],
            $cartones
        ));
        $n = count($cartones);
        $etiqueta = $n === 1 ? '1 cartón' : "{$n} cartones";

        return [
            'usuario' => $usuario,
            'nombre' => $nombre,
            'nombrePartida' => $nombrePartida,
            'cartones' => $cartones,
            'urlJugar' => $urlJugar,
            'listaIds' => $listaIds,
            'n' => $n,
            'etiqueta' => $etiqueta,
            'celular' => (string) ($usuario['celular'] ?? ''),
        ];
    }

    /**
     * Enlace absoluto con JWT (mismo patrón que invitado).
     * El token viaja dentro del link: #/acceso?token=…
     *
     * @param array<string, mixed> $usuario
     */
    private function urlAccesoConToken(array $usuario): string
    {
        $ttl = self::TTL_ENLACE_DIAS * 24 * 60 * 60;
        $token = $this->jwtService->emitir(
            [
                'sub' => (int) $usuario['id'],
                'organizacionId' => (int) $usuario['organizacionId'],
                'rol' => (string) ($usuario['rol'] ?? 'jugador'),
                'invitado' => true,
                'compra' => true,
            ],
            $ttl
        );

        return EmailPlantilla::urlApp(
            '#/acceso?token=' . rawurlencode($token) . '&next=' . rawurlencode('#/mis-cartones')
        );
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function enviarCorreo(array $datos): void
    {
        $correo = trim((string) ($datos['usuario']['correo'] ?? ''));

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            error_log('CompraNotificacionService: sin correo válido');

            return;
        }

        $html = EmailPlantilla::cartonesComprados(
            (string) $datos['nombre'],
            (string) $datos['nombrePartida'],
            $datos['cartones'],
            (string) $datos['urlJugar']
        );

        $n = (int) $datos['n'];
        $asunto = $n === 1
            ? 'Confirmación: tu cartón de ' . $datos['nombrePartida']
            : 'Confirmación: tus cartones de ' . $datos['nombrePartida'];

        $texto = "Hola {$datos['nombre']},\n\n"
            . "Tu compra quedó CONFIRMADA para: {$datos['nombrePartida']}.\n"
            . "Cartones: {$datos['listaIds']}\n\n"
            . "Entra con tu enlace personal (ya lleva tu acceso):\n"
            . "{$datos['urlJugar']}\n\n"
            . "Válido " . self::TTL_ENLACE_DIAS . " días. Guarda este mensaje.\n\n"
            . EmailPlantilla::marca() . "\n";

        try {
            $this->mailer->enviar($correo, $asunto, $html, $texto);
        } catch (Throwable $exception) {
            error_log('CompraNotificacionService correo: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function enviarWhatsApp(array $datos): void
    {
        $texto = $this->textoWhatsApp(
            (string) $datos['nombre'],
            (string) $datos['nombrePartida'],
            (string) $datos['etiqueta'],
            (string) $datos['listaIds'],
            (string) $datos['urlJugar']
        );

        // Plantilla Meta: {{1}} nombre {{2}} bingo {{3}} enlace con token
        $params = [
            (string) $datos['nombre'],
            (string) $datos['nombrePartida'],
            (string) $datos['urlJugar'],
        ];

        try {
            $this->whatsApp->enviarConfirmacion(
                (string) $datos['celular'],
                $texto,
                $params
            );
        } catch (Throwable $exception) {
            error_log('CompraNotificacionService WhatsApp: ' . $exception->getMessage());
        }
    }

    private function textoWhatsApp(
        string $nombre,
        string $partida,
        string $etiqueta,
        string $listaIds,
        string $urlJugar
    ): string {
        $marca = EmailPlantilla::marca();

        return "Hola {$nombre} 👋\n\n"
            . "✅ *Compra confirmada*\n"
            . "Bingo: *{$partida}*\n"
            . "{$etiqueta}: {$listaIds}\n\n"
            . "Toca este enlace para entrar y jugar (ya incluye tu acceso):\n"
            . "{$urlJugar}\n\n"
            . "Guarda este mensaje. El enlace vale " . self::TTL_ENLACE_DIAS . " días.\n"
            . "— {$marca}";
    }
}
