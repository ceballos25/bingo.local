<?php

final class Balota
{
    private int $numero;
    private string $letra;

    private const RANGOS = [
        'B' => [1, 15],
        'I' => [16, 30],
        'N' => [31, 45],
        'G' => [46, 60],
        'O' => [61, 75],
    ];

    public function __construct(int $numero)
    {
        if ($numero < 1 || $numero > 75) {
            throw new DomainException(
                'La balota debe estar entre 1 y 75.'
            );
        }

        $this->numero = $numero;
        $this->letra = self::letraDe($numero);
    }

    public static function letraDe(int $numero): string
    {
        foreach (self::RANGOS as $letra => [$min, $max]) {
            if ($numero >= $min && $numero <= $max) {
                return $letra;
            }
        }

        throw new DomainException(
            'La balota debe estar entre 1 y 75.'
        );
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getLetra(): string
    {
        return $this->letra;
    }

    public function etiqueta(): string
    {
        return $this->letra . $this->numero;
    }

    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'letra' => $this->letra,
            'etiqueta' => $this->etiqueta(),
        ];
    }
}
