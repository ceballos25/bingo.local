<?php

final class AuthResponseDTO
{
    public function __construct(
        private string $token,
        private array $usuario
    ) {
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'usuario' => [
                'id' => (int) $this->usuario['id'],
                'organizacionId' => (int) $this->usuario['organizacionId'],
                'ciudadId' => (int) $this->usuario['ciudadId'],
                'nombre' => $this->usuario['nombre'],
                'apellido' => $this->usuario['apellido'],
                'celular' => $this->usuario['celular'],
                'correo' => $this->usuario['correo'],
                'rol' => $this->usuario['rol'],
                'estado' => $this->usuario['estado'],
                'creadoEn' => $this->usuario['creadoEn'],
                'modificadoEn' => $this->usuario['modificadoEn'],
            ],
        ];
    }
}
