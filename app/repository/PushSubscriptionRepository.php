<?php

final class PushSubscriptionRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(
        int $usuarioId,
        string $endpoint,
        string $p256dh,
        string $auth
    ): int {
        $existente = $this->buscarPorEndpoint($endpoint);

        if ($existente !== null) {
            $sql = 'UPDATE push_subscriptions
                    SET usuarioId = :usuarioId,
                        p256dh = :p256dh,
                        auth = :auth
                    WHERE id = :id';

            $statement = $this->database->prepare($sql);
            $statement->execute([
                'usuarioId' => $usuarioId,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'id' => $existente['id'],
            ]);

            return (int) $existente['id'];
        }

        $sql = 'INSERT INTO push_subscriptions
                    (usuarioId, endpoint, p256dh, auth)
                VALUES
                    (:usuarioId, :endpoint, :p256dh, :auth)';

        $statement = $this->database->prepare($sql);
        $statement->execute([
            'usuarioId' => $usuarioId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function eliminarPorEndpoint(
        string $endpoint,
        ?int $usuarioId = null
    ): bool {
        if ($usuarioId !== null) {
            $sql = 'DELETE FROM push_subscriptions
                    WHERE endpoint = :endpoint
                      AND usuarioId = :usuarioId';

            $statement = $this->database->prepare($sql);
            $statement->execute([
                'endpoint' => $endpoint,
                'usuarioId' => $usuarioId,
            ]);
        } else {
            $sql = 'DELETE FROM push_subscriptions
                    WHERE endpoint = :endpoint';

            $statement = $this->database->prepare($sql);
            $statement->execute(['endpoint' => $endpoint]);
        }

        return $statement->rowCount() > 0;
    }

    public function buscarPorEndpoint(string $endpoint): ?array
    {
        $sql = 'SELECT id, usuarioId, endpoint, p256dh, auth, creadoEn
                FROM push_subscriptions
                WHERE endpoint = :endpoint
                LIMIT 1';

        $statement = $this->database->prepare($sql);
        $statement->execute(['endpoint' => $endpoint]);
        $fila = $statement->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    /**
     * @param list<int> $usuarioIds
     * @return list<array>
     */
    public function listarPorUsuarioIds(array $usuarioIds): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach (array_values(array_unique($usuarioIds)) as $i => $id) {
            $key = 'u' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT id, usuarioId, endpoint, p256dh, auth, creadoEn
                FROM push_subscriptions
                WHERE usuarioId IN (' . implode(', ', $placeholders) . ')';

        $statement = $this->database->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
