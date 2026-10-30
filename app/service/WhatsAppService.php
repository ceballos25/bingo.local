<?php

/**
 * WhatsApp Cloud API (Meta) — envío al celular del comprador.
 * Si no hay credenciales, no falla la venta (solo log).
 *
 * .env-bi:
 *   WHATSAPP_ENABLED=1
 *   WHATSAPP_TOKEN=...
 *   WHATSAPP_PHONE_NUMBER_ID=...
 *   WHATSAPP_TEMPLATE_NAME=compra_cartones   (opcional; recomendado para salida en frío)
 *   WHATSAPP_TEMPLATE_LANG=es
 */
final class WhatsAppService
{
    public function estaConfigurado(): bool
    {
        if (!$this->flagActivo()) {
            return false;
        }

        return $this->token() !== '' && $this->phoneNumberId() !== '';
    }

    /**
     * Enlace manual wa.me (mesa / respaldo). Texto ya armado.
     */
    public static function urlManual(?string $celular, string $texto): ?string
    {
        $destino = self::normalizarCelular($celular);

        if ($destino === null) {
            return null;
        }

        return 'https://wa.me/' . $destino . '?text=' . rawurlencode($texto);
    }

    /**
     * Texto libre (solo si hay ventana de 24 h) o plantilla de confirmación.
     *
     * @param list<string> $paramsPlantilla body {{1}}, {{2}}, {{3}}…
     *   Recomendado Meta: {{1}}=nombre {{2}}=bingo {{3}}=enlace con token
     */
    public function enviarConfirmacion(
        string $celular,
        string $textoLibre,
        array $paramsPlantilla = []
    ): bool {
        if (!$this->estaConfigurado()) {
            return false;
        }

        $destino = self::normalizarCelular($celular);

        if ($destino === null) {
            error_log('WhatsAppService: celular inválido');

            return false;
        }

        $plantilla = trim((string) ($_ENV['WHATSAPP_TEMPLATE_NAME'] ?? ''));

        if ($plantilla !== '') {
            $params = $paramsPlantilla !== []
                ? $paramsPlantilla
                : [mb_substr(trim($textoLibre), 0, 1024)];

            return $this->enviarPlantilla($destino, $plantilla, $params);
        }

        $textoLibre = trim($textoLibre);

        if ($textoLibre === '') {
            return false;
        }

        return $this->postMensaje([
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $textoLibre,
            ],
        ]);
    }

    /** @deprecated Usa enviarConfirmacion */
    public function enviarTexto(string $celular, string $texto): bool
    {
        return $this->enviarConfirmacion($celular, $texto);
    }

    /**
     * Plantilla de confirmación (Meta).
     * Ejemplo de cuerpo aprobado:
     * "Hola {{1}}, tu compra del bingo {{2}} está confirmada. Entra a jugar: {{3}}"
     *
     * @param list<string> $parametrosBody
     */
    private function enviarPlantilla(
        string $destino,
        string $nombrePlantilla,
        array $parametrosBody
    ): bool {
        $lang = trim((string) ($_ENV['WHATSAPP_TEMPLATE_LANG'] ?? 'es'));

        if ($lang === '') {
            $lang = 'es';
        }

        $parameters = [];

        foreach ($parametrosBody as $valor) {
            $texto = trim((string) $valor);

            if ($texto === '') {
                continue;
            }

            $parameters[] = [
                'type' => 'text',
                'text' => mb_substr($texto, 0, 1024),
            ];
        }

        if ($parameters === []) {
            return false;
        }

        return $this->postMensaje([
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'template',
            'template' => [
                'name' => $nombrePlantilla,
                'language' => ['code' => $lang],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ]);
    }

    private function postMensaje(array $payload): bool
    {
        $url = 'https://graph.facebook.com/v21.0/'
            . rawurlencode($this->phoneNumberId())
            . '/messages';

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return false;
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            error_log(
                'WhatsAppService HTTP ' . $code . ' ' . $err . ' ' . (string) $raw
            );

            return false;
        }

        return true;
    }

    /** @return string|null E.164 sin + (ej. 573001112233) */
    public static function normalizarCelular(?string $celular): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $celular);

        if ($digitos === null || $digitos === '') {
            return null;
        }

        // Colombia 10 dígitos → anteponer 57
        if (strlen($digitos) === 10 && str_starts_with($digitos, '3')) {
            $digitos = '57' . $digitos;
        }

        if (strlen($digitos) < 10 || strlen($digitos) > 15) {
            return null;
        }

        return $digitos;
    }

    private function flagActivo(): bool
    {
        $v = strtolower(trim((string) ($_ENV['WHATSAPP_ENABLED'] ?? '0')));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    private function token(): string
    {
        return trim((string) ($_ENV['WHATSAPP_TOKEN'] ?? ''));
    }

    private function phoneNumberId(): string
    {
        return trim((string) ($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? ''));
    }
}
