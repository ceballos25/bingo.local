-- Balotas sacadas por partida (1–75, sin repetir).
-- Aplicar en la BD bingo.

CREATE TABLE IF NOT EXISTS partida_balotas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partidaId BIGINT UNSIGNED NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    orden INT UNSIGNED NOT NULL,
    letra ENUM('B', 'I', 'N', 'G', 'O') NOT NULL,
    sacadaEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partida_balota_numero (partidaId, numero),
    UNIQUE KEY uq_partida_balota_orden (partidaId, orden),
    KEY idx_partida_balotas_partida (partidaId),
    CONSTRAINT fk_partida_balotas_partida
        FOREIGN KEY (partidaId) REFERENCES partidas (id)
        ON DELETE CASCADE,
    CONSTRAINT chk_partida_balotas_numero
        CHECK (numero BETWEEN 1 AND 75)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
