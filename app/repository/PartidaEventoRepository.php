<?php

final class PartidaEventoRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function publicar(
        int $partidaId,
        string $tipo,
        array $payload
    ): int {
        $sql = 'INSERT INTO partida_eventos (
                    partidaId,
                    tipo,
                    payload
                ) VALUES (
                    :partidaId,
                    :tipo,
                    :payload
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
            'tipo' => $tipo,
            'payload' => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);

        return (int) $this->database->lastInsertId();
    }

    /**
     * @return list<array{id:int,partidaId:int,tipo:string,payload:array,creadoEn:string}>
     */
    public function listarDesde(int $partidaId, int $desdeId): array
    {
        $sql = 'SELECT
                    id,
                    partidaId,
                    tipo,
                    payload,
                    creadoEn
                FROM partida_eventos
                WHERE partidaId = :partidaId
                  AND id > :desdeId
                ORDER BY id ASC
                LIMIT 100';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'partidaId' => $partidaId,
            'desdeId' => $desdeId,
        ]);

        $filas = $statement->fetchAll();
        $salida = [];

        foreach ($filas as $fila) {
            $payload = $fila['payload'];

            if (is_string($payload)) {
                $payload = json_decode(
                    $payload,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }

            $salida[] = [
                'id' => (int) $fila['id'],
                'partidaId' => (int) $fila['partidaId'],
                'tipo' => (string) $fila['tipo'],
                'payload' => is_array($payload) ? $payload : [],
                'creadoEn' => (string) $fila['creadoEn'],
            ];
        }

        return $salida;
    }

    public function ultimoId(int $partidaId): int
    {
        $sql = 'SELECT COALESCE(MAX(id), 0) AS ultimo
                FROM partida_eventos
                WHERE partidaId = :partidaId';

        $statement = $this->database->prepare($sql);
        $statement->execute(['partidaId' => $partidaId]);

        return (int) $statement->fetchColumn();
    }
}
