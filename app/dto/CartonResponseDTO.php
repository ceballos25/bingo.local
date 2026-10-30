<?php

final class CartonResponseDTO
{
    private int $id;
    private int $organizacionId;
    private ?int $partidaId;
    private ?int $usuarioId;
    private array $numbers;
    private string $estado;
    private string $creadoEn;
    private string $modificadoEn;

    public function __construct(
        int $id,
        int $organizacionId,
        ?int $partidaId,
        ?int $usuarioId,
        array $numbers,
        string $estado,
        string $creadoEn,
        string $modificadoEn
    ) {
        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->partidaId = $partidaId;
        $this->usuarioId = $usuarioId;
        $this->numbers = $numbers;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacionId' => $this->organizacionId,
            'partidaId' => $this->partidaId,
            'usuarioId' => $this->usuarioId,
            'numeros' => $this->numbers,
            'estado' => $this->estado,
            'creadoEn' => $this->creadoEn,
            'modificadoEn' => $this->modificadoEn,
        ];
    }
}
