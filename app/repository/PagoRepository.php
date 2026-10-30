<?php

final class PagoRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Pago $pago): int
    {
        $sql = 'INSERT INTO pagos (
                    organizacionId,
                    partidaId,
                    cartonId,
                    usuarioId,
                    operadorId,
                    canal,
                    estado,
                    monto,
                    referenciaExterna,
                    comprobanteUrl
                ) VALUES (
                    :organizacionId,
                    :partidaId,
                    :cartonId,
                    :usuarioId,
                    :operadorId,
                    :canal,
                    :estado,
                    :monto,
                    :referenciaExterna,
                    :comprobanteUrl
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $pago->getOrganizacionId(),
            'partidaId' => $pago->getPartidaId(),
            'cartonId' => $pago->getCartonId(),
            'usuarioId' => $pago->getUsuarioId(),
            'operadorId' => $pago->getOperadorId(),
            'canal' => $pago->getCanal(),
            'estado' => $pago->getEstado(),
            'monto' => $pago->getMonto(),
            'referenciaExterna' => $pago->getReferenciaExterna(),
            'comprobanteUrl' => $pago->getComprobanteUrl(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    cartonId,
                    usuarioId,
                    operadorId,
                    canal,
                    estado,
                    monto,
                    referenciaExterna,
                    comprobanteUrl,
                    creadoEn,
                    modificadoEn
                FROM pagos
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $pago = $statement->fetch();

        if ($pago === false) {
            return null;
        }

        return $this->normalizar($pago);
    }

    public function listarPorPartida(int $partidaId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    cartonId,
                    usuarioId,
                    operadorId,
                    canal,
                    estado,
                    monto,
                    referenciaExterna,
                    comprobanteUrl,
                    creadoEn,
                    modificadoEn
                FROM pagos
                WHERE partidaId = :partidaId
                ORDER BY id DESC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        $pagos = $statement->fetchAll();

        foreach ($pagos as &$pago) {
            $pago = $this->normalizar($pago);
        }

        unset($pago);

        return $pagos;
    }

    public function actualizar(Pago $pago): void
    {
        $sql = 'UPDATE pagos
                SET
                    operadorId = :operadorId,
                    estado = :estado,
                    referenciaExterna = :referenciaExterna,
                    comprobanteUrl = :comprobanteUrl
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'operadorId' => $pago->getOperadorId(),
            'estado' => $pago->getEstado(),
            'referenciaExterna' => $pago->getReferenciaExterna(),
            'comprobanteUrl' => $pago->getComprobanteUrl(),
            'id' => $pago->getId(),
        ]);
    }

    public function buscarPorReferenciaExterna(string $referenciaExterna): ?array
    {
        $lista = $this->listarPorReferenciaExterna($referenciaExterna);

        return $lista[0] ?? null;
    }

    /**
     * Varios cartones pueden compartir la misma referencia OpenPay (un cargo PSE).
     *
     * @return list<array>
     */
    public function listarPorReferenciaExterna(string $referenciaExterna): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    cartonId,
                    usuarioId,
                    operadorId,
                    canal,
                    estado,
                    monto,
                    referenciaExterna,
                    comprobanteUrl,
                    creadoEn,
                    modificadoEn
                FROM pagos
                WHERE referenciaExterna = :referenciaExterna
                ORDER BY id ASC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'referenciaExterna' => $referenciaExterna,
        ]);

        $pagos = $statement->fetchAll();

        foreach ($pagos as &$pago) {
            $pago = $this->normalizar($pago);
        }

        unset($pago);

        return $pagos;
    }

    public function buscarPendientePorCarton(int $cartonId): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    cartonId,
                    usuarioId,
                    operadorId,
                    canal,
                    estado,
                    monto,
                    referenciaExterna,
                    comprobanteUrl,
                    creadoEn,
                    modificadoEn
                FROM pagos
                WHERE cartonId = :cartonId
                  AND estado = \'pendiente\'
                ORDER BY id DESC
                LIMIT 1';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'cartonId' => $cartonId,
        ]);

        $pago = $statement->fetch();

        if ($pago === false) {
            return null;
        }

        return $this->normalizar($pago);
    }

    /**
     * Mesa: pagos con datos del cliente, partida y cartón.
     *
     * @return list<array>
     */
    public function listarParaMesa(
        int $organizacionId,
        ?string $estado = null,
        ?int $partidaId = null,
        int $limite = 80
    ): array {
        if ($organizacionId <= 0) {
            return [];
        }

        $limite = max(1, min(200, $limite));
        $where = ['p.organizacionId = :organizacionId'];
        $params = ['organizacionId' => $organizacionId];

        if ($estado !== null && $estado !== '') {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($partidaId !== null && $partidaId > 0) {
            $where[] = 'p.partidaId = :partidaId';
            $params['partidaId'] = $partidaId;
        }

        $sqlWhere = implode(' AND ', $where);

        $sql = "SELECT
                    p.id,
                    p.organizacionId,
                    p.partidaId,
                    p.cartonId,
                    p.usuarioId,
                    p.operadorId,
                    p.canal,
                    p.estado,
                    p.monto,
                    p.referenciaExterna,
                    p.comprobanteUrl,
                    p.creadoEn,
                    p.modificadoEn,
                    u.nombre AS clienteNombre,
                    u.apellido AS clienteApellido,
                    u.celular AS clienteCelular,
                    u.correo AS clienteCorreo,
                    part.nombre AS partidaNombre,
                    part.estado AS partidaEstado,
                    c.estado AS cartonEstado,
                    c.numeros AS cartonNumeros
                FROM pagos p
                INNER JOIN usuarios u ON u.id = p.usuarioId
                INNER JOIN partidas part ON part.id = p.partidaId
                INNER JOIN cartones c ON c.id = p.cartonId
                WHERE {$sqlWhere}
                ORDER BY
                    CASE p.estado
                        WHEN 'pendiente' THEN 0
                        WHEN 'aprobado' THEN 1
                        ELSE 2
                    END,
                    p.id DESC
                LIMIT {$limite}";

        $statement = $this->database->prepare($sql);
        $statement->execute($params);
        $filas = $statement->fetchAll();
        $salida = [];

        foreach ($filas as $fila) {
            $pago = $this->normalizar($fila);
            $pago['clienteNombre'] = (string) ($fila['clienteNombre'] ?? '');
            $pago['clienteApellido'] = (string) ($fila['clienteApellido'] ?? '');
            $pago['clienteCelular'] = (string) ($fila['clienteCelular'] ?? '');
            $pago['clienteCorreo'] = (string) ($fila['clienteCorreo'] ?? '');
            $pago['partidaNombre'] = (string) ($fila['partidaNombre'] ?? '');
            $pago['partidaEstado'] = (string) ($fila['partidaEstado'] ?? '');
            $pago['cartonEstado'] = (string) ($fila['cartonEstado'] ?? '');
            $numeros = $fila['cartonNumeros'] ?? null;
            $pago['cartonNumeros'] = is_string($numeros)
                ? json_decode($numeros, true)
                : (is_array($numeros) ? $numeros : []);
            if (!is_array($pago['cartonNumeros'])) {
                $pago['cartonNumeros'] = [];
            }
            $salida[] = $pago;
        }

        return $salida;
    }

    private function normalizar(array $pago): array
    {
        $pago['id'] = (int) $pago['id'];
        $pago['organizacionId'] = (int) $pago['organizacionId'];
        $pago['partidaId'] = (int) $pago['partidaId'];
        $pago['cartonId'] = (int) $pago['cartonId'];
        $pago['usuarioId'] = (int) $pago['usuarioId'];
        $pago['operadorId'] = $pago['operadorId'] !== null
            ? (int) $pago['operadorId']
            : null;
        $pago['monto'] = (float) $pago['monto'];

        return $pago;
    }
}
