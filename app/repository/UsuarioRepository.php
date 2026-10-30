<?php

final class UsuarioRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(Usuario $usuario): int
    {
        $sql = 'INSERT INTO usuarios (
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado
                ) VALUES (
                    :organizacionId,
                    :ciudadId,
                    :nombre,
                    :apellido,
                    :celular,
                    :correo,
                    :password,
                    :rol,
                    :estado
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $usuario->getOrganizacionId(),
            'ciudadId' => $usuario->getCiudadId(),
            'nombre' => $usuario->getNombre(),
            'apellido' => $usuario->getApellido(),
            'celular' => $usuario->getCelular(),
            'correo' => $usuario->getCorreo(),
            'password' => $usuario->getPassword(),
            'rol' => $usuario->getRol(),
            'estado' => $usuario->getEstado(),
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function listarTodos(): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM usuarios
                ORDER BY id DESC';

        $statement = $this->database->query($sql);

        return $statement->fetchAll();
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM usuarios
                WHERE organizacionId = :organizacionId
                ORDER BY id DESC';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
        ]);

        return $statement->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM usuarios
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'id' => $id,
        ]);

        $usuario = $statement->fetch();

        if ($usuario === false) {
            return null;
        }

        return $usuario;
    }

    public function buscarPorOrganizacionYCorreo(
        int $organizacionId,
        string $correo
    ): ?array {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM usuarios
                WHERE organizacionId = :organizacionId
                  AND correo = :correo';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
            'correo' => $correo,
        ]);

        $usuario = $statement->fetch();

        if ($usuario === false) {
            return null;
        }

        return $usuario;
    }

    public function buscarPorOrganizacionYCelular(
        int $organizacionId,
        string $celular
    ): ?array {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    ciudadId,
                    nombre,
                    apellido,
                    celular,
                    correo,
                    password,
                    rol,
                    estado,
                    creadoEn,
                    modificadoEn
                FROM usuarios
                WHERE organizacionId = :organizacionId
                  AND celular = :celular';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $organizacionId,
            'celular' => $celular,
        ]);

        $usuario = $statement->fetch();

        if ($usuario === false) {
            return null;
        }

        return $usuario;
    }

    public function actualizar(Usuario $usuario): void
    {
        $sql = 'UPDATE usuarios
                SET
                    organizacionId = :organizacionId,
                    ciudadId = :ciudadId,
                    nombre = :nombre,
                    apellido = :apellido,
                    celular = :celular,
                    correo = :correo,
                    password = :password,
                    rol = :rol,
                    estado = :estado
                WHERE id = :id';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $usuario->getOrganizacionId(),
            'ciudadId' => $usuario->getCiudadId(),
            'nombre' => $usuario->getNombre(),
            'apellido' => $usuario->getApellido(),
            'celular' => $usuario->getCelular(),
            'correo' => $usuario->getCorreo(),
            'password' => $usuario->getPassword(),
            'rol' => $usuario->getRol(),
            'estado' => $usuario->getEstado(),
            'id' => $usuario->getId(),
        ]);
    }
}
