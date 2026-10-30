<?php

/**
 * Valida formas de bingo sobre un cartón 5×5 con centro libre (columna N).
 *
 * numeros: ['B'=>[5], 'I'=>[5], 'N'=>[4], 'G'=>[5], 'O'=>[5]]
 * sacadas: lista de enteros 1–75 ya cantados.
 */
final class BingoValidador
{
    private const COLUMNAS = ['B', 'I', 'N', 'G', 'O'];

    /**
     * @param array<string, list<int>> $numeros
     * @param list<int> $sacadas
     */
    public function cumple(
        array $numeros,
        array $sacadas,
        string $modalidad
    ): bool {
        $marcados = array_fill_keys(
            array_map('intval', $sacadas),
            true
        );

        return match ($modalidad) {
            'linea' => $this->cumpleLinea($numeros, $marcados),
            'equis' => $this->cumpleEquis($numeros, $marcados),
            'completa' => $this->cumpleCompleta($numeros, $marcados),
            default => throw new DomainException(
                'La modalidad debe ser linea, equis o completa.'
            ),
        };
    }

    /**
     * L: columna B completa + fila inferior (filas 0–4 de B y fila 4 de B–O).
     *
     * @param array<string, list<int>> $numeros
     * @param array<int, true> $marcados
     */
    private function cumpleLinea(array $numeros, array $marcados): bool
    {
        for ($fila = 0; $fila < 5; $fila++) {
            if (!$this->celdaMarcada($numeros, 'B', $fila, $marcados)) {
                return false;
            }
        }

        foreach (self::COLUMNAS as $columna) {
            if (!$this->celdaMarcada($numeros, $columna, 4, $marcados)) {
                return false;
            }
        }

        return true;
    }

    /**
     * X: ambas diagonales (el centro libre cuenta como marcado).
     *
     * @param array<string, list<int>> $numeros
     * @param array<int, true> $marcados
     */
    private function cumpleEquis(array $numeros, array $marcados): bool
    {
        for ($i = 0; $i < 5; $i++) {
            if (!$this->celdaMarcada(
                $numeros,
                self::COLUMNAS[$i],
                $i,
                $marcados
            )) {
                return false;
            }

            if (!$this->celdaMarcada(
                $numeros,
                self::COLUMNAS[4 - $i],
                $i,
                $marcados
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, list<int>> $numeros
     * @param array<int, true> $marcados
     */
    private function cumpleCompleta(array $numeros, array $marcados): bool
    {
        foreach (self::COLUMNAS as $columna) {
            foreach ($numeros[$columna] ?? [] as $numero) {
                if (!isset($marcados[(int) $numero])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<string, list<int>> $numeros
     * @param array<int, true> $marcados
     */
    private function celdaMarcada(
        array $numeros,
        string $columna,
        int $fila,
        array $marcados
    ): bool {
        if ($columna === 'N' && $fila === 2) {
            return true;
        }

        $numero = $this->numeroEn($numeros, $columna, $fila);

        return isset($marcados[$numero]);
    }

    /**
     * @param array<string, list<int>> $numeros
     */
    private function numeroEn(
        array $numeros,
        string $columna,
        int $fila
    ): int {
        if ($columna === 'N') {
            if ($fila === 2) {
                throw new DomainException(
                    'El centro libre no tiene número.'
                );
            }

            $indice = $fila < 2 ? $fila : $fila - 1;
        } else {
            $indice = $fila;
        }

        if (!isset($numeros[$columna][$indice])) {
            throw new DomainException(
                "Celda inválida en {$columna} fila {$fila}."
            );
        }

        return (int) $numeros[$columna][$indice];
    }
}
