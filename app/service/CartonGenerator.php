<?php

final class CartonGenerator
{
    private const COLUMNAS = [
        'B' => [1, 15, 5],
        'I' => [16, 30, 5],
        'N' => [31, 45, 4],
        'G' => [46, 60, 5],
        'O' => [61, 75, 5],
    ];

    public function generarNumeros(): array
    {
        $numbers = [];

        foreach (self::COLUMNAS as $columna => [$min, $max, $cantidad]) {
            $disponibles = range($min, $max);
            $seleccionados = [];

            for ($i = 0; $i < $cantidad; $i++) {
                $indice = random_int(0, count($disponibles) - 1);

                $seleccionados[] = $disponibles[$indice];

                array_splice($disponibles, $indice, 1);
            }

            sort($seleccionados, SORT_NUMERIC);

            $numbers[$columna] = $seleccionados;
        }

        return $numbers;
    }
}