<?php

/**
 * Normaliza un id o URL de YouTube a videoId embebible.
 */
final class YoutubeVideoId
{
    public static function parse(?string $entrada): ?string
    {
        if ($entrada === null) {
            return null;
        }

        $entrada = trim($entrada);

        if ($entrada === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $entrada) === 1) {
            return $entrada;
        }

        $candidatos = [];

        if (
            preg_match(
                '~(?:youtube\.com/watch\?[^#]*v=|youtu\.be/|youtube\.com/live/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~',
                $entrada,
                $match
            ) === 1
        ) {
            $candidatos[] = $match[1];
        }

        $query = parse_url($entrada, PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            parse_str($query, $params);

            if (
                isset($params['v'])
                && is_string($params['v'])
                && preg_match('/^[A-Za-z0-9_-]{11}$/', $params['v']) === 1
            ) {
                $candidatos[] = $params['v'];
            }
        }

        if ($candidatos === []) {
            throw new DomainException(
                'El enlace o id de YouTube no es válido.'
            );
        }

        return $candidatos[0];
    }

    public static function embedUrl(?string $videoId): ?string
    {
        if ($videoId === null || $videoId === '') {
            return null;
        }

        return 'https://www.youtube.com/embed/' . rawurlencode($videoId)
            . '?playsinline=1&rel=0&modestbranding=1';
    }
}
