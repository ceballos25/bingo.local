<?php

final class CuentaPago
{
    private ?int $id;
    private int $organizacionId;
    private string $tipo;
    private string $banco;
    private string $marca;
    private string $valor;
    private string $etiqueta;
    private string $titular;
    private int $orden;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $modificadoEn;

    private const TIPOS = ['nequi', 'bancolombia', 'breb', 'otro'];

    public function __construct(
        int $organizacionId,
        string $tipo,
        string $banco,
        string $marca,
        string $valor,
        string $etiqueta,
        string $titular = '',
        int $orden = 0,
        bool $activo = true,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $modificadoEn = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException('El ID debe ser mayor que cero.');
        }

        if ($organizacionId <= 0) {
            throw new DomainException(
                'El ID de organización debe ser mayor que cero.'
            );
        }

        $tipo = strtolower(trim($tipo));

        if (!in_array($tipo, self::TIPOS, true)) {
            throw new DomainException(
                'El tipo de cuenta no es válido.'
            );
        }

        $banco = trim($banco);
        $valor = trim($valor);
        $etiqueta = trim($etiqueta);

        if ($banco === '' || $valor === '' || $etiqueta === '') {
            throw new DomainException(
                'Banco, valor y etiqueta son obligatorios.'
            );
        }

        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->tipo = $tipo;
        $this->banco = $banco;
        $this->marca = trim($marca);
        $this->valor = $valor;
        $this->etiqueta = $etiqueta;
        $this->titular = trim($titular);
        $this->orden = $orden;
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizacionId(): int
    {
        return $this->organizacionId;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getBanco(): string
    {
        return $this->banco;
    }

    public function getMarca(): string
    {
        return $this->marca;
    }

    public function getValor(): string
    {
        return $this->valor;
    }

    public function getEtiqueta(): string
    {
        return $this->etiqueta;
    }

    public function getTitular(): string
    {
        return $this->titular;
    }

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function estaActiva(): bool
    {
        return $this->activo;
    }

    public function getCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function getModificadoEn(): ?string
    {
        return $this->modificadoEn;
    }

    public function actualizar(
        string $tipo,
        string $banco,
        string $marca,
        string $valor,
        string $etiqueta,
        string $titular,
        int $orden,
        bool $activo
    ): void {
        $copia = new self(
            $this->organizacionId,
            $tipo,
            $banco,
            $marca,
            $valor,
            $etiqueta,
            $titular,
            $orden,
            $activo,
            $this->id,
            $this->creadoEn,
            $this->modificadoEn
        );

        $this->tipo = $copia->tipo;
        $this->banco = $copia->banco;
        $this->marca = $copia->marca;
        $this->valor = $copia->valor;
        $this->etiqueta = $copia->etiqueta;
        $this->titular = $copia->titular;
        $this->orden = $copia->orden;
        $this->activo = $copia->activo;
    }
}
