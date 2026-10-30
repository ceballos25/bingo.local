<?php

final class VentaService
{
    public function __construct(
        private PDO $database,
        private CartonRepository $cartonRepository,
        private PartidaRepository $partidaRepository,
        private UsuarioRepository $usuarioRepository,
        private PagoRepository $pagoRepository,
        private ComprobanteStorage $comprobanteStorage,
        private CiudadRepository $ciudadRepository,
        private PasarelaPago $pasarelaPago,
        private ?CompraNotificacionService $compraNotificacionService = null
    ) {
    }

    /**
     * @return list<array>
     */
    public function listarParaMesa(
        int $organizacionId,
        ?string $estado = null,
        ?int $partidaId = null
    ): array {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'organizacionId debe ser mayor que cero.'
            );
        }

        $estadoNorm = $estado !== null ? strtolower(trim($estado)) : null;

        if (
            $estadoNorm !== null &&
            $estadoNorm !== '' &&
            !in_array($estadoNorm, ['pendiente', 'aprobado', 'rechazado', 'expirado'], true)
        ) {
            throw new InvalidArgumentException(
                'estado no válido.'
            );
        }

        if ($estadoNorm === '') {
            $estadoNorm = null;
        }

        $lista = $this->pagoRepository->listarParaMesa(
            $organizacionId,
            $estadoNorm,
            $partidaId
        );

        if ($this->compraNotificacionService === null) {
            return $lista;
        }

        foreach ($lista as &$pago) {
            $pago['whatsappUrl'] = $this->compraNotificacionService->urlWhatsAppManual(
                (string) ($pago['clienteCelular'] ?? ''),
                trim(
                    (string) ($pago['clienteNombre'] ?? '')
                    . ' '
                    . (string) ($pago['clienteApellido'] ?? '')
                ),
                (string) ($pago['partidaNombre'] ?? 'Bingo'),
                [(int) ($pago['cartonId'] ?? 0)],
                (int) ($pago['usuarioId'] ?? 0)
            );
        }
        unset($pago);

        return $lista;
    }

    public function ventaManual(
        int $cartonId,
        int $usuarioId,
        int $operadorId
    ): array {
        if ($cartonId <= 0 || $usuarioId <= 0 || $operadorId <= 0) {
            throw new InvalidArgumentException(
                'cartonId, usuarioId y operadorId deben ser mayores que cero.'
            );
        }

        $contexto = $this->prepararVentaDisponible($cartonId, $usuarioId);
        $this->asegurarOperadorValido($operadorId, $contexto['organizacionId']);

        $carton = $contexto['carton'];
        $carton->vender($contexto['jugadorId']);

        $pago = new Pago(
            $contexto['organizacionId'],
            $contexto['partidaId'],
            $cartonId,
            $contexto['jugadorId'],
            'manual_operador',
            $contexto['monto'],
            'aprobado',
            $operadorId
        );

        return $this->confirmarVentaEnTransaccion(
            $carton,
            $pago,
            $contexto['partidaId'],
            $cartonId,
            true
        );
    }

    /**
     * Asigna cartones sin cobro cuando la partida es gratis (precio 0 / flag).
     * El jugador autenticado reclama sus propios cartones.
     *
     * @param list<int> $cartonIds
     * @return list<array>
     */
    public function ventaGratis(array $cartonIds, int $usuarioId): array
    {
        $cartonIds = array_values(array_unique(array_map('intval', $cartonIds)));

        if ($cartonIds === [] || $usuarioId <= 0) {
            throw new InvalidArgumentException(
                'Debes enviar al menos un cartonId y un usuario válido.'
            );
        }

        foreach ($cartonIds as $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'Cada cartonId debe ser mayor que cero.'
                );
            }
        }

        $resultados = [];
        $partidaId = null;

        foreach ($cartonIds as $cartonId) {
            $contexto = $this->prepararVentaDisponible($cartonId, $usuarioId);
            $this->asegurarPartidaGratis($contexto['partidaId']);

            if ($partidaId === null) {
                $partidaId = $contexto['partidaId'];
            } elseif ($contexto['partidaId'] !== $partidaId) {
                throw new DomainException(
                    'Todos los cartones gratis deben ser de la misma partida.'
                );
            }

            if ((float) $contexto['monto'] > 0) {
                throw new DomainException(
                    'Esta partida no es gratis; debes pagar el cartón.'
                );
            }

            $carton = $contexto['carton'];
            $carton->vender($contexto['jugadorId']);

            $pago = new Pago(
                $contexto['organizacionId'],
                $contexto['partidaId'],
                $cartonId,
                $contexto['jugadorId'],
                'gratis',
                0.0,
                'aprobado',
                null
            );

            $resultados[] = $this->confirmarVentaEnTransaccion(
                $carton,
                $pago,
                $contexto['partidaId'],
                $cartonId,
                false
            );
        }

        $this->notificarCartones(
            $usuarioId,
            (int) $partidaId,
            $cartonIds
        );

        return $resultados;
    }

    private function asegurarPartidaGratis(int $partidaId): void
    {
        $partida = $this->partidaRepository->buscarPorId($partidaId);

        if ($partida === null) {
            throw new DomainException('La partida no existe.');
        }

        $gratis = !empty($partida['gratis']);
        $precio = (float) ($partida['precioCarton'] ?? 0);

        if (!$gratis && $precio > 0) {
            throw new DomainException(
                'Esta partida no está marcada como gratis.'
            );
        }
    }

    /**
     * Un cargo PSE en OpenPay puede cubrir uno o varios cartones.
     * Se crea un pago pendiente por cartón y un solo redirect al banco con el total.
     *
     * @param list<int> $cartonIds
     */
    public function iniciarPse(
        array $cartonIds,
        int $usuarioId,
        string $redirectUrl
    ): array {
        $cartonIds = array_values(array_unique(array_map('intval', $cartonIds)));

        if ($cartonIds === [] || $usuarioId <= 0) {
            throw new InvalidArgumentException(
                'Debes enviar al menos un cartonId y un usuario válido.'
            );
        }

        foreach ($cartonIds as $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'Cada cartonId debe ser mayor que cero.'
                );
            }
        }

        $redirectUrl = trim($redirectUrl);

        if ($redirectUrl === '' || filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                'Debes enviar redirectUrl como URL válida.'
            );
        }

        $contextos = [];
        $partidaId = null;
        $organizacionId = null;
        $jugadorId = null;
        $montoTotal = 0.0;

        foreach ($cartonIds as $cartonId) {
            $contexto = $this->prepararVentaDisponible($cartonId, $usuarioId);

            if ($partidaId === null) {
                $partidaId = $contexto['partidaId'];
                $organizacionId = $contexto['organizacionId'];
                $jugadorId = $contexto['jugadorId'];
            } elseif (
                $contexto['partidaId'] !== $partidaId ||
                $contexto['jugadorId'] !== $jugadorId
            ) {
                throw new DomainException(
                    'Todos los cartones del pago PSE deben ser de la misma partida y del mismo jugador.'
                );
            }

            $contextos[] = $contexto;
            $montoTotal += (float) $contexto['monto'];
        }

        $jugador = $this->usuarioRepository->buscarPorId((int) $jugadorId);

        if ($jugador === null) {
            throw new DomainException(
                'El jugador no existe.'
            );
        }

        $ubicacion = $this->ciudadRepository->buscarConDepartamento(
            (int) $jugador['ciudadId']
        );

        if ($ubicacion === null) {
            throw new DomainException(
                'La ciudad del jugador no existe.'
            );
        }

        $pagoIds = [];

        try {
            $this->database->beginTransaction();

            foreach ($contextos as $contexto) {
                $carton = $contexto['carton'];
                $carton->reservar($contexto['jugadorId']);
                $this->cartonRepository->actualizar($carton);

                $pago = new Pago(
                    $contexto['organizacionId'],
                    $contexto['partidaId'],
                    (int) $carton->getId(),
                    $contexto['jugadorId'],
                    'openpay_pse',
                    $contexto['monto'],
                    'pendiente'
                );
                $pagoIds[] = $this->pagoRepository->guardar($pago);
            }

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        // order_id único ante OpenPay (no reutilizar IDs tras TRUNCATE / reintentos).
        $orderId = 'bingo-'
            . implode('-', $pagoIds)
            . '-'
            . bin2hex(random_bytes(4));

        try {
            $inicio = $this->pasarelaPago->iniciarPse([
                'monto' => $montoTotal,
                'descripcion' => 'Pago PSE',
                'orderId' => $orderId,
                'redirectUrl' => $redirectUrl,
                'cliente' => [
                    'nombre' => $jugador['nombre'],
                    'apellido' => $jugador['apellido'],
                    'correo' => $jugador['correo'],
                    'celular' => $jugador['celular'],
                    'departamento' => $ubicacion['departamento'],
                    'ciudad' => $ubicacion['ciudad'],
                ],
            ]);
        } catch (Throwable $exception) {
            foreach ($pagoIds as $i => $pagoId) {
                $this->revertirReservaPse($pagoId, $cartonIds[$i]);
            }

            throw $exception;
        }

        $referencia = $inicio->getReferenciaExterna();

        foreach ($pagoIds as $pagoId) {
            $pagoFila = $this->pagoRepository->buscarPorId($pagoId);
            $pagoEntidad = $this->aEntidadPago($pagoFila);
            $pagoEntidad->asignarReferenciaExterna($referencia);
            $this->pagoRepository->actualizar($pagoEntidad);
        }

        $respuesta = $this->armarRespuesta(
            $pagoIds[0],
            $cartonIds[0],
            (int) $partidaId
        );
        $respuesta['cartonIds'] = $cartonIds;
        $respuesta['pagoIds'] = $pagoIds;
        $respuesta['montoTotal'] = round($montoTotal, 2);
        $respuesta['pse'] = [
            'url' => $inicio->getUrlPago(),
            'referenciaExterna' => $referencia,
            'estadoRemoto' => $inicio->getEstadoRemoto(),
            'orderId' => $orderId,
        ];

        return $respuesta;
    }

    /**
     * @return array{ok: bool, mensaje: string, verificationCode?: string}
     */
    public function procesarWebhookPasarela(array $payload): array
    {
        if (($payload['type'] ?? '') === 'verification') {
            $codigo = (string) ($payload['verification_code'] ?? '');

            return [
                'ok' => true,
                'mensaje' => 'Webhook de verificación recibido.',
                'verificationCode' => $codigo,
            ];
        }

        $evento = $this->pasarelaPago->interpretarWebhook($payload);
        $accion = $evento['accion'];

        if ($accion === 'ignorar') {
            return [
                'ok' => true,
                'mensaje' => 'Evento ignorado.',
            ];
        }

        $pagos = [];

        if (!empty($evento['referenciaExterna'])) {
            $pagos = $this->pagoRepository->listarPorReferenciaExterna(
                (string) $evento['referenciaExterna']
            );
        }

        if ($pagos === [] && !empty($evento['orderId'])) {
            $pagos = $this->buscarPagosPorOrderId((string) $evento['orderId']);
        }

        if ($pagos === []) {
            return [
                'ok' => true,
                'mensaje' => 'Pago no encontrado; evento descartado.',
            ];
        }

        $pendientes = [];

        foreach ($pagos as $pagoFila) {
            if ($pagoFila['canal'] !== 'openpay_pse') {
                continue;
            }

            if ($pagoFila['estado'] !== 'pendiente') {
                continue;
            }

            $pendientes[] = $pagoFila;
        }

        if ($pendientes === []) {
            return [
                'ok' => true,
                'mensaje' => 'Pago ya procesado.',
            ];
        }

        if ($accion === 'aprobar') {
            foreach ($pendientes as $pagoFila) {
                $this->aprobarPagoPse($pagoFila, $evento['referenciaExterna'] ?? null);
            }

            $this->notificarCartones(
                (int) $pendientes[0]['usuarioId'],
                (int) $pendientes[0]['partidaId'],
                array_map(
                    static fn (array $p): int => (int) $p['cartonId'],
                    $pendientes
                )
            );

            return [
                'ok' => true,
                'mensaje' => count($pendientes) > 1
                    ? 'Pagos PSE aprobados.'
                    : 'Pago PSE aprobado.',
            ];
        }

        foreach ($pendientes as $pagoFila) {
            $this->rechazarPagoPse($pagoFila, $evento['referenciaExterna'] ?? null);
        }

        return [
            'ok' => true,
            'mensaje' => 'Pago PSE rechazado; cartón(es) liberado(s).',
        ];
    }

    public function solicitarComprobante(
        int $cartonId,
        int $usuarioId,
        array $archivo
    ): array {
        if ($cartonId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException(
                'cartonId y usuarioId deben ser mayores que cero.'
            );
        }

        $contexto = $this->prepararVentaDisponible($cartonId, $usuarioId);
        $comprobanteUrl = $this->comprobanteStorage->guardar($archivo);

        $carton = $contexto['carton'];
        $carton->reservar($contexto['jugadorId']);

        $pago = new Pago(
            $contexto['organizacionId'],
            $contexto['partidaId'],
            $cartonId,
            $contexto['jugadorId'],
            'comprobante',
            $contexto['monto'],
            'pendiente',
            null,
            null,
            $comprobanteUrl
        );

        try {
            $this->database->beginTransaction();

            $this->cartonRepository->actualizar($carton);
            $pagoId = $this->pagoRepository->guardar($pago);

            $this->database->commit();

        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        return $this->armarRespuesta($pagoId, $cartonId, $contexto['partidaId']);
    }

    public function aprobarComprobante(
        int $pagoId,
        int $operadorId
    ): array {
        $pagoFila = $this->obtenerPagoComprobantePendiente($pagoId);
        $this->asegurarOperadorValido(
            $operadorId,
            (int) $pagoFila['organizacionId']
        );

        $filaCarton = $this->cartonRepository->buscarPorId(
            (int) $pagoFila['cartonId']
        );

        if ($filaCarton === null) {
            throw new DomainException(
                'El cartón del pago no existe.'
            );
        }

        if ($filaCarton['estado'] !== 'reservado') {
            throw new DomainException(
                'El cartón debe estar reservado para aprobar el comprobante.'
            );
        }

        $carton = $this->aEntidadCarton($filaCarton);
        $carton->vender((int) $pagoFila['usuarioId']);

        $pago = $this->aEntidadPago($pagoFila);
        $pago->asignarOperador($operadorId);
        $pago->aprobar();

        try {
            $this->database->beginTransaction();

            $this->cartonRepository->actualizar($carton);
            $this->pagoRepository->actualizar($pago);
            $this->partidaRepository->incrementarCartonesConfirmados(
                (int) $pagoFila['partidaId']
            );

            $this->database->commit();

        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $respuesta = $this->armarRespuesta(
            $pagoId,
            (int) $pagoFila['cartonId'],
            (int) $pagoFila['partidaId']
        );

        $this->notificarCartones(
            (int) $pagoFila['usuarioId'],
            (int) $pagoFila['partidaId'],
            [(int) $pagoFila['cartonId']]
        );

        return $respuesta;
    }

    public function rechazarComprobante(
        int $pagoId,
        int $operadorId
    ): array {
        $pagoFila = $this->obtenerPagoComprobantePendiente($pagoId);
        $this->asegurarOperadorValido(
            $operadorId,
            (int) $pagoFila['organizacionId']
        );

        $filaCarton = $this->cartonRepository->buscarPorId(
            (int) $pagoFila['cartonId']
        );

        if ($filaCarton === null) {
            throw new DomainException(
                'El cartón del pago no existe.'
            );
        }

        $carton = $this->aEntidadCarton($filaCarton);

        if ($carton->getEstado() === 'reservado') {
            $carton->liberar();
        }

        $pago = $this->aEntidadPago($pagoFila);
        $pago->asignarOperador($operadorId);
        $pago->rechazar();

        try {
            $this->database->beginTransaction();

            $this->cartonRepository->actualizar($carton);
            $this->pagoRepository->actualizar($pago);

            $this->database->commit();

        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        return $this->armarRespuesta(
            $pagoId,
            (int) $pagoFila['cartonId'],
            (int) $pagoFila['partidaId']
        );
    }

    private function revertirReservaPse(int $pagoId, int $cartonId): void
    {
        $pagoFila = $this->pagoRepository->buscarPorId($pagoId);
        $cartonFila = $this->cartonRepository->buscarPorId($cartonId);

        if ($pagoFila === null || $cartonFila === null) {
            return;
        }

        try {
            $this->database->beginTransaction();

            $pago = $this->aEntidadPago($pagoFila);
            $pago->rechazar();
            $this->pagoRepository->actualizar($pago);

            $carton = $this->aEntidadCarton($cartonFila);

            if ($carton->getEstado() === 'reservado') {
                $carton->liberar();
                $this->cartonRepository->actualizar($carton);
            }

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            error_log($exception->getMessage());
        }
    }

    private function aprobarPagoPse(array $pagoFila, ?string $referenciaExterna): void
    {
        $filaCarton = $this->cartonRepository->buscarPorId((int) $pagoFila['cartonId']);

        if ($filaCarton === null) {
            throw new DomainException('El cartón del pago no existe.');
        }

        $carton = $this->aEntidadCarton($filaCarton);

        if ($carton->getEstado() === 'reservado') {
            $carton->vender((int) $pagoFila['usuarioId']);
        } elseif ($carton->getEstado() !== 'vendido') {
            throw new DomainException(
                'El cartón no está en un estado válido para confirmar PSE.'
            );
        }

        $pago = $this->aEntidadPago($pagoFila);

        if ($referenciaExterna) {
            $pago->asignarReferenciaExterna($referenciaExterna);
        }

        $pago->aprobar();

        $this->database->beginTransaction();

        try {
            $this->cartonRepository->actualizar($carton);
            $this->pagoRepository->actualizar($pago);

            if ($filaCarton['estado'] !== 'vendido') {
                $this->partidaRepository->incrementarCartonesConfirmados(
                    (int) $pagoFila['partidaId']
                );
            }

            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    private function rechazarPagoPse(array $pagoFila, ?string $referenciaExterna): void
    {
        $filaCarton = $this->cartonRepository->buscarPorId((int) $pagoFila['cartonId']);

        if ($filaCarton === null) {
            throw new DomainException('El cartón del pago no existe.');
        }

        $carton = $this->aEntidadCarton($filaCarton);
        $pago = $this->aEntidadPago($pagoFila);

        if ($referenciaExterna) {
            $pago->asignarReferenciaExterna($referenciaExterna);
        }

        $pago->rechazar();

        $this->database->beginTransaction();

        try {
            if ($carton->getEstado() === 'reservado') {
                $carton->liberar();
                $this->cartonRepository->actualizar($carton);
            }

            $this->pagoRepository->actualizar($pago);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<array>
     */
    private function buscarPagosPorOrderId(string $orderId): array
    {
        if (preg_match('/^(?:orden|bingo-pago)-(\d+)$/', $orderId, $coincidencias)) {
            $pago = $this->pagoRepository->buscarPorId((int) $coincidencias[1]);

            return $pago === null ? [] : [$pago];
        }

        if (preg_match('/^orden-lote-((?:\d+-)*\d+)$/', $orderId, $coincidencias)) {
            $pagos = [];

            foreach (explode('-', $coincidencias[1]) as $idTexto) {
                $pago = $this->pagoRepository->buscarPorId((int) $idTexto);

                if ($pago !== null) {
                    $pagos[] = $pago;
                }
            }

            return $pagos;
        }

        return [];
    }

    /**
     * Retorno del banco/OpenPay (?id=cargo).
     * En local, si el webhook no llega, consulta el estado del cargo y cierra el pago.
     */
    public function retornoPse(string $referenciaExterna): array
    {
        $referenciaExterna = trim($referenciaExterna);

        if ($referenciaExterna === '') {
            throw new InvalidArgumentException(
                'Debes enviar el id del cargo PSE.'
            );
        }

        $pagos = $this->pagoRepository->listarPorReferenciaExterna(
            $referenciaExterna
        );

        if ($pagos === []) {
            throw new DomainException(
                'No se encontró un pago con esa referencia.'
            );
        }

        $pendientes = array_values(array_filter(
            $pagos,
            static fn (array $p): bool => $p['estado'] === 'pendiente'
                && $p['canal'] === 'openpay_pse'
        ));

        if (
            $pendientes !== [] &&
            $this->pasarelaPago instanceof OpenPayPasarela
        ) {
            $remoto = $this->pasarelaPago->consultarCargo($referenciaExterna);
            $status = strtolower((string) ($remoto['status'] ?? ''));

            if ($status === 'completed') {
                foreach ($pendientes as $pagoFila) {
                    $this->aprobarPagoPse($pagoFila, $referenciaExterna);
                }
                $this->notificarCartones(
                    (int) $pendientes[0]['usuarioId'],
                    (int) $pendientes[0]['partidaId'],
                    array_map(
                        static fn (array $p): int => (int) $p['cartonId'],
                        $pendientes
                    )
                );
            } elseif (
                $status === 'failed' ||
                $status === 'cancelled' ||
                $status === 'expired'
            ) {
                foreach ($pendientes as $pagoFila) {
                    $this->rechazarPagoPse($pagoFila, $referenciaExterna);
                }
            }

            $pagos = $this->pagoRepository->listarPorReferenciaExterna(
                $referenciaExterna
            );
        }

        $pagoFila = $pagos[0];
        $respuesta = $this->armarRespuesta(
            (int) $pagoFila['id'],
            (int) $pagoFila['cartonId'],
            (int) $pagoFila['partidaId']
        );
        $respuesta['cartonIds'] = array_map(
            static fn (array $p): int => (int) $p['cartonId'],
            $pagos
        );
        $respuesta['mensaje'] = match ($pagoFila['estado']) {
            'aprobado' => count($pagos) > 1
                ? 'Pagos confirmados.'
                : 'Pago confirmado.',
            'pendiente' => 'Pago en proceso. Si acabas de pagar, espera un momento.',
            'rechazado', 'expirado' => 'El pago no fue confirmado.',
            default => 'Estado del pago consultado.',
        };

        return $respuesta;
    }

    private function prepararVentaDisponible(
        int $cartonId,
        int $usuarioId
    ): array {
        $filaCarton = $this->cartonRepository->buscarPorId($cartonId);

        if ($filaCarton === null) {
            throw new DomainException(
                'El cartón no existe.'
            );
        }

        if ($filaCarton['partidaId'] === null) {
            throw new DomainException(
                'El cartón no está asociado a una partida.'
            );
        }

        if ($filaCarton['estado'] !== 'disponible') {
            throw new DomainException(
                'El cartón no está disponible para la venta.'
            );
        }

        $partidaId = (int) $filaCarton['partidaId'];
        $organizacionId = (int) $filaCarton['organizacionId'];

        $partida = $this->partidaRepository->buscarPorId($partidaId);

        if ($partida === null) {
            throw new DomainException(
                'La partida no existe.'
            );
        }

        if (
            $partida['estado'] === 'finalizada' ||
            $partida['estado'] === 'cancelada'
        ) {
            throw new DomainException(
                'No se pueden vender cartones de una partida finalizada o cancelada.'
            );
        }

        $jugador = $this->asegurarUsuarioDeOrganizacion(
            $usuarioId,
            $organizacionId,
            'jugador'
        );

        $pendiente = $this->pagoRepository->buscarPendientePorCarton($cartonId);

        if ($pendiente !== null) {
            throw new DomainException(
                'El cartón ya tiene un pago pendiente.'
            );
        }

        return [
            'carton' => $this->aEntidadCarton($filaCarton),
            'partidaId' => $partidaId,
            'organizacionId' => $organizacionId,
            'jugadorId' => (int) $jugador['id'],
            'monto' => (float) $partida['precioCarton'],
        ];
    }

    private function confirmarVentaEnTransaccion(
        Carton $carton,
        Pago $pago,
        int $partidaId,
        int $cartonId,
        bool $enviarCorreo = true
    ): array {
        try {
            $this->database->beginTransaction();

            $this->cartonRepository->actualizar($carton);
            $pagoId = $this->pagoRepository->guardar($pago);
            $this->partidaRepository->incrementarCartonesConfirmados($partidaId);

            $this->database->commit();

        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        if ($enviarCorreo) {
            $this->notificarCartones(
                $pago->getUsuarioId(),
                $partidaId,
                [$cartonId]
            );
        }

        return $this->armarRespuesta($pagoId, $cartonId, $partidaId);
    }

    /**
     * @param list<int> $cartonIds
     */
    private function notificarCartones(
        int $usuarioId,
        int $partidaId,
        array $cartonIds
    ): void {
        if ($this->compraNotificacionService === null) {
            return;
        }

        $this->compraNotificacionService->notificarCompra(
            $usuarioId,
            $partidaId,
            $cartonIds
        );
    }

    private function obtenerPagoComprobantePendiente(int $pagoId): array
    {
        if ($pagoId <= 0) {
            throw new InvalidArgumentException(
                'El ID del pago debe ser mayor que cero.'
            );
        }

        $pago = $this->pagoRepository->buscarPorId($pagoId);

        if ($pago === null) {
            throw new DomainException(
                'El pago no existe.'
            );
        }

        if ($pago['canal'] !== 'comprobante') {
            throw new DomainException(
                'El pago no es de tipo comprobante.'
            );
        }

        if ($pago['estado'] !== 'pendiente') {
            throw new DomainException(
                'El pago no está pendiente.'
            );
        }

        return $pago;
    }

    private function armarRespuesta(
        int $pagoId,
        int $cartonId,
        int $partidaId
    ): array {
        $pagoGuardado = $this->pagoRepository->buscarPorId($pagoId);
        $cartonGuardado = $this->cartonRepository->buscarPorId($cartonId);
        $partidaGuardada = $this->partidaRepository->buscarPorId($partidaId);

        return [
            'pago' => $pagoGuardado,
            'carton' => $cartonGuardado,
            'partida' => [
                'id' => (int) $partidaGuardada['id'],
                'nombre' => $partidaGuardada['nombre'],
                'cantidadCartones' => (int) $partidaGuardada['cantidadCartones'],
                'cartonesConfirmados' => (int) $partidaGuardada['cartonesConfirmados'],
                'estado' => $partidaGuardada['estado'],
            ],
        ];
    }

    private function aEntidadCarton(array $fila): Carton
    {
        return new Carton(
            (int) $fila['organizacionId'],
            $fila['numeros'],
            (int) $fila['id'],
            $fila['estado'],
            $fila['usuarioId'] !== null ? (int) $fila['usuarioId'] : null,
            $fila['partidaId'] !== null ? (int) $fila['partidaId'] : null
        );
    }

    private function aEntidadPago(array $fila): Pago
    {
        return new Pago(
            (int) $fila['organizacionId'],
            (int) $fila['partidaId'],
            (int) $fila['cartonId'],
            (int) $fila['usuarioId'],
            $fila['canal'],
            (float) $fila['monto'],
            $fila['estado'],
            $fila['operadorId'] !== null ? (int) $fila['operadorId'] : null,
            $fila['referenciaExterna'],
            $fila['comprobanteUrl'],
            (int) $fila['id'],
            $fila['creadoEn'],
            $fila['modificadoEn']
        );
    }

    private function asegurarUsuarioDeOrganizacion(
        int $usuarioId,
        int $organizacionId,
        string $contexto
    ): array {
        $usuario = $this->usuarioRepository->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new DomainException(
                "El {$contexto} no existe."
            );
        }

        if ((int) $usuario['organizacionId'] !== $organizacionId) {
            throw new DomainException(
                "El {$contexto} no pertenece a la organización del cartón."
            );
        }

        if (($usuario['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                "El {$contexto} está suspendido."
            );
        }

        return $usuario;
    }

    private function asegurarOperadorValido(
        int $operadorId,
        int $organizacionId
    ): void {
        $operador = $this->asegurarUsuarioDeOrganizacion(
            $operadorId,
            $organizacionId,
            'operador'
        );

        $rol = $operador['rol'] ?? '';

        if ($rol !== 'administrador' && $rol !== 'operador') {
            throw new DomainException(
                'Solo un administrador u operador puede gestionar esta venta.'
            );
        }
    }
}
