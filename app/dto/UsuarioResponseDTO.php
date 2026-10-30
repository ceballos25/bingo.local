<?php

final class UsuarioResponseDTO
{
    private int $id;
    private int $organizacionId;
    private int $ciudadId;
    private string $nombre;
    private string $apellido;
    private string $celular;
    private string $correo;
    private string $rol;
    private string $estado;
    private string $creadoEn;
    private string $modificadoEn;

    public function __construct(
        int $id,
        int $organizacionId,
        int $ciudadId,
        string $nombre,
        string $apellido,
        string $celular,
        string $correo,
        string $rol,
        string $estado,
        string $creadoEn,
        string $modificadoEn
    ) {
        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->ciudadId = $ciudadId;
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->celular = $celular;
        $this->correo = $correo;
        $this->rol = $rol;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacionId' => $this->organizacionId,
            'ciudadId' => $this->ciudadId,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'rol' => $this->rol,
            'estado' => $this->estado,
            'creadoEn' => $this->creadoEn,
            'modificadoEn' => $this->modificadoEn,
        ];
    }
}
