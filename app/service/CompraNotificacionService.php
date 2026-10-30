<?php

/**
 * Confirmación de compra: correo siempre; WhatsApp Cloud API después (plantilla Meta).
 * De momento la mesa usa el botón wa.me con mensaje corto + enlace a jugar.
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
        // WhatsApp automático (plantilla Meta) queda para más adelante.
        // Hoy: botón "WhatsApp" en mesa → wa.me con mensaje corto + Jugar.
    }

    /**
     * Enlace wa.me (mesa): mensaje tipo plantilla corto + URL para jugar la partida.
     *
     * @param list<int> $cartonIds
     */
    public function urlWhatsAppManual(
        ?string $celular,
        string $nombreCliente,
        string $nombrePartida,
        array $cartonIds,
        ?int $usuarioId = null,
        ?int $partidaId = null
    ): ?string {
        $ids = array_values(array_filter(array_map('intval', $cartonIds)));
        $lista = $ids === []
            ? 'tus cartones'
            : implode(', ', array_map(static fn (int $id): string => '#' . $id, $ids));
        $n = count($ids);
        $etiqueta = $n <= 0 ? 'tus cartones' : ($n === 1 ? '1 carton' : "{$n} cartones");
        $nombre = trim($nombreCliente) !== '' ? trim($nombreCliente) : 'jugador';
        $next = ($partidaId !== null && $partidaId > 0)
            ? '#/vivo/' . $partidaId
            : '#/mis-cartones';

        $urlJugar = EmailPlantilla::urlApp($next);

        if ($usuarioId !== null && $usuarioId > 0) {
            $usuario = $this->usuarioRepository->buscarPorId($usuarioId);
            if ($usuario !== null) {
                $urlJugar = $this->urlAccesoConToken($usuario, $next);
            }
        }

        return WhatsAppService::urlManual(
            $celular,
            $this->textoWhatsAppCorto($nombre, $nombrePartida, $etiqueta, $lista, $urlJugar)
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
        $nextVivo = '#/vivo/' . $partidaId;
        $urlJugar = $this->urlAccesoConToken($usuario, $nextVivo);
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
            'partidaId' => $partidaId,
            'cartones' => $cartones,
            'urlJugar' => $urlJugar,
            'listaIds' => $listaIds,
            'n' => $n,
            'etiqueta' => $etiqueta,
            'celular' => (string) ($usuario['celular'] ?? ''),
        ];
    }

    /**
     * Enlace absoluto con JWT (#/acceso?token=…&next=…partida).
     *
     * @param array<string, mixed> $usuario
     */
    private function urlAccesoConToken(array $usuario, string $next = '#/mis-cartones'): string
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

        if ($next === '' || !str_starts_with($next, '#/')) {
            $next = '#/mis-cartones';
        }

        return EmailPlantilla::urlApp(
            '#/acceso?token=' . rawurlencode($token) . '&next=' . rawurlencode($next)
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

        // Texto plano sin pegar el JWT (el HTML lleva el botón Jugar con el acceso).
        $texto = "Hola {$datos['nombre']},\n\n"
            . "Tu compra quedó confirmada para: {$datos['nombrePartida']}.\n"
            . "Cartones: {$datos['listaIds']}\n\n"
            . "Abre el correo en HTML y toca Jugar para entrar.\n\n"
            . EmailPlantilla::marca() . "\n";

        try {
            $this->mailer->enviar($correo, $asunto, $html, $texto);
        } catch (Throwable $exception) {
            error_log('CompraNotificacionService correo: ' . $exception->getMessage());
        }
    }

    /**
     * Mensaje corto estilo plantilla (wa.me manual). Luego se mapea a plantilla Meta.
     */
    private function textoWhatsAppCorto(
        string $nombre,
        string $partida,
        string $etiqueta,
        string $listaIds,
        string $urlJugar
    ): string {
        $marca = EmailPlantilla::marca();

        return "Hola {$nombre},\n\n"
            . "Compra confirmada\n"
            . "Bingo: {$partida}\n"
            . "{$etiqueta}: {$listaIds}\n\n"
            . "Jugar:\n"
            . "{$urlJugar}\n\n"
            . "-- {$marca}";
    }
}
