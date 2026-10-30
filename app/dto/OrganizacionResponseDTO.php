<?php

final class OrganizacionResponseDTO
{
    private int $id;
    private string $nombre;
    private string $estado;
    private string $creadoEn;
    private string $modificadoEn;

    public function __construct(
        int $id,
        string $nombre,
        string $estado,
        string $creadoEn,
        string $modificadoEn
    ) {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'creadoEn' => $this->creadoEn,
            'modificadoEn' => $this->modificadoEn,
        ];
    }
}
