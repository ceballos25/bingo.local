<?php

final class AdminOtpRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function invalidarPendientes(int $usuarioId): void
    {
        $sql = 'UPDATE admin_otp
                SET usadoEn = NOW()
                WHERE usuarioId = :usuarioId
                  AND usadoEn IS NULL';

        $statement = $this->database->prepare($sql);
        $statement->execute(['usuarioId' => $usuarioId]);
    }

    public function guardar(
        int $organizacionId,
        int $usuarioId,
        string $codigoHash,
        string $expiraEn
    ): int {
        $sql = 'INSERT INTO admin_otp (
                    organizacionId,
                    usuarioId,
                    codigoHash,
                    expiraEn
                ) VALUES (
                    :organizacionId,
                    :usuarioId,
                    :codigoHash,
                    :expiraEn
                )';

        $statement = $this->database->prepare($sql);
        $statement->execute([
            'organizacionId' => $organizacionId,
            'usuarioId' => $usuarioId,
            'codigoHash' => $codigoHash,
            'expiraEn' => $expiraEn,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function buscarVigente(int $usuarioId): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    usuarioId,
                    codigoHash,
                    expiraEn,
                    intentos,
                    usadoEn,
                    creadoEn
                FROM admin_otp
                WHERE usuarioId = :usuarioId
                  AND usadoEn IS NULL
                  AND expiraEn > NOW()
                ORDER BY id DESC
                LIMIT 1';

        $statement = $this->database->prepare($sql);
        $statement->execute(['usuarioId' => $usuarioId]);
        $fila = $statement->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }

    public function contarSolicitudesRecientes(
        int $usuarioId,
        int $minutos = 15
    ): int {
        $minutos = max(1, min(60, $minutos));
        $sql = "SELECT COUNT(*) AS total
                FROM admin_otp
                WHERE usuarioId = :usuarioId
                  AND creadoEn >= (NOW() - INTERVAL {$minutos} MINUTE)";

        $statement = $this->database->prepare($sql);
        $statement->execute(['usuarioId' => $usuarioId]);
        $fila = $statement->fetch(PDO::FETCH_ASSOC);

        return (int) ($fila['total'] ?? 0);
    }

    public function registrarIntento(int $id): void
    {
        $sql = 'UPDATE admin_otp
                SET intentos = intentos + 1
                WHERE id = :id';

        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);
    }

    public function marcarUsado(int $id): void
    {
        $sql = 'UPDATE admin_otp
                SET usadoEn = NOW()
                WHERE id = :id';

        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);
    }
}
