-- Enlace de transmisión YouTube (videoId) por partida.
-- El operador pega el link o el id; el front embebe el live.
-- Aplicar en la BD bingo.

ALTER TABLE partidas
    ADD COLUMN youtubeVideoId VARCHAR(32) NULL
        AFTER estado;
