<?php

final class PasarelaPagoFactory
{
    public static function crear(?string $nombre = null): PasarelaPago
    {
        $nombre = strtolower(trim(
            $nombre ?? (string) ($_ENV['PAGO_PASARELA_DEFAULT'] ?? 'openpay')
        ));

        if ($nombre === 'openpay') {
            return new OpenPayPasarela();
        }

        throw new RuntimeException(
            "Pasarela de pago no soportada: {$nombre}."
        );
    }
}
