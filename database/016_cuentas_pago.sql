-- Cuentas para transferencia / comprobante (por organización).
-- El admin crea/edita titulares y números; el jugador las ve al pagar.

CREATE TABLE IF NOT EXISTS cuentas_pago (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacionId BIGINT UNSIGNED NOT NULL,
    tipo ENUM('nequi', 'bancolombia', 'breb', 'otro') NOT NULL DEFAULT 'otro',
    banco VARCHAR(80) NOT NULL,
    marca VARCHAR(16) NOT NULL DEFAULT '',
    valor VARCHAR(80) NOT NULL COMMENT 'Valor limpio para copiar',
    etiqueta VARCHAR(120) NOT NULL COMMENT 'Texto visible (con espacios)',
    titular VARCHAR(120) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cuentas_pago_org (organizacionId),
    KEY idx_cuentas_pago_activo (organizacionId, activo, orden),
    CONSTRAINT fk_cuentas_pago_organizacion
        FOREIGN KEY (organizacionId) REFERENCES organizaciones (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semilla org 1 (titular provisional Cristian Ceballos).
INSERT INTO cuentas_pago
    (organizacionId, tipo, banco, marca, valor, etiqueta, titular, orden, activo)
VALUES
    (1, 'nequi', 'Nequi', 'N', '3245894268', '324 589 4268', 'Cristian Ceballos', 1, 1),
    (1, 'bancolombia', 'Bancolombia · Ahorros', 'B', '61127119979', '611 2711 9979', 'Cristian Ceballos', 2, 1),
    (1, 'breb', 'Llave Bre-B', '@', '@CEBALLOS1003', '@CEBALLOS1003', 'Cristian Ceballos', 3, 1);
