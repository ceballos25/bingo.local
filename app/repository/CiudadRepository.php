<?php

final class CiudadRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT
                    c.id,
                    c.nombre,
                    d.nombre AS departamento
                FROM ciudades c
                LEFT JOIN departamentos d ON d.id = c.departamentoId
                ORDER BY c.nombre ASC, d.nombre ASC';

        $statement = $this->database->query($sql);

        $filas = $statement->fetchAll();
        $respuesta = [];

        foreach ($filas as $fila) {
            $nombre = (string) ($fila['nombre'] ?? '');
            $depto = trim((string) ($fila['departamento'] ?? ''));
            $respuesta[] = [
                'id' => (int) $fila['id'],
                'nombre' => $nombre,
                'departamento' => $depto !== '' ? $depto : null,
                'etiqueta' => $depto !== '' ? "{$nombre} ({$depto})" : $nombre,
            ];
        }

        return $respuesta;
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT id, departamentoId, codigo, nombre
                FROM ciudades
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $ciudad = $statement->fetch();

        if ($ciudad === false) {
            return null;
        }

        return $ciudad;
    }

    public function buscarConDepartamento(int $id): ?array
    {
        $sql = 'SELECT
                    c.id,
                    c.nombre AS ciudad,
                    d.nombre AS departamento
                FROM ciudades c
                INNER JOIN departamentos d ON d.id = c.departamentoId
                WHERE c.id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $fila = $statement->fetch();

        if ($fila === false) {
            return null;
        }

        return $fila;
    }
}
