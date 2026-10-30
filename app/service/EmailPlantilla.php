<?php

/**
 * Plantillas HTML de correo (tablas + estilos inline).
 * Tipografía grande y CTAs claros: pensado para adultos mayores.
 */
final class EmailPlantilla
{
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
     * Envoltorio común: encabezado azul + cuerpo + pie de página.
     */
    public static function envolver(
        string $tituloPestana,
        string $cuerpoHtml,
        string $eyebrow = ''
    ): string {
        $marca = self::escapar(self::marca());
        $eyebrowSeguro = self::escapar($eyebrow !== '' ? $eyebrow : 'Bingo en vivo');
        $tituloSeguro = self::escapar($tituloPestana);
        $anio = date('Y');
        $soporteMail = self::escapar((string) ($_ENV['GMAIL_FROM'] ?? ''));
        $appUrl = self::escapar(self::appUrl());

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$tituloSeguro}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef1f6;-webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef1f6;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #d8dee8;box-shadow:0 8px 24px rgba(0,51,153,0.08);">
          <tr>
            <td style="background-color:#003399;padding:24px 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:0.2px;">
                    {$marca}
                  </td>
                  <td align="right" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#ffcc00;font-weight:700;text-transform:uppercase;letter-spacing:1px;">
                    {$eyebrowSeguro}
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:0;font-family:Arial,Helvetica,sans-serif;color:#212529;">
              {$cuerpoHtml}
            </td>
          </tr>
          <tr>
            <td style="background-color:#0b1f4d;padding:22px 28px;font-family:Arial,Helvetica,sans-serif;color:#c9d4ef;text-align:center;">
              <p style="margin:0 0 8px;font-size:15px;font-weight:700;color:#ffffff;">{$marca}</p>
              <p style="margin:0 0 10px;font-size:13px;line-height:1.5;">
                ¿Necesitas ayuda? Escríbenos a
                <a href="mailto:{$soporteMail}" style="color:#ffcc00;text-decoration:none;font-weight:700;">{$soporteMail}</a>
              </p>
              <p style="margin:0 0 6px;font-size:12px;line-height:1.45;color:#9aabc9;">
                <a href="{$appUrl}/app/" style="color:#9aabc9;text-decoration:underline;">Abrir la app</a>
                · Este correo es automático; no respondas a este mensaje.
              </p>
              <p style="margin:12px 0 0;font-size:11px;color:#6f82a8;">
                © {$anio} {$marca}. Todos los derechos reservados.
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

        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:8px auto 4px;">
  <tr>
    <td align="center" bgcolor="#00994c" style="border-radius:10px;">
      <a href="{$urlSeguro}" style="display:inline-block;padding:16px 28px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
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

        $cuerpo = <<<HTML
<div style="padding:32px 28px 8px;">
  <p style="margin:0 0 10px;font-size:26px;font-weight:700;line-height:1.25;color:#003399;">
    Tu código de acceso
  </p>
  <p style="margin:0 0 22px;font-size:17px;line-height:1.55;color:#343a40;">
    Hola <strong>{$nombreSeguro}</strong>, usa este código para entrar. No lo compartas con nadie.
  </p>
</div>
<div style="padding:0 28px 8px;text-align:center;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;background-color:#f7f9fc;border:2px dashed #003399;border-radius:12px;">
    <tr>
      <td style="padding:20px 40px;font-family:'Courier New',Courier,monospace;font-size:40px;font-weight:700;letter-spacing:12px;color:#003399;text-align:center;">
        {$codigoSeguro}
      </td>
    </tr>
  </table>
</div>
<div style="padding:20px 28px 32px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff8e6;border-radius:10px;">
    <tr>
      <td style="padding:14px 16px;font-size:15px;line-height:1.5;color:#664d03;">
        <strong>Vence en 10 minutos.</strong> Si no pediste este código, ignora el correo.
      </td>
    </tr>
  </table>
</div>
HTML;

