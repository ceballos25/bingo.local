<?php

final class ReclamoResponseDTO
{
    public function __construct(
        private int $id,
        private int $partidaId,
        private int $cartonId,
        private int $usuarioId,
        private string $modalidad,
        private float $premio,
        private string $estado,
        private ?int $administradorId,
        private string $cantadoEn,
        private ?string $resueltoEn
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'partidaId' => $this->partidaId,
            'cartonId' => $this->cartonId,
            'usuarioId' => $this->usuarioId,
            'modalidad' => $this->modalidad,
            'premio' => $this->premio,
            'estado' => $this->estado,
            'administradorId' => $this->administradorId,
            'cantadoEn' => $this->cantadoEn,
            'resueltoEn' => $this->resueltoEn,
        ];
    }
}
