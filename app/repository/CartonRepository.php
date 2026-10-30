<?php

final class CartonRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Carton $carton): int
    {
        $sql = 'INSERT INTO cartones (
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado
                ) VALUES (
                    :organizacionId,
                    :partidaId,
                    :usuarioId,
                    :numeros,
                    :estado
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $carton->getOrganizacionId(),
            'partidaId' => $carton->getPartidaId(),
            'usuarioId' => $carton->getUsuarioId(),
            'numeros' => json_encode(
                $carton->getNumbers(),
                JSON_THROW_ON_ERROR
            ),
            'estado' => $carton->getEstado(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                ORDER BY id DESC';

        $statement = $this->database->query($sql);

        return $this->decodificarLista($statement->fetchAll());
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                WHERE organizacionId = :organizacionId
                ORDER BY id DESC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
        ]);

        return $this->decodificarLista($statement->fetchAll());
    }

    public function listarPorPartida(int $partidaId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                WHERE partidaId = :partidaId
                ORDER BY id ASC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        return $this->decodificarLista($statement->fetchAll());
    }

    public function contarDisponiblesPorPartida(int $partidaId): int
    {
        $sql = 'SELECT COUNT(*)
                FROM cartones
                WHERE partidaId = :partidaId
                  AND estado = \'disponible\'';

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Cartones disponibles de una partida (vitrina), paginados.
     *
     * @return list<array>
     */
    public function listarDisponiblesPorPartida(
        int $partidaId,
        int $limit,
        int $offset
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                WHERE partidaId = :partidaId
                  AND estado = \'disponible\'
                ORDER BY id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        return $this->decodificarLista($statement->fetchAll());
    }

    public function jugadorTieneCartonVendidoEnPartida(
        int $usuarioId,
        int $partidaId
    ): bool {
        if ($usuarioId <= 0 || $partidaId <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM cartones
                WHERE usuarioId = :usuarioId
                  AND partidaId = :partidaId
                  AND estado = \'vendido\'
                LIMIT 1';

        $statement = $this->database->prepare($sql);
        $statement->execute([
            'usuarioId' => $usuarioId,
            'partidaId' => $partidaId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                WHERE usuarioId = :usuarioId
                ORDER BY id DESC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'usuarioId' => $usuarioId,
        ]);

        return $this->decodificarLista($statement->fetchAll());
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    partidaId,
                    usuarioId,
                    numeros,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM cartones
                WHERE id = :id';

        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);

        $carton = $statement->fetch();

        if ($carton === false) {
            return null;
        }

        $carton['numeros'] = json_decode(
            $carton['numeros'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $carton;
    }

    public function actualizar(Carton $carton): void
    {
        $sql = 'UPDATE cartones
                SET estado = :estado, usuarioId = :usuarioId
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'estado' => $carton->getEstado(),
            'usuarioId' => $carton->getUsuarioId(),
            'id' => $carton->getId(),
        ]);
    }

    private function decodificarLista(array $cartones): array
    {
        foreach ($cartones as &$carton) {
            $carton['numeros'] = json_decode(
                $carton['numeros'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        unset($carton);

        return $cartones;
    }
}
