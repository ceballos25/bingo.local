<?php

final class CiudadRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT id, nombre
                FROM ciudades
                ORDER BY nombre ASC';

        $statement = $this->database->query($sql);

        return $statement->fetchAll();
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
