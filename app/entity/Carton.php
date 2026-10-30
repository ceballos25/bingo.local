<?php

final class Carton
{
    private ?int $id;
    private int $organizacionId;
    private ?int $partidaId;
    private ?int $usuarioId;
    private array $numbers;
    private string $estado;

    private const ESTADOS = ['disponible', 'reservado', 'vendido', 'anulado'];

    private const COLUMNAS = [
        'B' => [1, 15, 5],
        'I' => [16, 30, 5],
        'N' => [31, 45, 4],
        'G' => [46, 60, 5],
        'O' => [61, 75, 5],
    ];

    public function __construct(
        int $organizacionId,
        array $numbers,
        ?int $id = null,
        string $estado = 'disponible',
        ?int $usuarioId = null,
        ?int $partidaId = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException('El ID debe ser mayor que cero.');
        }

        if ($organizacionId <= 0) {
            throw new DomainException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        if ($usuarioId !== null && $usuarioId <= 0) {
            throw new DomainException(
                'El ID del usuario debe ser mayor que cero.'
            );
        }

        if ($partidaId !== null && $partidaId <= 0) {
            throw new DomainException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException('El estado no es válido.');
        }

        $this->validarNumeros($numbers);

        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->partidaId = $partidaId;
        $this->usuarioId = $usuarioId;
        $this->numbers = $numbers;
        $this->estado = $estado;
    }

    private function validarNumeros(array $numbers): void
    {
        if (array_keys($numbers) !== array_keys(self::COLUMNAS)) {
            throw new DomainException(
                'Las columnas B, I, N, G y O son obligatorias.'
            );
        }

        $todos = [];

        foreach (self::COLUMNAS as $columna => [$min, $max, $cantidad]) {
            $numerosColumna = $numbers[$columna];

            if (
                !is_array($numerosColumna) ||
                count($numerosColumna) !== $cantidad
            ) {
                throw new DomainException(
                    "Cantidad incorrecta en la columna {$columna}."
                );
            }

            foreach ($numerosColumna as $numero) {
                if (
                    !is_int($numero) ||
                    $numero < $min ||
                    $numero > $max
                ) {
                    throw new DomainException(
                        "Número inválido en la columna {$columna}."
                    );
                }

                $todos[] = $numero;
            }
        }

        if (count(array_unique($todos)) !== 24) {
            throw new DomainException(
                'El cartón no puede tener números repetidos.'
            );
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizacionId(): int
    {
        return $this->organizacionId;
    }

    public function getPartidaId(): ?int
    {
        return $this->partidaId;
    }

    public function getUsuarioId(): ?int
    {
        return $this->usuarioId;
    }

    public function getNumbers(): array
    {
        return $this->numbers;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function reservar(int $usuarioId): void
    {
        if ($this->estado !== 'disponible') {
            throw new DomainException(
                'El cartón no está disponible para reservar.'
            );
        }

        if ($usuarioId <= 0) {
            throw new DomainException(
                'El ID del usuario debe ser mayor que cero.'
            );
        }

        $this->usuarioId = $usuarioId;
        $this->estado = 'reservado';
    }

    public function liberar(): void
    {
        if ($this->estado !== 'reservado') {
            throw new DomainException(
                'Solo se puede liberar un cartón reservado.'
            );
        }

        $this->usuarioId = null;
        $this->estado = 'disponible';
    }

    public function vender(int $usuarioId): void
    {
        if ($this->estado !== 'disponible' && $this->estado !== 'reservado') {
            throw new DomainException(
                'El cartón no está disponible para la venta.'
            );
        }

        if ($usuarioId <= 0) {
            throw new DomainException(
                'El ID del usuario debe ser mayor que cero.'
            );
        }

        $this->usuarioId = $usuarioId;
        $this->estado = 'vendido';
    }

    public function anular(): void
    {
        if ($this->estado !== 'vendido') {
            throw new DomainException(
                'Solo se puede anular un cartón vendido.'
            );
        }

        $this->estado = 'anulado';
    }
}
