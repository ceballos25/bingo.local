<?php

final class CuentaPagoResponseDTO
{
    public function __construct(
        private int $id,
        private int $organizacionId,
        private string $tipo,
        private string $banco,
        private string $marca,
        private string $valor,
        private string $etiqueta,
        private string $titular,
        private int $orden,
        private bool $activo,
        private ?string $creadoEn = null,
        private ?string $modificadoEn = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacionId' => $this->organizacionId,
            'tipo' => $this->tipo,
            'banco' => $this->banco,
            'marca' => $this->marca,
            'valor' => $this->valor,
            'etiqueta' => $this->etiqueta,
            'titular' => $this->titular,
            'orden' => $this->orden,
            'activo' => $this->activo,
            'creadoEn' => $this->creadoEn,
            'modificadoEn' => $this->modificadoEn,
        ];
    }

    /** Forma compacta para el checkout del jugador. */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'bank' => $this->banco,
            'mark' => $this->marca,
            'label' => $this->etiqueta,
            'copy' => $this->valor,
            'holder' => $this->titular,
            'orden' => $this->orden,
        ];
    }
}
