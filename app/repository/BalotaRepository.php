<?php

final class BalotaRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(
        int $partidaId,
        Balota $balota,
        int $orden
    ): int {
        $sql = 'INSERT INTO partida_balotas (
                    partidaId,
                    numero,
                    orden,
                    letra
                ) VALUES (
                    :partidaId,
                    :numero,
                    :orden,
                    :letra
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
            'numero' => $balota->getNumero(),
            'orden' => $orden,
            'letra' => $balota->getLetra(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function listarPorPartida(int $partidaId): array
    {
        $sql = 'SELECT
                    id,
                    partidaId,
                    numero,
                    orden,
                    letra,
                    sacadaEn
                FROM partida_balotas
                WHERE partidaId = :partidaId
                ORDER BY orden ASC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        $filas = $statement->fetchAll();

        foreach ($filas as &$fila) {
            $fila['id'] = (int) $fila['id'];
            $fila['partidaId'] = (int) $fila['partidaId'];
            $fila['numero'] = (int) $fila['numero'];
            $fila['orden'] = (int) $fila['orden'];
            $fila['etiqueta'] = $fila['letra'] . $fila['numero'];
        }

        unset($fila);

        return $filas;
    }

    public function numerosSacados(int $partidaId): array
    {
        $sql = 'SELECT numero
                FROM partida_balotas
                WHERE partidaId = :partidaId
                ORDER BY orden ASC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        return array_map(
            static fn (array $fila): int => (int) $fila['numero'],
            $statement->fetchAll()
        );
    }

    public function contarPorPartida(int $partidaId): int
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM partida_balotas
                WHERE partidaId = :partidaId';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
        ]);

        return (int) $statement->fetchColumn();
    }
}
