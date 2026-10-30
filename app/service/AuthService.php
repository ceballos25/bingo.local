<?php

final class AuthService
{
    /** Roles de mesa: entran con OTP al correo, nunca directo. */
    private const ROLES_OTP = ['administrador', 'operador'];

    public function __construct(
        private UsuarioRepository $usuarioRepository,
        private JwtService $jwtService,
        private ?UsuarioService $usuarioService = null,
        private ?AdminOtpRepository $adminOtpRepository = null,
        private ?MailerService $mailerService = null,
        private ?OrganizacionRepository $organizacionRepository = null
    ) {
    }

    /**
     * Sin enlace de partida el front no conoce la org: si hay una sola
     * organización activa se usa esa; con varias, el jugador debe entrar
     * por el enlace del bingo.
     */
    public function resolverOrganizacion(int $organizacionId): int
    {
        if ($organizacionId > 0 || $this->organizacionRepository === null) {
            return $organizacionId;
        }

        $unica = $this->organizacionRepository->buscarUnicaActiva();

        if ($unica === null) {
            throw new DomainException(
                'Entra desde el enlace del bingo para continuar.'
            );
        }

        return (int) $unica['id'];
    }

    /**
     * Alta pública de jugador: siempre rol jugador + password obligatorio.
     * Devuelve el mismo shape que login (token + usuario).
     */
    public function registro(
        int $organizacionId,
        int $ciudadId,
        string $nombre,
        string $apellido,
        string $celular,
        string $correo,
        string $password
    ): array {
        if ($this->usuarioService === null) {
            throw new RuntimeException(
                'UsuarioService no está disponible para registro.'
            );
        }

        $password = trim($password);

        if ($password === '') {
            throw new InvalidArgumentException(
                'Debes enviar la contraseña.'
            );
        }

        $this->usuarioService->crear(
            $nombre,
            $apellido,
            $celular,
            $correo,
            $organizacionId,
            $ciudadId,
            'jugador',
            $password
        );

        return $this->login($organizacionId, $correo, $password);
    }