        return self::envolver('Código de acceso', $cuerpo, 'Acceso seguro');
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
        $boton = self::boton($urlJugar, 'Entrar y jugar (un toque)');
        $urlSeguro = self::escapar($urlJugar);
        $bloques = '';

        foreach ($cartones as $carton) {
            $id = (int) ($carton['id'] ?? 0);
            $numeros = is_array($carton['numeros'] ?? null) ? $carton['numeros'] : [];
            $bloques .= self::tarjetaCarton($id, $numeros);
        }

        $cuerpo = <<<HTML
<div style="padding:32px 28px 10px;">
  <p style="margin:0 0 10px;font-size:26px;font-weight:700;line-height:1.25;color:#003399;">
    Confirmación de compra
  </p>
  <p style="margin:0 0 8px;font-size:17px;line-height:1.55;color:#343a40;">
    Hola <strong>{$nombreSeguro}</strong>, tu pago quedó confirmado. Ya tienes tus cartones.
  </p>
  <p style="margin:0 0 18px;font-size:17px;line-height:1.55;color:#343a40;">
    Bingo: <strong>{$partidaSeguro}</strong><br>
    Recibiste: <strong>{$etiqueta}</strong>
  </p>
  {$boton}
  <p style="margin:18px 0 0;font-size:15px;line-height:1.55;color:#495057;">
    El botón verde abre la app con tu acceso. No necesitas contraseña.<br>
    Cuando empiece el bingo, toca <strong>Jugar en vivo</strong>.
  </p>
  <p style="margin:12px 0 0;font-size:13px;line-height:1.45;color:#6c757d;word-break:break-all;">
    Si el botón no funciona, copia este enlace:<br>
    <a href="{$urlSeguro}" style="color:#003399;">{$urlSeguro}</a>
  </p>
</div>
<div style="padding:8px 20px 28px;">
  {$bloques}
</div>
HTML;

        return self::envolver('Confirmación de compra', $cuerpo, 'Compra confirmada');
    }

    /**
     * @param array<string,mixed> $numeros
     */
    private static function tarjetaCarton(int $cartonId, array $numeros): string
    {
        $idSeguro = self::escapar((string) $cartonId);
        $letras = ['B', 'I', 'N', 'G', 'O'];
        $filasHtml = '';

        for ($r = 0; $r < 5; $r++) {
            $celdas = '';
            foreach ($letras as $letra) {
                $col = $numeros[$letra] ?? [];
                if (!is_array($col)) {
                    $col = [];
                }

                if ($letra === 'N' && count($col) === 4 && $r === 2) {
                    $texto = 'FREE';
                    $estilo = 'background-color:#ffcc00;color:#003399;font-weight:800;font-size:12px;';
                } else {
                    $idx = $r;
                    if ($letra === 'N' && count($col) === 4 && $r > 2) {
                        $idx = $r - 1;
                    }
                    $valor = $col[$idx] ?? '';
                    $texto = self::escapar((string) $valor);
                    $estilo = 'background-color:#ffffff;color:#111;font-weight:700;';
                }

                $celdas .= '<td align="center" style="border:1px solid #003399;padding:8px 4px;font-size:15px;line-height:1.1;'
                    . $estilo . '">' . $texto . '</td>';
            }
            $filasHtml .= '<tr>' . $celdas . '</tr>';
        }

        $heads = '';
        foreach ($letras as $letra) {
            $heads .= '<th style="background-color:#003399;color:#fff;font-size:14px;padding:8px 4px;border:1px solid #002266;">'
                . $letra . '</th>';
        }

        return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;border:2px solid #003399;border-radius:10px;overflow:hidden;">
  <tr>
    <td style="background-color:#e8eefc;padding:10px 12px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#003399;">
      Cartón #{$idSeguro}
    </td>
  </tr>
  <tr>
    <td style="padding:0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
        <tr>{$heads}</tr>
        {$filasHtml}
      </table>
    </td>
  </tr>
</table>
HTML;
    }
}
