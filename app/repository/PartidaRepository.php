<?php

final class PartidaRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Partida $partida): int
    {
        $sql = 'INSERT INTO partidas (
                    organizacionId,
                    operadorId,
                    nombre,
                    numero,
                    fecha,
                    precioCarton,
                    gratis,
                    cantidadCartones,
                    cartonesConfirmados,
                    estado,
                    youtubeVideoId
                ) VALUES (
                    :organizacionId,
                    :operadorId,
                    :nombre,
                    :numero,
                    :fecha,
                    :precioCarton,
                    :gratis,
                    :cantidadCartones,
                    :cartonesConfirmados,
                    :estado,
                    :youtubeVideoId
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $partida->getOrganizacionId(),
            'operadorId' => $partida->getOperadorId(),
            'nombre' => $partida->getNombre(),
            'numero' => $partida->getNumero(),
            'fecha' => $partida->getFecha(),
            'precioCarton' => $partida->getPrecioCarton(),
            'gratis' => $partida->esGratis() ? 1 : 0,
            'cantidadCartones' => $partida->getCantidadCartones(),
            'cartonesConfirmados' => $partida->getCartonesConfirmados(),
            'estado' => $partida->getEstado(),
            'youtubeVideoId' => $partida->getYoutubeVideoId(),
        ]);

        $partidaId = (int) $this->database->lastInsertId();

        $this->guardarModalidades($partidaId, $partida->getModalidades());

        return $partidaId;
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    operadorId,
                    nombre,
                    numero,
                    fecha,
                    precioCarton,
                    gratis,
                    cantidadCartones,
                    cartonesConfirmados,
                    estado,
                    youtubeVideoId,
                    creadoEn,
                    modificadoEn
                FROM partidas
                ORDER BY id DESC';

        $statement = $this->database->query($sql);
        $partidas = $statement->fetchAll();

        return $this->conModalidades($partidas);
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    operadorId,
                    nombre,
                    numero,
                    fecha,
                    precioCarton,
                    gratis,
                    cantidadCartones,
                    cartonesConfirmados,
                    estado,
                    youtubeVideoId,
                    creadoEn,
                    modificadoEn
                FROM partidas
                WHERE organizacionId = :organizacionId
                ORDER BY id DESC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
        ]);

        return $this->conModalidades($statement->fetchAll());
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    operadorId,
                    nombre,
                    numero,
                    fecha,
                    precioCarton,
                    gratis,
                    cantidadCartones,
                    cartonesConfirmados,
                    estado,
                    youtubeVideoId,
                    creadoEn,
                    modificadoEn
                FROM partidas
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $partida = $statement->fetch();

        if ($partida === false) {
            return null;
        }

        $partida['modalidades'] = $this->listarModalidades((int) $partida['id']);

        return $partida;
    }

    public function buscarPorOrganizacionYNumero(
        int $organizacionId,
        int $numero
    ): ?array {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    operadorId,
                    nombre,
                    numero,
                    fecha,
                    precioCarton,
                    gratis,
                    cantidadCartones,
                    cartonesConfirmados,
                    estado,
                    youtubeVideoId,
                    creadoEn,
                    modificadoEn
                FROM partidas
                WHERE organizacionId = :organizacionId
                  AND numero = :numero';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
            'numero' => $numero,
        ]);

        $partida = $statement->fetch();

        if ($partida === false) {
            return null;
        }

        $partida['modalidades'] = $this->listarModalidades((int) $partida['id']);

        return $partida;
    }

    public function actualizarEstado(Partida $partida): void
    {
        $sql = 'UPDATE partidas
                SET estado = :estado
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'estado' => $partida->getEstado(),
            'id' => $partida->getId(),
        ]);
    }

    public function actualizarYoutubeVideoId(Partida $partida): void
    {
        $sql = 'UPDATE partidas
                SET youtubeVideoId = :youtubeVideoId
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'youtubeVideoId' => $partida->getYoutubeVideoId(),
            'id' => $partida->getId(),
        ]);
    }

    public function actualizarGratis(Partida $partida): void
    {
        $sql = 'UPDATE partidas
                SET gratis = :gratis,
                    precioCarton = :precioCarton
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'gratis' => $partida->esGratis() ? 1 : 0,
            'precioCarton' => $partida->getPrecioCarton(),
            'id' => $partida->getId(),
        ]);
    }

    public function incrementarCartonesConfirmados(int $partidaId): void
    {
        $sql = 'UPDATE partidas
                SET cartonesConfirmados = cartonesConfirmados + 1
                WHERE id = :id
                  AND cartonesConfirmados < cantidadCartones';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $partidaId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new DomainException(
                'No fue posible confirmar el cartón en la partida.'
            );
        }
    }

    /**
     * @param PartidaModalidad[] $modalidades
     */
    private function guardarModalidades(
        int $partidaId,
        array $modalidades
    ): void {
        $sql = 'INSERT INTO partida_modalidades (
                    partidaId,
                    modalidad,
                    premio
                ) VALUES (
                    :partidaId,
                    :modalidad,
                    :premio
                )';

        $statement = $this->database->prepare($sql);

        foreach ($modalidades as $modalidad) {
            $statement->execute([
                'partidaId' => $partidaId,
                'modalidad' => $modalidad->getModalidad(),
                'premio' => $modalidad->getPremio(),
            ]);
        }
    }

    private function listarModalidades(int $partidaId): array
    {
        $sql = 'SELECT modalidad, premio
                FROM partida_modalidades
                WHERE partidaId = :partidaId
                ORDER BY FIELD(modalidad, \'linea\', \'equis\', \'completa\')';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        $filas = $statement->fetchAll();

        foreach ($filas as &$fila) {
            $fila['premio'] = (float) $fila['premio'];
        }

        unset($fila);

        return $filas;
    }

    private function conModalidades(array $partidas): array
    {
        foreach ($partidas as &$partida) {
            $partida['modalidades'] = $this->listarModalidades(
                (int) $partida['id']
            );
        }

        unset($partida);

        return $partidas;
    }
}
