<?php

final class OpenPayPasarela implements PasarelaPago
{
    private string $merchantId;
    private string $privateKey;
    private bool $sandbox;

    public function __construct()
    {
        $merchantId = trim((string) ($_ENV['OPENPAY_MERCHANT_ID'] ?? ''));
        $privateKey = trim((string) ($_ENV['OPENPAY_PRIVATE_KEY'] ?? ''));

        if ($merchantId === '' || $privateKey === '') {
            throw new RuntimeException(
                'Credenciales de OpenPay incompletas.'
            );
        }

        $this->merchantId = $merchantId;
        $this->privateKey = $privateKey;
        $this->sandbox = filter_var(
            $_ENV['OPENPAY_SANDBOX'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function iniciarPse(array $datos): ResultadoInicioPse
    {
        $cliente = $datos['cliente'] ?? null;

        if (!is_array($cliente)) {
            throw new InvalidArgumentException(
                'Los datos del cliente son obligatorios para PSE.'
            );
        }

        $amount = (int) round((float) $datos['monto']);

        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'El monto PSE debe ser mayor que cero.'
            );
        }

        $payload = [
            'method' => 'bank_account',
            'amount' => $amount,
            'currency' => 'COP',
            'iva' => (string) ($datos['iva'] ?? ($_ENV['OPENPAY_IVA'] ?? '0')),
            'description' => mb_substr((string) $datos['descripcion'], 0, 250),
            'order_id' => (string) $datos['orderId'],
            'redirect_url' => (string) $datos['redirectUrl'],
            'customer' => [
                'name' => (string) $cliente['nombre'],
                'last_name' => (string) $cliente['apellido'],
                'email' => (string) $cliente['correo'],
                'phone_number' => (string) $cliente['celular'],
                'requires_account' => false,
                'customer_address' => [
                    'department' => (string) $cliente['departamento'],
                    'city' => (string) $cliente['ciudad'],
                    'additional' => 'N/A',
                ],
            ],
        ];

        $respuesta = $this->request(
            'POST',
            '/v1/' . $this->merchantId . '/charges',
            $payload
        );

        $referencia = (string) ($respuesta['id'] ?? '');
        $url = (string) ($respuesta['payment_method']['url'] ?? '');

        if ($referencia === '' || $url === '') {
            throw new RuntimeException(
                'OpenPay no devolvió URL de pago PSE.'
            );
        }

        return new ResultadoInicioPse(
            $referencia,
            $url,
            (string) ($respuesta['status'] ?? 'in_progress')
        );
    }

    public function consultarCargo(string $cargoId): array
    {
        $cargoId = trim($cargoId);

        if ($cargoId === '') {
            throw new InvalidArgumentException(
                'El id del cargo es obligatorio.'
            );
        }

        return $this->request(
            'GET',
            '/v1/' . $this->merchantId . '/charges/' . rawurlencode($cargoId)
        );
    }

    public function interpretarWebhook(array $payload): array
    {
        $tipo = (string) ($payload['type'] ?? '');
        $transaccion = $payload['transaction'] ?? null;

        if (!is_array($transaccion)) {
            return [
                'accion' => 'ignorar',
                'referenciaExterna' => null,
                'orderId' => null,
            ];
        }

        $referencia = isset($transaccion['id'])
            ? (string) $transaccion['id']
            : null;
        $orderId = isset($transaccion['order_id'])
            ? (string) $transaccion['order_id']
            : null;

        if ($tipo === 'charge.succeeded') {
            return [
                'accion' => 'aprobar',
                'referenciaExterna' => $referencia,
                'orderId' => $orderId,
            ];
        }

        if (
            $tipo === 'charge.failed' ||
            $tipo === 'charge.cancelled' ||
            $tipo === 'charge.expired'
        ) {
            return [
                'accion' => 'rechazar',
                'referenciaExterna' => $referencia,
                'orderId' => $orderId,
            ];
        }

        return [
            'accion' => 'ignorar',
            'referenciaExterna' => $referencia,
            'orderId' => $orderId,
        ];
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null
    ): array {
        $base = $this->sandbox
            ? 'https://sandbox-api.openpay.co'
            : 'https://api.openpay.co';

        $ch = curl_init($base . $path);

        if ($ch === false) {
            throw new RuntimeException(
                'No fue posible iniciar la conexión con OpenPay.'
            );
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->privateKey . ':',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ];

        $verifySsl = filter_var(
            $_ENV['OPENPAY_VERIFY_SSL'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
        $options[CURLOPT_SSL_VERIFYPEER] = $verifySsl;

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException(
                'Error de red con OpenPay: ' . $error
            );
        }

        $decoded = [];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'Respuesta inválida de OpenPay.'
                );
            }
        }

        if ($status < 200 || $status >= 300) {
            $mensaje = $decoded['description']
                ?? $decoded['message']
                ?? ('HTTP ' . $status);

            throw new RuntimeException(
                'OpenPay rechazó la solicitud: ' . $mensaje
            );
        }

        return $decoded;
    }
}
