<?php

final class ComprobanteStorage
{
    private const MAX_BYTES = 5242880; // 5 MB

    private const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function guardar(array $archivo): string
    {
        if (
            !isset($archivo['error'], $archivo['tmp_name'], $archivo['size']) ||
            !is_uploaded_file($archivo['tmp_name'])
        ) {
            throw new InvalidArgumentException(
                'Debes enviar el archivo del comprobante.'
            );
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                'No fue posible subir el comprobante.'
            );
        }

        if ($archivo['size'] <= 0 || $archivo['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException(
                'El comprobante debe pesar entre 1 byte y 5 MB.'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($archivo['tmp_name']);

        if (!isset(self::MIMES[$mime])) {
            throw new InvalidArgumentException(
                'El comprobante debe ser JPG, PNG o WEBP.'
            );
        }

        $directorio = dirname(__DIR__, 2) . '/public/uploads/comprobantes';

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException(
                'No fue posible crear el directorio de comprobantes.'
            );
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        $destino = $directorio . '/' . $nombre;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new RuntimeException(
                'No fue posible guardar el comprobante.'
            );
        }

        return '/uploads/comprobantes/' . $nombre;
    }
}
