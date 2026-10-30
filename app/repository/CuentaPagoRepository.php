<?php

final class CuentaPagoRepository
{
    public function __construct(
        private PDO $database
    ) {
    }

    public function guardar(CuentaPago $cuenta): int
    {
        $sql = 'INSERT INTO cuentas_pago (
                    organizacionId,
                    tipo,
                    banco,
                    marca,
                    valor,
                    etiqueta,
                    titular,
                    orden,
                    activo
                ) VALUES (
                    :organizacionId,
                    :tipo,
                    :banco,
                    :marca,
                    :valor,
                    :etiqueta,
                    :titular,
                    :orden,
                    :activo
                )';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'organizacionId' => $cuenta->getOrganizacionId(),
            'tipo' => $cuenta->getTipo(),
            'banco' => $cuenta->getBanco(),
            'marca' => $cuenta->getMarca(),
            'valor' => $cuenta->getValor(),
            'etiqueta' => $cuenta->getEtiqueta(),
            'titular' => $cuenta->getTitular(),
            'orden' => $cuenta->getOrden(),
            'activo' => $cuenta->estaActiva() ? 1 : 0,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function actualizar(CuentaPago $cuenta): void
    {
        $sql = 'UPDATE cuentas_pago
                SET
                    tipo = :tipo,
                    banco = :banco,
                    marca = :marca,
                    valor = :valor,
                    etiqueta = :etiqueta,
                    titular = :titular,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id
                  AND organizacionId = :organizacionId';

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'tipo' => $cuenta->getTipo(),
            'banco' => $cuenta->getBanco(),
            'marca' => $cuenta->getMarca(),
            'valor' => $cuenta->getValor(),
            'etiqueta' => $cuenta->getEtiqueta(),
            'titular' => $cuenta->getTitular(),
            'orden' => $cuenta->getOrden(),
            'activo' => $cuenta->estaActiva() ? 1 : 0,
            'id' => $cuenta->getId(),
            'organizacionId' => $cuenta->getOrganizacionId(),
        ]);
    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    tipo,
                    banco,
                    marca,
                    valor,
                    etiqueta,
                    titular,
                    orden,
                    activo,
                    creadoEn,
                    modificadoEn
                FROM cuentas_pago
                WHERE id = :id';

        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /**
     * @return list<array>
     */
    public function listarPorOrganizacion(int $organizacionId, bool $soloActivas = false): array
    {
        $sql = 'SELECT
                    id,
                    organizacionId,
                    tipo,
                    banco,
                    marca,
                    valor,
                    etiqueta,
                    titular,
                    orden,
                    activo,
                    creadoEn,
                    modificadoEn
                FROM cuentas_pago
                WHERE organizacionId = :organizacionId';

        if ($soloActivas) {
            $sql .= ' AND activo = 1';
        }

        $sql .= ' ORDER BY orden ASC, id ASC';

        $statement = $this->database->prepare($sql);
        $statement->execute(['organizacionId' => $organizacionId]);
        $filas = $statement->fetchAll();

        foreach ($filas as &$fila) {
            $fila = $this->normalizar($fila);
        }

        unset($fila);

        return $filas;
    }

    private function normalizar(array $fila): array
    {
        $fila['id'] = (int) $fila['id'];
        $fila['organizacionId'] = (int) $fila['organizacionId'];
        $fila['orden'] = (int) $fila['orden'];
        $fila['activo'] = (bool) (int) $fila['activo'];

        return $fila;
    }
}
