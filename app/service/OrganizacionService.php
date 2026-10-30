<?php

final class OrganizacionService
{
    public function __construct(
        private OrganizacionRepository $repository
    ) {
    }

    public function crear(string $nombre): int
    {
        $organizacion = new Organizacion($nombre);

        return $this->repository->guardar($organizacion);
    }

    public function listarTodos(): array
    {
        return $this->repository->listarTodos();
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

    /**
     * @return array<string, mixed>
     */
    public function resumen(int $organizacionId): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        $org = $this->repository->buscarPorId($organizacionId);

        if ($org === null) {
            throw new DomainException(
                'Organización no encontrada.'
            );
        }

        $resumen = $this->repository->obtenerResumen($organizacionId);
        $resumen['nombre'] = $org['nombre'];

        return $resumen;
    }

    public function actualizar(int $id, ?string $nombre, ?string $estado): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        if ($nombre === null && $estado === null) {
            throw new InvalidArgumentException(
                'Debes enviar el nombre o el estado.'
            );
        }

        $fila = $this->repository->buscarPorId($id);

        if ($fila === null) {
            return null;
        }

        $organizacion = new Organizacion(
            $fila['nombre'],
            (int) $fila['id'],
            $fila['estado'],
            $fila['creadoEn'],
            $fila['modificadoEn']
        );

        if ($nombre !== null) {
            $organizacion->renombrar($nombre);
        }

        if ($estado === 'activo') {
            $organizacion->activar();
        } elseif ($estado === 'suspendido') {
            $organizacion->suspender();
        } elseif ($estado !== null) {
            throw new DomainException(
                'El estado debe ser activo o suspendido.'
            );
        }

        $this->repository->actualizar($organizacion);

        return $this->repository->buscarPorId($id);
    }
}
