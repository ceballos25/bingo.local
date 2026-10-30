<?php

final class VentaResponseDTO
{
    public function __construct(
        private array $pago,
        private array $carton,
        private array $partida
    ) {
    }

    public function toArray(): array
    {
        return [
            'pago' => [
                'id' => (int) $this->pago['id'],
                'organizacionId' => (int) $this->pago['organizacionId'],
                'partidaId' => (int) $this->pago['partidaId'],
                'cartonId' => (int) $this->pago['cartonId'],
                'usuarioId' => (int) $this->pago['usuarioId'],
                'operadorId' => $this->pago['operadorId'] !== null
                    ? (int) $this->pago['operadorId']
                    : null,
                'canal' => $this->pago['canal'],
                'estado' => $this->pago['estado'],
                'monto' => (float) $this->pago['monto'],
                'referenciaExterna' => $this->pago['referenciaExterna'],
                'comprobanteUrl' => $this->pago['comprobanteUrl'],
                'creadoEn' => $this->pago['creadoEn'],
                'modificadoEn' => $this->pago['modificadoEn'],
            ],
            'carton' => [
                'id' => (int) $this->carton['id'],
                'organizacionId' => (int) $this->carton['organizacionId'],
                'partidaId' => $this->carton['partidaId'] !== null
                    ? (int) $this->carton['partidaId']
                    : null,
                'usuarioId' => $this->carton['usuarioId'] !== null
                    ? (int) $this->carton['usuarioId']
                    : null,
                'estado' => $this->carton['estado'],
            ],
            'partida' => $this->partida,
        ];
    }
}
