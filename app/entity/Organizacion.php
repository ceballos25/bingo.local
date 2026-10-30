<?php

final class Organizacion
{
    private ?int $id;
    private string $nombre;
    private string $estado;
    private ?string $creadoEn;
    private ?string $modificadoEn;

    private const ESTADOS = [
        'activo',
        'suspendido'
    ];

    public function __construct(
        string $nombre,
        ?int $id = null,
        string $estado = 'activo',
        ?string $creadoEn = null,
        ?string $modificadoEn = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException(
                'El ID debe ser mayor que cero.'
            );
        }

        $nombre = $this->validarNombre($nombre);

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException(
                'El estado debe ser activo o suspendido.'
            );
        }

        $this->id = $id;
        $this->nombre = $nombre;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function getModificadoEn(): ?string
    {
        return $this->modificadoEn;
    }

    public function renombrar(string $nombre): void
    {
        $this->nombre = $this->validarNombre($nombre);
    }

    public function activar(): void
    {
        $this->estado = 'activo';

    }

    public function suspender(): void
    {
        $this->estado = 'suspendido';

    }

    private function validarNombre(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new DomainException(
                'El nombre no puede estar vacío.'
            );
        }

        if (mb_strlen($nombre) > 150) {
            throw new DomainException(
                'El nombre no puede superar los 150 caracteres.'
            );
        }

        return $nombre;
    }
}

