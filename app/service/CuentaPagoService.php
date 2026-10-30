<?php

final class CuentaPagoService
{
    public function __construct(
        private CuentaPagoRepository $cuentaPagoRepository,
        private OrganizacionRepository $organizacionRepository
    ) {
    }

    /**
     * @return list<array>
     */
    public function listarPublicas(int $organizacionId): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'organizacionId debe ser mayor que cero.'
            );
        }

        $org = $this->organizacionRepository->buscarPorId($organizacionId);

        if ($org === null) {
            throw new DomainException('La organización no existe.');
        }

        return $this->cuentaPagoRepository->listarPorOrganizacion(
            $organizacionId,
            true
        );
    }

    /**
     * @return list<array>
     */
    public function listarAdmin(int $organizacionId): array
    {
        $this->asegurarAdminDeOrg($organizacionId);

        return $this->cuentaPagoRepository->listarPorOrganizacion(
            $organizacionId,
            false
        );
    }

    public function crear(int $organizacionId, array $datos): array
    {
        $this->asegurarAdminDeOrg($organizacionId);

        $cuenta = new CuentaPago(
            $organizacionId,
            (string) ($datos['tipo'] ?? 'otro'),
            (string) ($datos['banco'] ?? ''),
            (string) ($datos['marca'] ?? ''),
            (string) ($datos['valor'] ?? ''),
            (string) ($datos['etiqueta'] ?? ''),
            (string) ($datos['titular'] ?? ''),
            (int) ($datos['orden'] ?? 0),
            array_key_exists('activo', $datos)
                ? (bool) $datos['activo']
                : true
        );

        $id = $this->cuentaPagoRepository->guardar($cuenta);
        $fila = $this->cuentaPagoRepository->buscarPorId($id);

        if ($fila === null) {
            throw new RuntimeException('No se encontró la cuenta recién creada.');
        }

        return $fila;
    }

    public function actualizar(int $id, int $organizacionId, array $datos): array
    {
        $this->asegurarAdminDeOrg($organizacionId);

        $fila = $this->cuentaPagoRepository->buscarPorId($id);

        if ($fila === null || (int) $fila['organizacionId'] !== $organizacionId) {
            throw new DomainException('La cuenta de pago no existe.');
        }

        $cuenta = $this->aEntidad($fila);
        $cuenta->actualizar(
            (string) ($datos['tipo'] ?? $cuenta->getTipo()),
            (string) ($datos['banco'] ?? $cuenta->getBanco()),
            (string) ($datos['marca'] ?? $cuenta->getMarca()),
            (string) ($datos['valor'] ?? $cuenta->getValor()),
            (string) ($datos['etiqueta'] ?? $cuenta->getEtiqueta()),
            (string) ($datos['titular'] ?? $cuenta->getTitular()),
            array_key_exists('orden', $datos)
                ? (int) $datos['orden']
                : $cuenta->getOrden(),
            array_key_exists('activo', $datos)
                ? (bool) $datos['activo']
                : $cuenta->estaActiva()
        );

        $this->cuentaPagoRepository->actualizar($cuenta);
        $actualizada = $this->cuentaPagoRepository->buscarPorId($id);

        if ($actualizada === null) {
            throw new RuntimeException('No se pudo releer la cuenta.');
        }

        return $actualizada;
    }

    /** Soft-delete: marca inactiva. */
    public function desactivar(int $id, int $organizacionId): array
    {
        return $this->actualizar($id, $organizacionId, ['activo' => false]);
    }

    private function asegurarAdminDeOrg(int $organizacionId): void
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'organizacionId inválido.'
            );
        }

        if (!AuthContext::esAdmin()) {
            throw new DomainException(
                'Solo el administrador puede gestionar cuentas de pago.'
            );
        }

        if (AuthContext::organizacionId() !== $organizacionId) {
            throw new DomainException(
                'No puedes gestionar cuentas de otra organización.'
            );
        }
    }

    private function aEntidad(array $fila): CuentaPago
    {
        return new CuentaPago(
            (int) $fila['organizacionId'],
            (string) $fila['tipo'],
            (string) $fila['banco'],
            (string) $fila['marca'],
            (string) $fila['valor'],
            (string) $fila['etiqueta'],
            (string) $fila['titular'],
            (int) $fila['orden'],
            (bool) $fila['activo'],
            (int) $fila['id'],
            $fila['creadoEn'] ?? null,
            $fila['modificadoEn'] ?? null
        );
    }
}
