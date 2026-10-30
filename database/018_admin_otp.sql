-- Códigos OTP para acceso de administrador (correo Gmail).

CREATE TABLE IF NOT EXISTS admin_otp (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacionId BIGINT UNSIGNED NOT NULL,
    usuarioId BIGINT UNSIGNED NOT NULL,
    codigoHash VARCHAR(255) NOT NULL,
    expiraEn DATETIME NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    usadoEn DATETIME NULL DEFAULT NULL,
    creadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_otp_usuario (usuarioId, usadoEn, expiraEn),
    KEY idx_admin_otp_org (organizacionId, creadoEn),
    CONSTRAINT fk_admin_otp_organizacion
        FOREIGN KEY (organizacionId) REFERENCES organizaciones (id),
    CONSTRAINT fk_admin_otp_usuario
        FOREIGN KEY (usuarioId) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
