-- Pagos de cartones (venta manual, OpenPay PSE, comprobante).
-- Aplicar en la BD bingo.

CREATE TABLE IF NOT EXISTS pagos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacionId BIGINT UNSIGNED NOT NULL,
    partidaId BIGINT UNSIGNED NOT NULL,
    cartonId BIGINT UNSIGNED NOT NULL,
    usuarioId BIGINT UNSIGNED NOT NULL,
    operadorId BIGINT UNSIGNED NULL,
    canal ENUM('manual_operador', 'openpay_pse', 'comprobante') NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado', 'expirado')
        NOT NULL DEFAULT 'pendiente',
    monto DECIMAL(12, 2) NOT NULL,
    referenciaExterna VARCHAR(120) NULL,
    comprobanteUrl VARCHAR(255) NULL,
    creadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pagos_organizacion (organizacionId),
    KEY idx_pagos_partida (partidaId),
    KEY idx_pagos_carton (cartonId),
    KEY idx_pagos_usuario (usuarioId),
    KEY idx_pagos_estado (estado),
    KEY idx_pagos_referencia (referenciaExterna),
    CONSTRAINT fk_pagos_organizacion
        FOREIGN KEY (organizacionId) REFERENCES organizaciones (id),
    CONSTRAINT fk_pagos_partida
        FOREIGN KEY (partidaId) REFERENCES partidas (id),
    CONSTRAINT fk_pagos_carton
        FOREIGN KEY (cartonId) REFERENCES cartones (id),
    CONSTRAINT fk_pagos_usuario
        FOREIGN KEY (usuarioId) REFERENCES usuarios (id),
    CONSTRAINT fk_pagos_operador
        FOREIGN KEY (operadorId) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
