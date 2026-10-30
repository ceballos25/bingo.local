<?php

interface PasarelaPago
{
    /**
     * @param array{
     *   monto: float,
     *   descripcion: string,
     *   orderId: string,
     *   redirectUrl: string,
     *   iva?: string,
     *   cliente: array{
     *     nombre: string,
     *     apellido: string,
     *     correo: string,
     *     celular: string,
     *     departamento: string,
     *     ciudad: string
     *   }
     * } $datos
     */
    public function iniciarPse(array $datos): ResultadoInicioPse;

    /**
     * Interpreta el payload del webhook de la pasarela.
     *
     * @return array{accion: string, referenciaExterna: ?string, orderId: ?string}
     *         accion: ignorar | aprobar | rechazar
     */
    public function interpretarWebhook(array $payload): array;
}