    /**
     * Compra / juego sin contraseña: crea jugador (o reabre sesión si
     * celular+correo coinciden) y devuelve JWT + enlace único #/acceso?token=…
     * TTL del enlace: 30 días.
     */
    public function invitado(
        int $organizacionId,
        string $nombre,
        string $apellido,
        string $celular,
        string $correo,
        int $ciudadId = 1,
        bool $paraPagar = false
    ): array {
        if ($this->usuarioService === null) {
            throw new RuntimeException(
                'UsuarioService no está disponible para invitado.'
            );
        }

        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        $nombre = trim($nombre);
        $apellido = trim($apellido);
        $celular = trim($celular);
        $correo = mb_strtolower(trim($correo));

        if ($nombre === '') {
            throw new InvalidArgumentException(
                'Debes enviar el nombre.'
            );
        }

        if ($apellido === '') {
            throw new InvalidArgumentException(
                'Debes enviar el apellido.'
            );
        }

        if (!preg_match('/^[0-9]{10}$/', $celular)) {
            throw new InvalidArgumentException(
                'El celular debe tener exactamente 10 dígitos.'
            );
        }

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'Debes enviar un correo válido.'
            );
        }

        if ($ciudadId <= 0) {
            $ciudadId = 1;
        }

        // Seguridad: solo reabre sesión si CORREO y CELULAR son de la misma cuenta.
        // Un solo dato no basta (evita robo por adivinar celular o correo).
        $porCorreo = $this->usuarioRepository->buscarPorOrganizacionYCorreo(
            $organizacionId,
            $correo
        );
        $porCelular = $this->usuarioRepository->buscarPorOrganizacionYCelular(
            $organizacionId,
            $celular
        );

        $fila = null;

        if ($porCorreo !== null && $porCelular !== null) {
            if ((int) $porCorreo['id'] !== (int) $porCelular['id']) {
                throw new DomainException(
                    'El correo y el celular no pertenecen a la misma cuenta. Revísalos.'
                );
            }

            $fila = $porCorreo;
        } elseif ($porCorreo !== null || $porCelular !== null) {
            throw new DomainException(
                'Correo y celular no coinciden con una cuenta. Usa el mismo par con el que jugaste.'
            );
        }

        if ($fila === null) {
            $passwordTemporal = bin2hex(random_bytes(16));

            $this->usuarioService->crear(
                $nombre,
                $apellido,
                $celular,
                $correo,
                $organizacionId,
                $ciudadId,
                'jugador',
                $passwordTemporal
            );

            $fila = $this->usuarioRepository->buscarPorOrganizacionYCorreo(
                $organizacionId,
                $correo
            );

            if ($fila === null) {
                throw new RuntimeException(
                    'No fue posible crear el acceso de invitado.'
                );
            }
        }

        if (!$paraPagar && in_array($fila['rol'] ?? '', self::ROLES_OTP, true)) {
            return $this->dispararOtpAdmin($fila);
        }

        return $this->tokenInvitado($fila);
    }

    /**
     * Vuelve a jugar: correo + celular de la misma cuenta → JWT nuevo (30 días).
     * Nunca solo celular (evita secuestro por adivinanza).
     */
    public function accesoInvitado(
        int $organizacionId,
        string $celular,
        string $correo,
        bool $paraPagar = false
    ): array {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        $celular = trim($celular);
        $correo = mb_strtolower(trim($correo));

        if (!preg_match('/^[0-9]{10}$/', $celular)) {
            throw new InvalidArgumentException(
                'El celular debe tener exactamente 10 dígitos.'
            );
        }

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'Debes enviar un correo válido.'
            );
        }

        $porCorreo = $this->usuarioRepository->buscarPorOrganizacionYCorreo(
            $organizacionId,
            $correo
        );
        $porCelular = $this->usuarioRepository->buscarPorOrganizacionYCelular(
            $organizacionId,
            $celular
        );

        if (
            $porCorreo === null
            || $porCelular === null
            || (int) $porCorreo['id'] !== (int) $porCelular['id']
        ) {
            throw new DomainException(
                'Correo y celular no coinciden con una cuenta. Revísalos.'
            );
        }

        if (!$paraPagar && in_array($porCorreo['rol'] ?? '', self::ROLES_OTP, true)) {
            return $this->dispararOtpAdmin($porCorreo);
        }

        return $this->tokenInvitado($porCorreo);
    }

    /**
     * @param array<string, mixed> $fila
     * @return array{token: string, usuario: array, enlaceAcceso: string}
     */
    private function tokenInvitado(array $fila): array
    {
        if (($fila['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El usuario está suspendido.'
            );
        }

        $ttlEnlace = 30 * 24 * 60 * 60;

        // Sin OTP un administrador u operador solo obtiene permisos de jugador.
        $soloJugador = in_array($fila['rol'] ?? '', self::ROLES_OTP, true);

        if ($soloJugador) {
            $fila['rol'] = 'jugador';
        }

        $token = $this->jwtService->emitir(
            [
                'sub' => (int) $fila['id'],
                'organizacionId' => (int) $fila['organizacionId'],
                'rol' => $fila['rol'],
                'invitado' => true,
                'soloJugador' => $soloJugador,
            ],
            $ttlEnlace
        );

        return [
            'token' => $token,
            'usuario' => $this->sinPassword($fila),
            'enlaceAcceso' => '#/acceso?token=' . rawurlencode($token),
        ];
    }

    public function login(
        int $organizacionId,
        string $identificador,
        string $password
    ): array {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        $identificador = trim($identificador);
        $password = trim($password);

        if ($identificador === '') {
            throw new InvalidArgumentException(
                'Debes enviar el identificador (correo o celular).'
            );
        }

        if ($password === '') {
            throw new InvalidArgumentException(
                'Debes enviar la contraseña.'
            );
        }

        $fila = $this->buscarPorIdentificador(
            $organizacionId,
            $identificador
        );

        if ($fila === null) {
            throw new DomainException(
                'Credenciales inválidas.'
            );
        }

        if (($fila['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El usuario está suspendido.'
            );
        }

        if (in_array($fila['rol'] ?? '', self::ROLES_OTP, true)) {
            throw new DomainException(
                'Administradores y operadores entran con código OTP al correo, no con contraseña.'
            );
        }

        $usuario = $this->aEntidad($fila);

        if (!$usuario->verificarPassword($password)) {
            throw new DomainException(
                'Credenciales inválidas.'
            );
        }

        $token = $this->jwtService->emitir([
            'sub' => $usuario->getId(),
            'organizacionId' => $usuario->getOrganizacionId(),
            'rol' => $usuario->getRol(),
        ]);

        return [
            'token' => $token,
            'usuario' => $this->sinPassword($fila),
        ];
    }

    /**
     * Envía OTP de 6 dígitos solo si el correo es administrador activo.
     */
    public function solicitarOtpAdmin(
        int $organizacionId,
        string $correo
    ): array {
        if ($this->adminOtpRepository === null || $this->mailerService === null) {
            throw new RuntimeException(
                'OTP de administrador no está disponible.'
            );
        }

        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        $correo = mb_strtolower(trim($correo));

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'Debes enviar un correo válido.'
            );
        }

        $fila = $this->usuarioRepository->buscarPorOrganizacionYCorreo(
            $organizacionId,
            $correo
        );

        if (
            $fila === null
            || !in_array($fila['rol'] ?? '', self::ROLES_OTP, true)
            || ($fila['estado'] ?? '') !== 'activo'
        ) {
            throw new DomainException(
                'No hay un administrador u operador activo con ese correo.'
            );
        }

        return $this->dispararOtpAdmin($fila);
    }

    /**
     * @param array<string, mixed> $fila
     * @return array{requiereOtp: bool, correo: string, mensaje: string, expiraEnMinutos: int}
     */
    private function dispararOtpAdmin(array $fila): array
    {
        if ($this->adminOtpRepository === null || $this->mailerService === null) {
            throw new RuntimeException(
                'OTP de administrador no está disponible.'
            );
        }

        if (($fila['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El usuario está suspendido.'
            );
        }

        $usuarioId = (int) $fila['id'];
        $organizacionId = (int) $fila['organizacionId'];
        $correo = mb_strtolower(trim((string) ($fila['correo'] ?? '')));

        $recientes = $this->adminOtpRepository->contarSolicitudesRecientes(
            $usuarioId,
            15
        );

        if ($recientes >= 5) {
            throw new DomainException(
                'Demasiados códigos pedidos. Espera unos minutos.'
            );
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($codigo, PASSWORD_DEFAULT);
        $expiraEn = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');

        $this->adminOtpRepository->invalidarPendientes($usuarioId);
        $this->adminOtpRepository->guardar(
            $organizacionId,
            $usuarioId,
            $hash,
            $expiraEn
        );

        $nombre = trim((string) ($fila['nombre'] ?? 'Admin'));
        $html = $this->plantillaCorreoOtp($nombre, $codigo);

        $marca = EmailPlantilla::marca();
        $this->mailerService->enviar(
            $correo,
            "Tu código de acceso — {$marca}",
            $html,
            "{$marca} — Código de acceso\n\n"
            . "Hola {$nombre},\n\n"
            . "Tu código es: {$codigo}\n"
            . "Vence en 10 minutos.\n\n"
            . "Si no solicitaste este código, ignora este correo.\n"
        );

        return [
            'requiereOtp' => true,
            'ok' => true,
            'correo' => $correo,
            'mensaje' => 'Te enviamos un código a tu correo.',
            'expiraEnMinutos' => 10,
        ];
    }

    /**
     * Valida OTP de administrador y emite JWT.
     */
    public function verificarOtpAdmin(
        int $organizacionId,
        string $correo,
        string $codigo
    ): array {
        if ($this->adminOtpRepository === null) {
            throw new RuntimeException(
                'OTP de administrador no está disponible.'
            );
        }

        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        $correo = mb_strtolower(trim($correo));
        $codigo = trim($codigo);

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'Debes enviar un correo válido.'
            );
        }

        if (!preg_match('/^[0-9]{6}$/', $codigo)) {
            throw new InvalidArgumentException(
                'El código debe tener 6 dígitos.'
            );
        }

        $fila = $this->usuarioRepository->buscarPorOrganizacionYCorreo(
            $organizacionId,
            $correo
        );

        if (
            $fila === null
            || !in_array($fila['rol'] ?? '', self::ROLES_OTP, true)
            || ($fila['estado'] ?? '') !== 'activo'
        ) {
            throw new DomainException(
                'Código inválido o expirado.'
            );
        }

        $otp = $this->adminOtpRepository->buscarVigente((int) $fila['id']);

        if ($otp === null) {
            throw new DomainException(
                'Código inválido o expirado.'
            );
        }

        if ((int) ($otp['intentos'] ?? 0) >= 5) {
            $this->adminOtpRepository->marcarUsado((int) $otp['id']);

            throw new DomainException(
                'Demasiados intentos. Pide un código nuevo.'
            );
        }

        if (!password_verify($codigo, (string) $otp['codigoHash'])) {
            $this->adminOtpRepository->registrarIntento((int) $otp['id']);

            throw new DomainException(
                'Código inválido o expirado.'
            );
        }

        $this->adminOtpRepository->marcarUsado((int) $otp['id']);

        $token = $this->jwtService->emitir([
            'sub' => (int) $fila['id'],
            'organizacionId' => (int) $fila['organizacionId'],
            'rol' => $fila['rol'],
        ]);

        return [
            'token' => $token,
            'usuario' => $this->sinPassword($fila),
        ];
    }

    public function yo(string $token): array
    {
        try {
            $claims = $this->jwtService->verificar($token);
        } catch (Throwable $exception) {
            throw new DomainException(
                'Token inválido o expirado.'
            );
        }

        $usuarioId = (int) ($claims->sub ?? 0);

        if ($usuarioId <= 0) {
            throw new DomainException(
                'Token inválido o expirado.'
            );
        }

        $fila = $this->usuarioRepository->buscarPorId($usuarioId);

        if ($fila === null) {
            throw new DomainException(
                'Token inválido o expirado.'
            );
        }

        if (($fila['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El usuario está suspendido.'
            );
        }

        if (!empty($claims->soloJugador)) {
            $fila['rol'] = 'jugador';
        }

        return $this->sinPassword($fila);
    }

    private function plantillaCorreoOtp(string $nombre, string $codigo): string
    {
        return EmailPlantilla::otp($nombre, $codigo);
    }

    private function buscarPorIdentificador(
        int $organizacionId,
        string $identificador
    ): ?array {
        if (str_contains($identificador, '@')) {
            return $this->usuarioRepository->buscarPorOrganizacionYCorreo(
                $organizacionId,
                mb_strtolower($identificador)
            );
        }

        return $this->usuarioRepository->buscarPorOrganizacionYCelular(
            $organizacionId,
            $identificador
        );
    }

    private function aEntidad(array $fila): Usuario
    {
        return new Usuario(
            $fila['nombre'],
            $fila['apellido'],
            $fila['celular'],
            $fila['correo'],
            (int) $fila['organizacionId'],
            (int) $fila['ciudadId'],
            $fila['rol'],
            (int) $fila['id'],
            $fila['estado'],
            $fila['creadoEn'],
            $fila['modificadoEn'],
            $fila['password']
        );
    }

    private function sinPassword(array $fila): array
    {
        unset($fila['password']);

        return $fila;
    }
}
