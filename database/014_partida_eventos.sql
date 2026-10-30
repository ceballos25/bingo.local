-- Eventos de mesa en vivo (balotas, reclamos) para SSE.
-- Aplicar en la BD bingo.

CREATE TABLE IF NOT EXISTS partida_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partidaId BIGINT UNSIGNED NOT NULL,
    tipo ENUM(
        'balota',
        'reclamo_pendiente',
        'reclamo_aprobado',
        'reclamo_rechazado',
        'partida_estado'
    ) NOT NULL,
    payload JSON NOT NULL,
    creadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partida_eventos_partida_id (partidaId, id),
    CONSTRAINT fk_partida_eventos_partida
        FOREIGN KEY (partidaId) REFERENCES partidas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
