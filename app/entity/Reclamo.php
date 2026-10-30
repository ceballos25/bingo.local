<?php

final class Reclamo
{
    private ?int $id;
    private int $partidaId;
    private int $cartonId;
    private int $usuarioId;
    private string $modalidad;
    private float $premio;
    private string $estado;
    private ?int $activo;
    private ?int $administradorId;
    private ?string $cantadoEn;
    private ?string $resueltoEn;

    private const MODALIDADES = ['linea', 'equis', 'completa'];

    private const ESTADOS = ['pendiente', 'aprobado', 'rechazado'];

    public function __construct(
        int $partidaId,
        int $cartonId,
        int $usuarioId,
        string $modalidad,
        float $premio,
        string $estado = 'pendiente',
        ?int $activo = 1,
        ?int $administradorId = null,
        ?int $id = null,
        ?string $cantadoEn = null,
        ?string $resueltoEn = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException(
                'El ID debe ser mayor que cero.'
            );
        }

        foreach (
            [
                'partida' => $partidaId,
                'cartón' => $cartonId,
                'usuario' => $usuarioId,
            ] as $etiqueta => $valor
        ) {
            if ($valor <= 0) {
                throw new DomainException(
                    "El ID de {$etiqueta} debe ser mayor que cero."
                );
            }
        }

        if ($administradorId !== null && $administradorId <= 0) {
            throw new DomainException(
                'El ID del administrador debe ser mayor que cero.'
            );
        }

        $modalidad = trim($modalidad);

        if (!in_array($modalidad, self::MODALIDADES, true)) {
            throw new DomainException(
                'La modalidad debe ser linea, equis o completa.'
            );
        }

        if ($premio <= 0) {
            throw new DomainException(
                'El premio debe ser mayor que cero.'
            );
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException(
                'El estado del reclamo no es válido.'
            );
        }

        $this->validarActivo($estado, $activo);

        $this->id = $id;
        $this->partidaId = $partidaId;
        $this->cartonId = $cartonId;
        $this->usuarioId = $usuarioId;
        $this->modalidad = $modalidad;
        $this->premio = round($premio, 2);
        $this->estado = $estado;
        $this->activo = $activo;
        $this->administradorId = $administradorId;
        $this->cantadoEn = $cantadoEn;
        $this->resueltoEn = $resueltoEn;
    }

    private function validarActivo(string $estado, ?int $activo): void
    {
        if (
            ($estado === 'pendiente' || $estado === 'aprobado')
            && $activo !== 1
        ) {
            throw new DomainException(
                'Un reclamo pendiente o aprobado debe estar activo.'
            );
        }

        if ($estado === 'rechazado' && $activo !== null) {
            throw new DomainException(
                'Un reclamo rechazado no debe estar activo.'
            );
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartidaId(): int
    {
        return $this->partidaId;
    }

    public function getCartonId(): int
    {
        return $this->cartonId;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getModalidad(): string
    {
        return $this->modalidad;
    }

    public function getPremio(): float
    {
        return $this->premio;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getActivo(): ?int
    {
        return $this->activo;
    }

    public function getAdministradorId(): ?int
    {
        return $this->administradorId;
    }

    public function getCantadoEn(): ?string
    {
        return $this->cantadoEn;
    }

    public function getResueltoEn(): ?string
    {
        return $this->resueltoEn;
    }

    public function aprobar(int $administradorId): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo un reclamo pendiente puede aprobarse.'
            );
        }

        if ($administradorId <= 0) {
            throw new DomainException(
                'El ID del administrador debe ser mayor que cero.'
            );
        }

        $this->estado = 'aprobado';
        $this->activo = 1;
        $this->administradorId = $administradorId;
        $this->resueltoEn = date('Y-m-d H:i:s');
    }

    public function rechazar(int $administradorId): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo un reclamo pendiente puede rechazarse.'
            );
        }

        if ($administradorId <= 0) {
            throw new DomainException(
                'El ID del administrador debe ser mayor que cero.'
            );
        }

        $this->estado = 'rechazado';
        $this->activo = null;
        $this->administradorId = $administradorId;
        $this->resueltoEn = date('Y-m-d H:i:s');
    }
}
