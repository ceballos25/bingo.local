-- Bingo gratis: partida promocional sin cobro por cartón.
-- Aplicar en la BD bingo.

ALTER TABLE partidas
    ADD COLUMN gratis TINYINT(1) NOT NULL DEFAULT 0
        AFTER precioCarton;

ALTER TABLE pagos
    MODIFY COLUMN canal ENUM(
        'manual_operador',
        'openpay_pse',
        'comprobante',
        'gratis'
    ) NOT NULL;
