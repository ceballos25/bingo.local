<?php

/**
 * Plantillas HTML de correo (tablas + estilos inline).
 * Estilo tipo verificación Meta: fondo claro, tipografía sobria, un solo azul de acento.
 */
final class EmailPlantilla
{
    /** Azul de acento (CTA / código), no el azul de feria del bingo. */
    private const AZUL = '#0866FF';

    private const TEXTO = '#1c1e21';
    private const MUTED = '#65676b';
    private const BORDE = '#dadde1';
    private const FONDO = '#f0f2f5';

    public static function marca(): string
    {
        $nombre = trim((string) ($_ENV['GMAIL_FROM_NAME'] ?? ''));

        return $nombre !== '' ? $nombre : 'Bingo Virtual';
    }

    public static function appUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://bingo.local'), '/');
    }

    public static function urlApp(string $hashRuta = '#/landing'): string
    {
        $hash = str_starts_with($hashRuta, '#') ? $hashRuta : '#/' . ltrim($hashRuta, '/');

        return self::appUrl() . '/app/' . $hash;
    }

    public static function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Envoltorio: tarjeta blanca, marca sobria, pie gris (sin amarillo).
     */
    public static function envolver(
        string $tituloPestana,
        string $cuerpoHtml,
        string $eyebrow = ''
    ): string {
        $marca = self::escapar(self::marca());
        $tituloSeguro = self::escapar($tituloPestana);
        $anio = date('Y');
        $soporteMail = self::escapar((string) ($_ENV['GMAIL_FROM'] ?? ''));
        $appUrl = self::escapar(self::appUrl());
        $azul = self::AZUL;
        $texto = self::TEXTO;
        $muted = self::MUTED;
        $borde = self::BORDE;
        $fondo = self::FONDO;
        unset($eyebrow);

        $bloqueSoporte = '';
        if ($soporteMail !== '') {
            $bloqueSoporte = <<<HTML
              <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:{$muted};">
                ¿Necesitas ayuda?
                <a href="mailto:{$soporteMail}" style="color:{$azul};text-decoration:none;">{$soporteMail}</a>
              </p>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$tituloSeguro}</title>
</head>
<body style="margin:0;padding:0;background-color:{$fondo};-webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$fondo};padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;background-color:#ffffff;border-radius:8px;border:1px solid {$borde};">
          <tr>
            <td style="padding:28px 28px 0;font-family:Helvetica,Arial,sans-serif;">
              <p style="margin:0;font-size:15px;font-weight:600;color:{$texto};letter-spacing:-0.01em;">
                {$marca}
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:0;font-family:Helvetica,Arial,sans-serif;color:{$texto};">
              {$cuerpoHtml}
            </td>
          </tr>
          <tr>
            <td style="padding:24px 28px 28px;font-family:Helvetica,Arial,sans-serif;border-top:1px solid {$borde};">
              {$bloqueSoporte}
              <p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:{$muted};">
                <a href="{$appUrl}/app/" style="color:{$muted};text-decoration:underline;">Abrir la app</a>
                · Correo automático; no respondas a este mensaje.
              </p>
              <p style="margin:0;font-size:12px;color:{$muted};">
                © {$anio} {$marca}
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    public static function boton(string $url, string $texto): string
    {
        $urlSeguro = self::escapar($url);
        $textoSeguro = self::escapar($texto);
        $azul = self::AZUL;

        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:4px auto;">
  <tr>
    <td align="center" bgcolor="{$azul}" style="border-radius:6px;">
      <a href="{$urlSeguro}" style="display:inline-block;padding:12px 24px;font-family:Helvetica,Arial,sans-serif;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">
        {$textoSeguro}
      </a>
    </td>
  </tr>
</table>
HTML;
    }

    public static function otp(string $nombre, string $codigo): string
    {
        $nombreSeguro = self::escapar($nombre);
        $codigoSeguro = self::escapar($codigo);
        $texto = self::TEXTO;
        $muted = self::MUTED;
        $borde = self::BORDE;
        $azul = self::AZUL;

        $cuerpo = <<<HTML
<div style="padding:20px 28px 8px;">
  <p style="margin:0 0 12px;font-size:22px;font-weight:600;line-height:1.3;color:{$texto};">
    Tu código de acceso
  </p>
  <p style="margin:0 0 24px;font-size:15px;line-height:1.5;color:{$muted};">
    Hola {$nombreSeguro}, introduce este código para continuar. No lo compartas con nadie.
  </p>
</div>
<div style="padding:0 28px 8px;text-align:center;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;background-color:#f7f8fa;border:1px solid {$borde};border-radius:8px;">
    <tr>
      <td style="padding:18px 32px;font-family:Helvetica,Arial,sans-serif;font-size:36px;font-weight:700;letter-spacing:10px;color:{$texto};text-align:center;">
        {$codigoSeguro}
      </td>
    </tr>
  </table>
</div>
<div style="padding:20px 28px 28px;">
  <p style="margin:0;font-size:13px;line-height:1.5;color:{$muted};">
    Este código vence en <strong style="color:{$texto};">10 minutos</strong>.
    Si no pediste este acceso, puedes ignorar el correo.
  </p>
  <p style="margin:16px 0 0;font-size:12px;line-height:1.45;color:{$muted};">
    Por tu seguridad, <span style="color:{$azul};">nunca</span> te pediremos este código por teléfono o chat.
  </p>
</div>
HTML;

        return self::envolver('Código de acceso', $cuerpo);
    }

    /**
     * @param list<array{id:int,numeros:array}> $cartones
     */
    public static function cartonesComprados(
        string $nombreCliente,
        string $nombrePartida,
        array $cartones,
        string $urlJugar
    ): string {
        $nombreSeguro = self::escapar($nombreCliente);
        $partidaSeguro = self::escapar($nombrePartida);
        $cantidad = count($cartones);
        $etiqueta = $cantidad === 1 ? '1 cartón' : "{$cantidad} cartones";
        // El JWT va solo en el href del botón (no se muestra el enlace largo al cliente).
        $boton = self::boton($urlJugar, 'Jugar');
        $texto = self::TEXTO;
        $muted = self::MUTED;
        $bloques = '';

        foreach ($cartones as $carton) {
            $id = (int) ($carton['id'] ?? 0);
            $numeros = is_array($carton['numeros'] ?? null) ? $carton['numeros'] : [];
            $bloques .= self::tarjetaCarton($id, $numeros);
        }

        $cuerpo = <<<HTML
<div style="padding:20px 28px 10px;">
  <p style="margin:0 0 12px;font-size:22px;font-weight:600;line-height:1.3;color:{$texto};">
    Compra confirmada
  </p>
  <p style="margin:0 0 8px;font-size:15px;line-height:1.5;color:{$muted};">
    Hola {$nombreSeguro}, tu pago quedó registrado.
  </p>
  <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:{$texto};">
    <strong>{$partidaSeguro}</strong><br>
    <span style="color:{$muted};">{$etiqueta}</span>
  </p>
  {$boton}
</div>
<div style="padding:8px 20px 24px;">
  {$bloques}
</div>
HTML;

        return self::envolver('Confirmación de compra', $cuerpo);
    }

    /**
     * @param array<string,mixed> $numeros
     */
    private static function tarjetaCarton(int $cartonId, array $numeros): string
    {
        $idSeguro = self::escapar((string) $cartonId);
        $letras = ['B', 'I', 'N', 'G', 'O'];
        $filasHtml = '';
        $borde = self::BORDE;
        $texto = self::TEXTO;
        $muted = self::MUTED;

        for ($r = 0; $r < 5; $r++) {
            $celdas = '';
            foreach ($letras as $letra) {
                $col = $numeros[$letra] ?? [];
                if (!is_array($col)) {
                    $col = [];
                }

                if ($letra === 'N' && count($col) === 4 && $r === 2) {
                    $celdaTexto = 'FREE';
                    $estilo = 'background-color:#f0f2f5;color:' . $muted . ';font-weight:700;font-size:11px;';
                } else {
                    $idx = $r;
                    if ($letra === 'N' && count($col) === 4 && $r > 2) {
                        $idx = $r - 1;
                    }
                    $valor = $col[$idx] ?? '';
                    $celdaTexto = self::escapar((string) $valor);
                    $estilo = 'background-color:#ffffff;color:' . $texto . ';font-weight:600;';
                }

                $celdas .= '<td align="center" style="border:1px solid ' . $borde . ';padding:8px 4px;font-size:14px;line-height:1.1;'
                    . $estilo . '">' . $celdaTexto . '</td>';
            }
            $filasHtml .= '<tr>' . $celdas . '</tr>';
        }

        $heads = '';
        foreach ($letras as $letra) {
            $heads .= '<th style="background-color:#f7f8fa;color:' . $muted . ';font-size:12px;font-weight:700;padding:8px 4px;border:1px solid '
                . $borde . ';">' . $letra . '</th>';
        }

        return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px;border:1px solid {$borde};border-radius:8px;overflow:hidden;">
  <tr>
    <td style="background-color:#ffffff;padding:12px 14px;font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;color:{$texto};border-bottom:1px solid {$borde};">
      Cartón #{$idSeguro}
    </td>
  </tr>
  <tr>
    <td style="padding:0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Helvetica,Arial,sans-serif;">
        <tr>{$heads}</tr>
        {$filasHtml}
      </table>
    </td>
  </tr>
</table>
HTML;
    }
}
