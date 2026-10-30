-- Reclamos de bingo por modalidad (linea / equis / completa).
-- Un solo ganador activo por modalidad: activo=1 (pendiente|aprobado),
-- activo=NULL al rechazar (permite nuevo canto).
-- Aplicar en la BD bingo.

CREATE TABLE IF NOT EXISTS reclamos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partidaId BIGINT UNSIGNED NOT NULL,
    cartonId BIGINT UNSIGNED NOT NULL,
    usuarioId BIGINT UNSIGNED NOT NULL,
    modalidad ENUM('linea', 'equis', 'completa') NOT NULL,
    premio DECIMAL(12, 2) NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado')
        NOT NULL DEFAULT 'pendiente',
    activo TINYINT UNSIGNED NULL DEFAULT 1,
    administradorId BIGINT UNSIGNED NULL,
    cantadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resueltoEn TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reclamo_modalidad_activa (partidaId, modalidad, activo),
    KEY idx_reclamos_partida (partidaId),
    KEY idx_reclamos_carton (cartonId),
    KEY idx_reclamos_usuario (usuarioId),
    KEY idx_reclamos_estado (estado),
    CONSTRAINT fk_reclamos_partida
        FOREIGN KEY (partidaId) REFERENCES partidas (id),
    CONSTRAINT fk_reclamos_carton
        FOREIGN KEY (cartonId) REFERENCES cartones (id),
    CONSTRAINT fk_reclamos_usuario
        FOREIGN KEY (usuarioId) REFERENCES usuarios (id),
    CONSTRAINT fk_reclamos_administrador
        FOREIGN KEY (administradorId) REFERENCES usuarios (id),
    CONSTRAINT chk_reclamos_activo
        CHECK (
            (estado IN ('pendiente', 'aprobado') AND activo = 1)
            OR (estado = 'rechazado' AND activo IS NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
