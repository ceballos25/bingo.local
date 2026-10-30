<?php

require_once __DIR__ . '/../bootstrap.php';

try {
    // 1. Crear la conexión a la base de datos.
    $database = Database::connect();

    // 2. Crear repositorios compartidos.
    $carton_repository = new CartonRepository($database);
    $organizacion_repository = new OrganizacionRepository($database);
    $usuario_repository = new UsuarioRepository($database);
    $ciudad_repository = new CiudadRepository($database);
    $generator = new CartonGenerator();

    // 3. Crear dependencias de organizaciones.
    $organizacion_service = new OrganizacionService($organizacion_repository);
    $organizacion_controller = new OrganizacionController($organizacion_service);

    // 4. Crear dependencias de usuarios.
    $usuario_service = new UsuarioService(
        $usuario_repository,
        $organizacion_repository,
        $ciudad_repository
    );
    $usuario_controller = new UsuarioController($usuario_service);

    // 5. Crear dependencias de cartones.
    $carton_service = new CartonService(
        $carton_repository,
        $generator,
        $organizacion_repository,
        $usuario_repository
    );
    $pago_repository = new PagoRepository($database);
    $carton_controller = new CartonController(
        $carton_service,
        $pago_repository
    );

    // 6. Crear dependencias de ciudades.
    $ciudad_controller = new CiudadController($ciudad_repository);

    // 7. Crear dependencias de autenticación.
    $jwt_service = new JwtService();
    $admin_otp_repository = new AdminOtpRepository($database);
    $mailer_service = new MailerService();
    $auth_service = new AuthService(
        $usuario_repository,
        $jwt_service,
        $usuario_service,
        $admin_otp_repository,
        $mailer_service,
        $organizacion_repository
    );
    $auth_controller = new AuthController($auth_service);
    $auth_guard = new AuthGuard($jwt_service, $usuario_repository);

    $rolesMesa = ['administrador', 'operador'];
    $rolesAdmin = ['administrador'];
    $rolesCualquiera = ['administrador', 'operador', 'jugador'];

    // 8. Crear dependencias de partidas / mesa en vivo.
    $partida_repository = new PartidaRepository($database);
    $reclamo_repository = new ReclamoRepository($database);
    $partida_evento_repository = new PartidaEventoRepository($database);
    $centrifugo_publisher = new CentrifugoPublisher();
    $push_subscription_repository = new PushSubscriptionRepository($database);
    $push_service = new PushService(
        $push_subscription_repository,
        $carton_repository
    );
    $push_controller = new PushController($push_service);
    $partida_realtime = new PartidaRealtime(
        $partida_evento_repository,
        $centrifugo_publisher,
        $push_service
    );
    $mesa_estado_service = new MesaEstadoService($reclamo_repository);
    $partida_eventos_service = new PartidaEventosService(
        $partida_repository,
        $partida_evento_repository,
        $mesa_estado_service
    );
    $partida_service = new PartidaService(
        $database,
        $partida_repository,
        $carton_repository,
        $generator,
        $organizacion_repository,
        $usuario_repository
    );
    $partida_controller = new PartidaController(
        $partida_service,
        $partida_eventos_service,
        $carton_repository
    );

    // 8b. Balotas.
    $balota_repository = new BalotaRepository($database);
    $balota_service = new BalotaService(
        $balota_repository,
        $partida_repository,
        $usuario_repository,
        $reclamo_repository,
        $partida_realtime,
        $mesa_estado_service
    );
    $balota_controller = new BalotaController($balota_service);

    // 9. Crear dependencias de ventas / pagos.
    $comprobante_storage = new ComprobanteStorage();
    $pasarela_pago = PasarelaPagoFactory::crear();
    $whatsapp_service = new WhatsAppService();
    $compra_notificacion_service = new CompraNotificacionService(
        $mailer_service,
        $whatsapp_service,
        $jwt_service,
        $usuario_repository,
        $partida_repository,
        $carton_repository
    );
    $venta_service = new VentaService(
        $database,
        $carton_repository,
        $partida_repository,
        $usuario_repository,
        $pago_repository,
        $comprobante_storage,
        $ciudad_repository,
        $pasarela_pago,
        $compra_notificacion_service
    );
    $venta_controller = new VentaController($venta_service);

    // 9b. Reclamos de bingo (L / X / completa).
    $bingo_validador = new BingoValidador();
    $reclamo_service = new ReclamoService(
        $database,
        $reclamo_repository,
        $carton_repository,
        $partida_repository,
        $balota_repository,
        $usuario_repository,
        $ciudad_repository,
        $bingo_validador,
        $partida_realtime,
        $mesa_estado_service
    );
    $reclamo_controller = new ReclamoController($reclamo_service);

    // 9c. Config realtime (SSE vs Centrifugo).
    $realtime_controller = new RealtimeController();

    // 9d. Cuentas de transferencia / comprobante.
    $cuenta_pago_repository = new CuentaPagoRepository($database);
    $cuenta_pago_service = new CuentaPagoService(
        $cuenta_pago_repository,
        $organizacion_repository
    );
    $cuenta_pago_controller = new CuentaPagoController($cuenta_pago_service);

    // 10. Identificar recurso, método y acción.
    //    /cartones/listar        → recurso cartones, acción listar
    //    /cartones/buscar?id=1   → recurso cartones, acción buscar
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = trim((string) $path, '/');
    $segments = $path === '' ? [] : explode('/', $path);

    $resource = $segments[0] ?? '';

    if ($resource === 'index.php') {
        $resource = '';
    }

    $action = $segments[1] ?? ($_GET['accion'] ?? '');

    $esCartones = $resource === '' || $resource === 'cartones';
    $esOrganizaciones = $resource === 'organizaciones';
    $esUsuarios = $resource === 'usuarios';
    $esCiudades = $resource === 'ciudades';
    $esAuth = $resource === 'auth';
    $esPartidas = $resource === 'partidas';
    $esBalotas = $resource === 'balotas';
    $esVentas = $resource === 'ventas';
    $esReclamos = $resource === 'reclamos';
    $esRealtime = $resource === 'realtime';
    $esPush = $resource === 'push';
    $esCuentasPago = $resource === 'cuentas-pago';

    $esSse = $esPartidas && $method === 'GET' && $action === 'eventos';

    if (!$esSse) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Driver activo: sse (default) o centrifugo.
    if ($esRealtime && $method === 'GET' && $action === 'config') {
        $realtime_controller->config();
        exit;
    }

    // Clave pública VAPID (PWA).
    if ($esPush && $method === 'GET' && $action === 'vapid-publica') {
        $push_controller->vapidPublica();
        exit;
    }

    if ($esPush && $method === 'POST' && $action === 'suscribir') {
        $auth_guard->exigir($rolesCualquiera);
        $push_controller->suscribir();
        exit;
    }

    if ($esPush && $method === 'POST' && $action === 'desuscribir') {
        $auth_guard->exigir($rolesCualquiera);
        $push_controller->desuscribir();
        exit;
    }

    // Login: correo o celular + password → JWT.
    if ($esAuth && $method === 'POST' && $action === 'login') {
        $auth_controller->login();
        exit;
    }

    // Registro público de jugador (siempre rol jugador).
    if ($esAuth && $method === 'POST' && $action === 'registro') {
        $auth_controller->registro();
        exit;
    }

    // Acceso sin contraseña (nombre + celular + correo → JWT + enlace único).
    if ($esAuth && $method === 'POST' && $action === 'invitado') {
        $auth_controller->invitado();
        exit;
    }

    // Jugador que ya jugó: correo + celular (mismo par) → JWT nuevo.
    if ($esAuth && $method === 'POST' && $action === 'acceso-invitado') {
        $auth_controller->accesoInvitado();
        exit;
    }

    // Administrador: OTP por correo (Gmail).
    if ($esAuth && $method === 'POST' && $action === 'otp-solicitar') {
        $auth_controller->solicitarOtpAdmin();
        exit;
    }

    if ($esAuth && $method === 'POST' && $action === 'otp-verificar') {
        $auth_controller->verificarOtpAdmin();
        exit;
    }

    // Sesión actual a partir del Bearer token.
    if ($esAuth && $method === 'GET' && $action === 'yo') {
        $auth_guard->exigir($rolesCualquiera);
        $auth_controller->yo();
        exit;
    }

    // Partidas: crear (genera cartones), listar, buscar.
    if ($esPartidas && $method === 'POST' && $action === 'crear') {
        $auth_guard->exigir($rolesMesa);
        $partida_controller->crear();
        exit;
    }

    if ($esPartidas && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesCualquiera);
        $partida_controller->listar();
        exit;
    }

    // Landing jugador: partidas abiertas sin JWT.
    if ($esPartidas && $method === 'GET' && $action === 'publicas') {
        $partida_controller->listarPublicas();
        exit;
    }

    // Comprar sin sesión: partida + cartones disponibles.
    if ($esPartidas && $method === 'GET' && $action === 'vitrina') {
        $partida_controller->vitrina();
        exit;
    }

    if ($esPartidas && $method === 'GET' && $action === 'buscar') {
        $auth_guard->exigir($rolesCualquiera);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $partida_controller->buscarPorId($id);
        exit;
    }

    if ($esPartidas && $method === 'POST' && $action === 'iniciar') {
        $auth_guard->exigir($rolesMesa);
        $partida_controller->iniciar();
        exit;
    }

    if ($esPartidas && $method === 'POST' && $action === 'finalizar') {
        $auth_guard->exigir($rolesMesa);
        $partida_controller->finalizar();
        exit;
    }

    if ($esPartidas && $method === 'POST' && $action === 'asignar-youtube') {
        $auth_guard->exigir($rolesMesa);
        $partida_controller->asignarYoutube();
        exit;
    }

    if ($esPartidas && $method === 'POST' && $action === 'marcar-gratis') {
        $auth_guard->exigir($rolesMesa);
        $partida_controller->marcarGratis();
        exit;
    }

    // SSE: balotas + reclamos (Bearer o ?token=).
    if ($esSse) {
        $auth_guard->exigir($rolesCualquiera);
        $partida_controller->eventos();
        exit;
    }

    // Balotas: sacar (aleatoria sin repetir) y listar.
    if ($esBalotas && $method === 'POST' && $action === 'sacar') {
        $auth_guard->exigir($rolesMesa);
        $balota_controller->sacar();
        exit;
    }

    if ($esBalotas && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesCualquiera);
        $balota_controller->listar();
        exit;
    }

    // Reclamos: prevalidar / cantar + validación del administrador.
    if ($esReclamos && $method === 'POST' && $action === 'prevalidar') {
        $auth_guard->exigir($rolesCualquiera);
        $reclamo_controller->prevalidar();
        exit;
    }

    if ($esReclamos && $method === 'POST' && $action === 'cantar') {
        $auth_guard->exigir($rolesCualquiera);
        $reclamo_controller->cantar();
        exit;
    }

    if ($esReclamos && $method === 'POST' && $action === 'aprobar') {
        $auth_guard->exigir($rolesAdmin);
        $reclamo_controller->aprobar();
        exit;
    }

    if ($esReclamos && $method === 'POST' && $action === 'rechazar') {
        $auth_guard->exigir($rolesAdmin);
        $reclamo_controller->rechazar();
        exit;
    }

    if ($esReclamos && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesCualquiera);
        $reclamo_controller->listar();
        exit;
    }

    // Venta manual: operador confirma pago y asigna cartón.
    if ($esVentas && $method === 'POST' && $action === 'manual') {
        $auth_guard->exigir($rolesMesa);
        $venta_controller->manual();
        exit;
    }

    // Bingo gratis: jugador reclama cartones sin cobro.
    if ($esVentas && $method === 'POST' && $action === 'gratis') {
        $auth_guard->exigir($rolesCualquiera);
        $venta_controller->gratis();
        exit;
    }

    // PSE OpenPay: reserva + URL del banco.
    if ($esVentas && $method === 'POST' && $action === 'pse') {
        $auth_guard->exigir($rolesCualquiera);
        $venta_controller->pse();
        exit;
    }

    // Retorno del banco (?id=cargo OpenPay). También alias /pago-ok.
    if (
        $method === 'GET' &&
        (
            ($esVentas && $action === 'retorno-pse') ||
            $resource === 'pago-ok'
        )
    ) {
        $venta_controller->retornoPse();
        exit;
    }

    // Webhook OpenPay (Basic Auth + verification / charge.*).
    if ($esVentas && $method === 'POST' && $action === 'webhook-openpay') {
        $venta_controller->webhookOpenpay();
        exit;
    }

    // Mesa: listado de pagos con cliente + cartón.
    if ($esVentas && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesMesa);
        $venta_controller->listar();
        exit;
    }

    // Jugador sube comprobante → cartón reservado + pago pendiente.
    if ($esVentas && $method === 'POST' && $action === 'comprobante') {
        $auth_guard->exigir($rolesCualquiera);
        $venta_controller->comprobante();
        exit;
    }

    // Operador aprueba comprobante → vendido.
    if ($esVentas && $method === 'POST' && $action === 'aprobar-comprobante') {
        $auth_guard->exigir($rolesMesa);
        $venta_controller->aprobarComprobante();
        exit;
    }

    // Operador rechaza comprobante → libera cartón.
    if ($esVentas && $method === 'POST' && $action === 'rechazar-comprobante') {
        $auth_guard->exigir($rolesMesa);
        $venta_controller->rechazarComprobante();
        exit;
    }

    // 6. Crear un cartón mediante POST.
    if ($esCartones && $method === 'POST' && $action === 'crear') {
        $auth_guard->exigir($rolesMesa);
        $carton_controller->crear();
        exit;
    }

    // 7. Listar cartones mediante GET.
    if ($esCartones && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesCualquiera);
        $carton_controller->listar();
        exit;
    }

    // 8. Buscar un cartón mediante GET.
    if ($esCartones && $method === 'GET' && $action === 'buscar') {
        $auth_guard->exigir($rolesCualquiera);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $carton_controller->buscarPorId($id);
        exit;
    }

    // 9. Actualizar el estado de un cartón mediante PUT.
    if ($esCartones && $method === 'PUT' && $action === 'actualizar') {
        $auth_guard->exigir($rolesMesa);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $carton_controller->actualizar($id);
        exit;
    }

    // 10. Anular un cartón vendido mediante DELETE.
    if ($esCartones && $method === 'DELETE' && $action === 'eliminar') {
        $auth_guard->exigir($rolesMesa);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $carton_controller->eliminar($id);
        exit;
    }

    // 11. Crear una organización mediante POST (alta SaaS · solo admin).
    if ($esOrganizaciones && $method === 'POST' && $action === 'crear') {
        $auth_guard->exigir($rolesAdmin);
        $organizacion_controller->crear();
        exit;
    }

    // 11b. Resumen panel mesa (clientes, cartones, partidas, recaudado).
    if ($esOrganizaciones && $method === 'GET' && $action === 'resumen') {
        $auth_guard->exigir($rolesMesa);
        $organizacion_controller->resumen();
        exit;
    }

    // 12. Listar organizaciones mediante GET.
    if ($esOrganizaciones && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesAdmin);
        $organizacion_controller->listar();
        exit;
    }

    // 13. Buscar una organización mediante GET.
    if ($esOrganizaciones && $method === 'GET' && $action === 'buscar') {
        $auth_guard->exigir($rolesCualquiera);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        // Admin puede consultar cualquier tenant; mesa/jugador solo la propia.
        if (
            $id !== AuthContext::organizacionId()
            && AuthContext::rol() !== 'administrador'
        ) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Organización no encontrada.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $organizacion_controller->buscarPorId($id);
        exit;
    }

    // 14. Actualizar una organización mediante PUT (admin · cualquier tenant).
    if ($esOrganizaciones && $method === 'PUT' && $action === 'actualizar') {
        $auth_guard->exigir($rolesAdmin);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $organizacion_controller->actualizar($id);
        exit;
    }

    // 15. Crear un usuario mediante POST.
    if ($esUsuarios && $method === 'POST' && $action === 'crear') {
        $auth_guard->exigir($rolesMesa);
        $usuario_controller->crear();
        exit;
    }

    // 16. Listar usuarios mediante GET.
    if ($esUsuarios && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesMesa);
        $usuario_controller->listar();
        exit;
    }

    // 17. Buscar un usuario mediante GET.
    if ($esUsuarios && $method === 'GET' && $action === 'buscar') {
        $auth_guard->exigir($rolesCualquiera);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $usuario_controller->buscarPorId($id);
        exit;
    }

    // 18. Actualizar un usuario mediante PUT.
    // Mesa: cualquier usuario de la org. Jugador: solo su propio perfil (sin rol/estado).
    if ($esUsuarios && $method === 'PUT' && $action === 'actualizar') {
        $auth_guard->exigir($rolesCualquiera);
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $usuario_controller->actualizar($id);
        exit;
    }

    // 19. Listar ciudades mediante GET.
    if ($esCiudades && $method === 'GET' && $action === 'listar') {
        $ciudad_controller->listar();
        exit;
    }

    // Cuentas de pago (transferencia / comprobante).
    if ($esCuentasPago && $method === 'GET' && $action === 'publicas') {
        $cuenta_pago_controller->listarPublicas();
        exit;
    }

    if ($esCuentasPago && $method === 'GET' && $action === 'listar') {
        $auth_guard->exigir($rolesAdmin);
        $cuenta_pago_controller->listar();
        exit;
    }

    if ($esCuentasPago && $method === 'POST' && $action === 'crear') {
        $auth_guard->exigir($rolesAdmin);
        $cuenta_pago_controller->crear();
        exit;
    }

    if ($esCuentasPago && $method === 'PUT' && $action === 'actualizar') {
        $auth_guard->exigir($rolesAdmin);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cuenta_pago_controller->actualizar($id);
        exit;
    }

    if ($esCuentasPago && $method === 'DELETE' && $action === 'eliminar') {
        $auth_guard->exigir($rolesAdmin);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Debes proporcionar un ID entero válido.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cuenta_pago_controller->eliminar($id);
        exit;
    }

    // 20. Solicitud no reconocida.
    http_response_code(404);

    echo json_encode([
        'error' => 'Ruta no encontrada.',
        'rutas_disponibles' => [
            'POST /cartones/crear',
            'GET /cartones/listar',
            'GET /cartones/listar?organizacionId=1',
            'GET /cartones/listar?usuarioId=1',
            'GET /cartones/listar?partidaId=1',
            'GET /cartones/buscar?id=1',
            'PUT /cartones/actualizar?id=1',
            'DELETE /cartones/eliminar?id=1',
            'POST /organizaciones/crear',
            'GET /organizaciones/resumen',
            'GET /organizaciones/listar',
            'GET /organizaciones/buscar?id=1',
            'PUT /organizaciones/actualizar?id=1',
            'POST /usuarios/crear',
            'GET /usuarios/listar',
            'GET /usuarios/listar?organizacionId=1',
            'GET /usuarios/buscar?id=1',
            'PUT /usuarios/actualizar?id=1',
            'GET /ciudades/listar',
            'POST /auth/login',
            'POST /auth/registro',
            'POST /auth/invitado',
            'POST /auth/acceso-invitado',
            'POST /auth/otp-solicitar',
            'POST /auth/otp-verificar',
            'GET /auth/yo',
            'POST /partidas/crear',
            'GET /partidas/listar',
            'GET /partidas/publicas?organizacionId=1',
            'GET /partidas/listar?organizacionId=1',
            'GET /partidas/vitrina?id=1',
            'GET /partidas/buscar?id=1',
            'POST /partidas/iniciar',
            'POST /partidas/finalizar',
            'POST /partidas/asignar-youtube',
            'POST /partidas/marcar-gratis',
            'GET /partidas/eventos?partidaId=1&desdeId=0',
            'GET /realtime/config',
            'GET /push/vapid-publica',
            'POST /push/suscribir',
            'POST /push/desuscribir',
            'POST /balotas/sacar',
            'GET /balotas/listar?partidaId=1',
            'POST /reclamos/prevalidar',
            'POST /reclamos/cantar',
            'POST /reclamos/aprobar',
            'POST /reclamos/rechazar',
            'GET /reclamos/listar?partidaId=1',
            'POST /ventas/manual',
            'POST /ventas/gratis',
            'GET /ventas/listar',
            'POST /ventas/pse',
            'GET /ventas/retorno-pse?id=',
            'GET /pago-ok?id=',
            'POST /ventas/webhook-openpay',
            'POST /ventas/comprobante',
            'POST /ventas/aprobar-comprobante',
            'POST /ventas/rechazar-comprobante',
            'GET /cuentas-pago/publicas?organizacionId=1',
            'GET /cuentas-pago/listar',
            'POST /cuentas-pago/crear',
            'PUT /cuentas-pago/actualizar?id=1',
            'DELETE /cuentas-pago/eliminar?id=1',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $exception) {
    error_log($exception->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'error' => 'Error interno del servidor.',
    ], JSON_UNESCAPED_UNICODE);
}
