<?php


final class OrganizacionRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Organizacion $organizacion): int
    {
        $sql = 'INSERT INTO organizaciones (nombre, estado)
                VALUES (:nombre, :estado)';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'nombre' => $organizacion->getNombre(),
            'estado' => $organizacion->getEstado(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT id, nombre, estado, creadoEn, modificadoEn
                FROM organizaciones
                ORDER BY id DESC';

        $statement = $this->database->query($sql);

        return $statement->fetchAll();
    }

    /** La organización activa si es la única; null si hay 0 o varias. */
    public function buscarUnicaActiva(): ?array
    {
        $sql = 'SELECT id, nombre, estado, creadoEn, modificadoEn
                FROM organizaciones
                WHERE estado = \'activo\'
                LIMIT 2';

        $filas = $this->database->query($sql)->fetchAll();

        return count($filas) === 1 ? $filas[0] : null;
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT id, nombre, estado, creadoEn, modificadoEn
                FROM organizaciones
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $organizacion = $statement->fetch();

        if ($organizacion === false) {
            return null;
        }

        return $organizacion;
    }

    public function actualizar(Organizacion $organizacion): void
    {
        $sql = 'UPDATE organizaciones
                SET nombre = :nombre, estado = :estado
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'nombre' => $organizacion->getNombre(),
            'estado' => $organizacion->getEstado(),
            'id' => $organizacion->getId(),
        ]);
    }

    /**
     * Totales del panel mesa para una organización.
     *
     * @return array<string, mixed>
     */
    public function obtenerResumen(int $organizacionId): array
    {
        $clientes = $this->contar(
            'SELECT COUNT(*) FROM usuarios
             WHERE organizacionId = :org
               AND rol = \'jugador\'
               AND estado = \'activo\'',
            $organizacionId
        );

        $partidasStmt = $this->database->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado = \'pendiente\' THEN 1 ELSE 0 END) AS proximas,
                SUM(CASE WHEN estado = \'en_curso\' THEN 1 ELSE 0 END) AS enCurso,
                SUM(CASE WHEN estado = \'finalizada\' THEN 1 ELSE 0 END) AS finalizadas
             FROM partidas
             WHERE organizacionId = :org'
        );
        $partidasStmt->execute(['org' => $organizacionId]);
        $partidas = $partidasStmt->fetch() ?: [];

        $cartonesStmt = $this->database->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado = \'disponible\' THEN 1 ELSE 0 END) AS disponibles,
                SUM(CASE WHEN estado = \'reservado\' THEN 1 ELSE 0 END) AS reservados,
                SUM(CASE WHEN estado = \'vendido\' THEN 1 ELSE 0 END) AS vendidos
             FROM cartones
             WHERE organizacionId = :org'
        );
        $cartonesStmt->execute(['org' => $organizacionId]);
        $cartones = $cartonesStmt->fetch() ?: [];

        $pagosStmt = $this->database->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN estado = \'aprobado\' THEN monto ELSE 0 END), 0) AS recaudado,
                COALESCE(SUM(CASE WHEN estado = \'pendiente\' THEN monto ELSE 0 END), 0) AS pendienteMonto,
                SUM(CASE WHEN estado = \'pendiente\' THEN 1 ELSE 0 END) AS pagosPendientes
             FROM pagos
             WHERE organizacionId = :org'
        );
        $pagosStmt->execute(['org' => $organizacionId]);
        $pagos = $pagosStmt->fetch() ?: [];

        return [
            'organizacionId' => $organizacionId,
            'clientes' => $clientes,
            'partidas' => [
                'total' => (int) ($partidas['total'] ?? 0),
                'proximas' => (int) ($partidas['proximas'] ?? 0),
                'enCurso' => (int) ($partidas['enCurso'] ?? 0),
                'finalizadas' => (int) ($partidas['finalizadas'] ?? 0),
            ],
            'cartones' => [
                'total' => (int) ($cartones['total'] ?? 0),
                'disponibles' => (int) ($cartones['disponibles'] ?? 0),
                'reservados' => (int) ($cartones['reservados'] ?? 0),
                'vendidos' => (int) ($cartones['vendidos'] ?? 0),
            ],
            'pagos' => [
                'recaudado' => (float) ($pagos['recaudado'] ?? 0),
                'pendienteMonto' => (float) ($pagos['pendienteMonto'] ?? 0),
                'pagosPendientes' => (int) ($pagos['pagosPendientes'] ?? 0),
            ],
        ];
    }

    private function contar(string $sql, int $organizacionId): int
    {
        $statement = $this->database->prepare($sql);
        $statement->execute(['org' => $organizacionId]);

        return (int) $statement->fetchColumn();
    }
}
