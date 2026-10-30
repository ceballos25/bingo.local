<?php

final class UsuarioService
{
    public function __construct(
        private UsuarioRepository $repository,
        private OrganizacionRepository $organizacionRepository,
        private CiudadRepository $ciudadRepository
    ) {
    }

    public function crear(
        string $nombre,
        string $apellido,
        string $celular,
        string $correo,
        int $organizacionId,
        int $ciudadId,
        string $rol = 'jugador',
        ?string $password = null
    ): int {
        $this->asegurarOrganizacionExiste($organizacionId);
        $this->asegurarCiudadExiste($ciudadId);

        $passwordHash = $this->hashearPasswordSiViene($password);

        $usuario = new Usuario(
            $nombre,
            $apellido,
            $celular,
            $correo,
            $organizacionId,
            $ciudadId,
            $rol,
            null,
            'activo',
            null,
            null,
            $passwordHash
        );

        $this->asegurarCorreoDisponible(
            $usuario->getOrganizacionId(),
            $usuario->getCorreo()
        );

        $this->asegurarCelularDisponible(
            $usuario->getOrganizacionId(),
            $usuario->getCelular()
        );

        return $this->repository->guardar($usuario);
    }

    public function listarTodos(): array
    {
        return $this->repository->listarTodos();
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        return $this->repository->listarPorOrganizacion($organizacionId);
    }

    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        return $this->repository->buscarPorId($id);
    }

    public function actualizar(
        int $id,
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $celular = null,
        ?string $correo = null,
        ?int $ciudadId = null,
        ?string $rol = null,
        ?string $estado = null,
        ?string $password = null
    ): ?array {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        if (
            $nombre === null &&
            $apellido === null &&
            $celular === null &&
            $correo === null &&
            $ciudadId === null &&
            $rol === null &&
            $estado === null &&
            $password === null
        ) {
            throw new InvalidArgumentException(
                'Debes enviar al menos un campo para actualizar.'
            );
        }

        $fila = $this->repository->buscarPorId($id);

        if ($fila === null) {
            return null;
        }

        if ($ciudadId !== null) {
            $this->asegurarCiudadExiste($ciudadId);
        }

        $passwordHash = $password !== null
            ? $this->hashearPasswordSiViene($password)
            : $fila['password'];

        $usuario = new Usuario(
            $nombre ?? $fila['nombre'],
            $apellido ?? $fila['apellido'],
            $celular ?? $fila['celular'],
            $correo ?? $fila['correo'],
            (int) $fila['organizacionId'],
            $ciudadId ?? (int) $fila['ciudadId'],
            $rol ?? $fila['rol'],
            (int) $fila['id'],
            $estado ?? $fila['estado'],
            $fila['creadoEn'],
            $fila['modificadoEn'],
            $passwordHash
        );

        $this->asegurarCorreoDisponible(
            $usuario->getOrganizacionId(),
            $usuario->getCorreo(),
            $id
        );

        $this->asegurarCelularDisponible(
            $usuario->getOrganizacionId(),
            $usuario->getCelular(),
            $id
        );

        $this->repository->actualizar($usuario);

        return $this->repository->buscarPorId($id);
    }

    private function hashearPasswordSiViene(?string $password): ?string
    {
        if ($password === null) {
            return null;
        }

        $password = trim($password);

        if ($password === '') {
            throw new DomainException(
                'La contraseña no puede estar vacía.'
            );
        }

        if (mb_strlen($password) < 6) {
            throw new DomainException(
                'La contraseña debe tener al menos 6 caracteres.'
            );
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function asegurarOrganizacionExiste(int $organizacionId): void
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        if ($this->organizacionRepository->buscarPorId($organizacionId) === null) {
            throw new DomainException(
                'La organización no existe.'
            );
        }
    }

    private function asegurarCiudadExiste(int $ciudadId): void
    {
        if ($ciudadId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la ciudad debe ser mayor que cero.'
            );
        }

        if ($this->ciudadRepository->buscarPorId($ciudadId) === null) {
            throw new DomainException(
                'La ciudad no existe.'
            );
        }
    }

    private function asegurarCorreoDisponible(
        int $organizacionId,
        string $correo,
        ?int $usuarioId = null
    ): void {
        $existente = $this->repository->buscarPorOrganizacionYCorreo(
            $organizacionId,
            $correo
        );

        if ($existente === null) {
            return;
        }

        if ($usuarioId !== null && (int) $existente['id'] === $usuarioId) {
            return;
        }

        throw new DomainException(
            'Ya existe un usuario con ese correo en esta organización.'
        );
    }

    private function asegurarCelularDisponible(
        int $organizacionId,
        string $celular,
        ?int $usuarioId = null
    ): void {
        $existente = $this->repository->buscarPorOrganizacionYCelular(
            $organizacionId,
            $celular
        );

        if ($existente === null) {
            return;
        }

        if ($usuarioId !== null && (int) $existente['id'] === $usuarioId) {
            return;
        }

        throw new DomainException(
            'Ya existe un usuario con ese celular en esta organización.'
        );
    }
}
