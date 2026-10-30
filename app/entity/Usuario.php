<?php

final class Usuario
{
    private ?int $id;
    private int $organizacionId;
    private int $ciudadId;
    private string $nombre;
    private string $apellido;
    private string $celular;
    private string $correo;
    private ?string $password;
    private string $rol;
    private string $estado;
    private ?string $creadoEn;
    private ?string $modificadoEn;

    private const ESTADOS = [
        'activo',
        'suspendido',
    ];

    private const ROLES = [
        'administrador',
        'operador',
        'jugador',
    ];

    public function __construct(
        string $nombre,
        string $apellido,
        string $celular,
        string $correo,
        int $organizacionId,
        int $ciudadId,
        string $rol = 'jugador',
        ?int $id = null,
        string $estado = 'activo',
        ?string $creadoEn = null,
        ?string $modificadoEn = null,
        ?string $password = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException(
                'El ID debe ser mayor que cero.'
            );
        }

        if ($organizacionId <= 0) {
            throw new DomainException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        if ($ciudadId <= 0) {
            throw new DomainException(
                'El ID de la ciudad debe ser mayor que cero.'
            );
        }

        $nombre = $this->validarTexto($nombre, 'nombre', 100);
        $apellido = $this->validarTexto($apellido, 'apellido', 100);
        $celular = $this->validarCelular($celular);
        $correo = $this->validarCorreo($correo);

        if (!in_array($rol, self::ROLES, true)) {
            throw new DomainException(
                'El rol debe ser administrador, operador o jugador.'
            );
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException(
                'El estado debe ser activo o suspendido.'
            );
        }

        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->ciudadId = $ciudadId;
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->celular = $celular;
        $this->correo = $correo;
        $this->password = $password;
        $this->rol = $rol;
        $this->estado = $estado;
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

    public function getCiudadId(): int
    {
        return $this->ciudadId;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getApellido(): string
    {
        return $this->apellido;
    }

    public function getCelular(): string
    {
        return $this->celular;
    }

    public function getCorreo(): string
    {
        return $this->correo;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRol(): string
    {
        return $this->rol;
    }

    public function verificarPassword(string $plano): bool
    {
        if ($this->password === null || $this->password === '') {
            return false;
        }

        return password_verify($plano, $this->password);
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
        $this->nombre = $this->validarTexto($nombre, 'nombre', 100);
    }

    public function activar(): void
    {
        $this->estado = 'activo';
    }

    public function suspender(): void
    {
        $this->estado = 'suspendido';
    }

    private function validarTexto(
        string $valor,
        string $campo,
        int $maximo
    ): string {
        $valor = trim($valor);

        if ($valor === '') {
            throw new DomainException(
                "El {$campo} no puede estar vacío."
            );
        }

        if (mb_strlen($valor) > $maximo) {
            throw new DomainException(
                "El {$campo} no puede superar los {$maximo} caracteres."
            );
        }

        return $valor;
    }

    private function validarCelular(string $celular): string
    {
        $celular = trim($celular);

        if (!preg_match('/^[0-9]{10}$/', $celular)) {
            throw new DomainException(
                'El celular debe tener exactamente 10 dígitos.'
            );
        }

        return $celular;
    }

    private function validarCorreo(string $correo): string
    {
        $correo = trim($correo);

        if ($correo === '') {
            throw new DomainException(
                'El correo no puede estar vacío.'
            );
        }

        if (mb_strlen($correo) > 150) {
            throw new DomainException(
                'El correo no puede superar los 150 caracteres.'
            );
        }

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException(
                'El correo no tiene un formato válido.'
            );
        }

        return mb_strtolower($correo);
    }
}
