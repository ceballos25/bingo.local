<?php

final class UsuarioController
{
    public function __construct(
        private UsuarioService $service
    ) {
    }

    public function crear(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $camposTexto = ['nombre', 'apellido', 'celular', 'correo'];

        foreach ($camposTexto as $campo) {
            if (!isset($entrada[$campo]) || !is_string($entrada[$campo])) {
                $this->responder(
                    ['error' => "Debes enviar {$campo} como texto."],
                    400
                );

                return;
            }
        }

        if (!isset($entrada['ciudadId']) || !is_int($entrada['ciudadId'])) {
            $this->responder(
                ['error' => 'Debes enviar ciudadId como entero.'],
                400
            );

            return;
        }

        $rol = 'jugador';

        if (array_key_exists('rol', $entrada)) {
            if (!is_string($entrada['rol'])) {
                $this->responder(
                    ['error' => 'El rol debe ser un texto.'],
                    400
                );

                return;
            }

            $rol = $entrada['rol'];
        }

        // Solo el administrador asigna operador/admin; el operador solo crea jugadores.
        if (!AuthContext::esAdmin()) {
            if ($rol !== 'jugador') {
                $this->responder(
                    ['error' => 'Solo el administrador puede crear operadores o administradores.'],
                    403
                );

                return;
            }
        }

        $password = null;

        if (array_key_exists('password', $entrada)) {
            if (!is_string($entrada['password'])) {
                $this->responder(
                    ['error' => 'El password debe ser un texto.'],
                    400
                );

                return;
            }

            $password = $entrada['password'];
        }

        try {
            $id = $this->service->crear(
                $entrada['nombre'],
                $entrada['apellido'],
                $entrada['celular'],
                $entrada['correo'],
                AuthContext::organizacionId(),
                $entrada['ciudadId'],
                $rol,
                $password
            );

            $usuario = $this->service->buscarPorId($id);

            if ($usuario === null) {
                throw new RuntimeException(
                    'No se encontró el usuario recién creado.'
                );
            }

            $this->responder($this->aDto($usuario)->toArray(), 201);

        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear el usuario.'],
                500
            );
        }
    }

    public function listar(): void
    {
        try {
            $usuarios = $this->service->listarPorOrganizacion(
                AuthContext::organizacionId()
            );

            $respuesta = [];

            foreach ($usuarios as $usuario) {
                $respuesta[] = $this->aDto($usuario)->toArray();
            }

            $this->responder($respuesta, 200);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar los usuarios.'],
                500
            );
        }
    }

    public function buscarPorId(int $id): void
    {
        if ($id <= 0) {
            $this->responder(
                ['error' => 'El ID debe ser mayor que cero.'],
                400
            );

            return;
        }

        try {
            $usuario = $this->service->buscarPorId($id);

            if ($usuario === null) {
                $this->responder(
                    ['error' => 'Usuario no encontrado.'],
                    404
                );

                return;
            }

            if (
                (int) $usuario['organizacionId'] !== AuthContext::organizacionId()
                && AuthContext::id() !== $id
            ) {
                $this->responder(
                    ['error' => 'Usuario no encontrado.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($usuario)->toArray(), 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar el usuario.'],
                500
            );
        }
    }

    public function actualizar(int $id): void
    {
        if ($id <= 0) {
            $this->responder(
                ['error' => 'El ID debe ser mayor que cero.'],
                400
            );

            return;
        }

        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $nombre = null;
        $apellido = null;
        $celular = null;
        $correo = null;
        $ciudadId = null;
        $rol = null;
        $estado = null;
        $password = null;

        $camposTexto = [
            'nombre' => &$nombre,
            'apellido' => &$apellido,
            'celular' => &$celular,
            'correo' => &$correo,
            'rol' => &$rol,
            'estado' => &$estado,
            'password' => &$password,
        ];

        foreach ($camposTexto as $campo => &$valor) {
            if (!array_key_exists($campo, $entrada)) {
                continue;
            }

            if (!is_string($entrada[$campo])) {
                $this->responder(
                    ['error' => "El campo {$campo} debe ser un texto."],
                    400
                );

                return;
            }

            $valor = $entrada[$campo];
        }
        unset($valor);

        if (array_key_exists('ciudadId', $entrada)) {
            if (!is_int($entrada['ciudadId'])) {
                $this->responder(
                    ['error' => 'El ciudadId debe ser un entero.'],
                    400
                );

                return;
            }

            $ciudadId = $entrada['ciudadId'];
        }

        if (
            $nombre === null &&
            $apellido === null &&
            $celular === null &&
            $correo === null &&
            $ciudadId === null &&
            $rol === null &&
            $estado === null &&
            $password === null
        ) {
            $this->responder(
                ['error' => 'Debes enviar al menos un campo para actualizar.'],
                400
            );

            return;
        }

        try {
            $existente = $this->service->buscarPorId($id);

            if (
                $existente === null
                || (int) $existente['organizacionId'] !== AuthContext::organizacionId()
            ) {
                $this->responder(
                    ['error' => 'Usuario no encontrado.'],
                    404
                );

                return;
            }

            $actorId = AuthContext::id();
            $esMesa = AuthContext::esOperadorOAdmin();
            $esAdmin = AuthContext::esAdmin();
            $rolExistente = (string) $existente['rol'];

            // Jugador solo edita su ficha; no puede tocar rol ni estado.
            // Tampoco correo/celular: son la llave de acceso (anti-secuestro).
            if (!$esMesa) {
                if ($id !== $actorId) {
                    $this->responder(
                        ['error' => 'Solo puedes actualizar tu propio perfil.'],
                        403
                    );

                    return;
                }

                $rol = null;
                $estado = null;
                $celular = null;
                $correo = null;
            } elseif (!$esAdmin) {
                // Operador: solo gestiona jugadores; no cambia roles.
                if ($rolExistente !== 'jugador') {
                    $this->responder(
                        ['error' => 'Solo el administrador puede editar operadores o administradores.'],
                        403
                    );

                    return;
                }

                $rol = null;

                if ($estado !== null && $estado !== 'activo' && $estado !== 'inactivo') {
                    $this->responder(
                        ['error' => 'El estado debe ser activo o inactivo.'],
                        400
                    );

                    return;
                }
            } else {
                // Admin no se desactiva ni se quita el rol a sí mismo.
                if ($id === $actorId) {
                    if ($estado === 'inactivo') {
                        $this->responder(
                            ['error' => 'No puedes desactivarte a ti mismo.'],
                            403
                        );

                        return;
                    }

                    if ($rol !== null && $rol !== 'administrador') {
                        $this->responder(
                            ['error' => 'No puedes quitarte el rol de administrador.'],
                            403
                        );

                        return;
                    }
                }
            }

            $usuario = $this->service->actualizar(
                $id,
                $nombre,
                $apellido,
                $celular,
                $correo,
                $ciudadId,
                $rol,
                $estado,
                $password
            );

            if ($usuario === null) {
                $this->responder(
                    ['error' => 'Usuario no encontrado.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($usuario)->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible actualizar el usuario.'],
                500
            );
        }
    }

    private function aDto(array $usuario): UsuarioResponseDTO
    {
        return new UsuarioResponseDTO(
            (int) $usuario['id'],
            (int) $usuario['organizacionId'],
            (int) $usuario['ciudadId'],
            $usuario['nombre'],
            $usuario['apellido'],
            $usuario['celular'],
            $usuario['correo'],
            $usuario['rol'],
            $usuario['estado'],
            $usuario['creadoEn'],
            $usuario['modificadoEn']
        );
    }

    private function responder(
        array $datos,
        int $codigoHttp
    ): void {
        http_response_code($codigoHttp);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
