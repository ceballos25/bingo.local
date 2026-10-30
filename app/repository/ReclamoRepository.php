<?php

final class ReclamoRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Reclamo $reclamo): int
    {
        $sql = 'INSERT INTO reclamos (
                    partidaId,
                    cartonId,
                    usuarioId,
                    modalidad,
                    premio,
                    estado,
                    activo,
                    administradorId,
                    resueltoEn
                ) VALUES (
                    :partidaId,
                    :cartonId,
                    :usuarioId,
                    :modalidad,
                    :premio,
                    :estado,
                    :activo,
                    :administradorId,
                    :resueltoEn
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $reclamo->getPartidaId(),
            'cartonId' => $reclamo->getCartonId(),
            'usuarioId' => $reclamo->getUsuarioId(),
            'modalidad' => $reclamo->getModalidad(),
            'premio' => $reclamo->getPremio(),
            'estado' => $reclamo->getEstado(),
            'activo' => $reclamo->getActivo(),
            'administradorId' => $reclamo->getAdministradorId(),
            'resueltoEn' => $reclamo->getResueltoEn(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    partidaId,
                    cartonId,
                    usuarioId,
                    modalidad,
                    premio,
                    estado,
                    activo,
                    administradorId,
                    cantadoEn,
                    resueltoEn
                FROM reclamos
                WHERE id = :id';

        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);

        $fila = $statement->fetch();

        if ($fila === false) {
            return null;
        }

        return $this->normalizar($fila);
    }

    public function listarPorPartida(int $partidaId): array
    {
        $sql = 'SELECT
                    id,
                    partidaId,
                    cartonId,
                    usuarioId,
                    modalidad,
                    premio,
                    estado,
                    activo,
                    administradorId,
                    cantadoEn,
                    resueltoEn
                FROM reclamos
                WHERE partidaId = :partidaId
                ORDER BY id ASC';

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        $filas = $statement->fetchAll();

        foreach ($filas as &$fila) {
            $fila = $this->normalizar($fila);
        }

        unset($fila);

        return $filas;
    }

    public function actualizar(Reclamo $reclamo): void
    {
        $sql = 'UPDATE reclamos
                SET
                    estado = :estado,
                    activo = :activo,
                    administradorId = :administradorId,
                    resueltoEn = :resueltoEn
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'estado' => $reclamo->getEstado(),
            'activo' => $reclamo->getActivo(),
            'administradorId' => $reclamo->getAdministradorId(),
            'resueltoEn' => $reclamo->getResueltoEn(),
            'id' => $reclamo->getId(),
        ]);
    }

    public function buscarPendientePorPartida(
        int $partidaId,
        bool $paraActualizar = false
    ): ?array {
        $sql = 'SELECT
                    id,
                    partidaId,
                    cartonId,
                    usuarioId,
                    modalidad,
                    premio,
                    estado,
                    activo,
                    administradorId,
                    cantadoEn,
                    resueltoEn
                FROM reclamos
                WHERE partidaId = :partidaId
                  AND estado = \'pendiente\'
                ORDER BY id ASC
                LIMIT 1';

        if ($paraActualizar) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        $fila = $statement->fetch();

        if ($fila === false) {
            return null;
        }

        return $this->normalizar($fila);
    }

    public function existePendientePorPartida(int $partidaId): bool
    {
        return $this->buscarPendientePorPartida($partidaId) !== null;
    }

    /**
     * @return list<string>
     */
    public function listarModalidadesAprobadas(int $partidaId): array
    {
        $sql = 'SELECT modalidad
                FROM reclamos
                WHERE partidaId = :partidaId
                  AND estado = \'aprobado\'
                ORDER BY id ASC';

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        return array_map(
            static fn (array $fila): string => (string) $fila['modalidad'],
            $statement->fetchAll()
        );
    }

    private function normalizar(array $fila): array
    {
        $fila['id'] = (int) $fila['id'];
        $fila['partidaId'] = (int) $fila['partidaId'];
        $fila['cartonId'] = (int) $fila['cartonId'];
        $fila['usuarioId'] = (int) $fila['usuarioId'];
        $fila['premio'] = (float) $fila['premio'];
        $fila['activo'] = $fila['activo'] === null
            ? null
            : (int) $fila['activo'];
        $fila['administradorId'] = $fila['administradorId'] === null
            ? null
            : (int) $fila['administradorId'];

        return $fila;
    }
}
