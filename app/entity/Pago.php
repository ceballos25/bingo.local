<?php

final class Pago
{
    private ?int $id;
    private int $organizacionId;
    private int $partidaId;
    private int $cartonId;
    private int $usuarioId;
    private ?int $operadorId;
    private string $canal;
    private string $estado;
    private float $monto;
    private ?string $referenciaExterna;
    private ?string $comprobanteUrl;
    private ?string $creadoEn;
    private ?string $modificadoEn;

    private const CANALES = [
        'manual_operador',
        'openpay_pse',
        'comprobante',
        'gratis',
    ];

    private const ESTADOS = [
        'pendiente',
        'aprobado',
        'rechazado',
        'expirado',
    ];

    public function __construct(
        int $organizacionId,
        int $partidaId,
        int $cartonId,
        int $usuarioId,
        string $canal,
        float $monto,
        string $estado = 'pendiente',
        ?int $operadorId = null,
        ?string $referenciaExterna = null,
        ?string $comprobanteUrl = null,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $modificadoEn = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException(
                'El ID debe ser mayor que cero.'
            );
        }

        foreach (
            [
                'organización' => $organizacionId,
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

        if ($operadorId !== null && $operadorId <= 0) {
            throw new DomainException(
                'El ID del operador debe ser mayor que cero.'
            );
        }

        if (!in_array($canal, self::CANALES, true)) {
            throw new DomainException(
                'El canal de pago no es válido.'
            );
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException(
                'El estado del pago no es válido.'
            );
        }

        // Bingo gratis: monto 0. Resto de canales exige cobro.
        if ($canal === 'gratis') {
            if ($monto < 0) {
                throw new DomainException(
                    'El monto no puede ser negativo.'
                );
            }
        } elseif ($monto <= 0) {
            throw new DomainException(
                'El monto debe ser mayor que cero.'
            );
        }

        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->partidaId = $partidaId;
        $this->cartonId = $cartonId;
        $this->usuarioId = $usuarioId;
        $this->operadorId = $operadorId;
        $this->canal = $canal;
        $this->estado = $estado;
        $this->monto = round($monto, 2);
        $this->referenciaExterna = $referenciaExterna;
        $this->comprobanteUrl = $comprobanteUrl;
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

    public function getOperadorId(): ?int
    {
        return $this->operadorId;
    }

    public function getCanal(): string
    {
        return $this->canal;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getMonto(): float
    {
        return $this->monto;
    }

    public function getReferenciaExterna(): ?string
    {
        return $this->referenciaExterna;
    }

    public function getComprobanteUrl(): ?string
    {
        return $this->comprobanteUrl;
    }

    public function getCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function getModificadoEn(): ?string
    {
        return $this->modificadoEn;
    }

    public function asignarOperador(int $operadorId): void
    {
        if ($operadorId <= 0) {
            throw new DomainException(
                'El ID del operador debe ser mayor que cero.'
            );
        }

        $this->operadorId = $operadorId;
    }

    public function asignarReferenciaExterna(string $referenciaExterna): void
    {
        $referenciaExterna = trim($referenciaExterna);

        if ($referenciaExterna === '') {
            throw new DomainException(
                'La referencia externa no puede estar vacía.'
            );
        }

        $this->referenciaExterna = $referenciaExterna;
    }

    public function aprobar(?string $referenciaExterna = null): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo un pago pendiente puede aprobarse.'
            );
        }

        $this->estado = 'aprobado';

        if ($referenciaExterna !== null && $referenciaExterna !== '') {
            $this->referenciaExterna = $referenciaExterna;
        }
    }

    public function rechazar(): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo un pago pendiente puede rechazarse.'
            );
        }

        $this->estado = 'rechazado';
    }

    public function expirar(): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo un pago pendiente puede expirar.'
            );
        }

        $this->estado = 'expirado';
    }
}
