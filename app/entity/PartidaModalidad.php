<?php

final class PartidaModalidad
{
    private string $modalidad;
    private float $premio;

    private const MODALIDADES = [
        'linea',
        'equis',
        'completa',
    ];

    public function __construct(
        string $modalidad,
        float $premio
    ) {
        $modalidad = trim($modalidad);

        if (!in_array($modalidad, self::MODALIDADES, true)) {
            throw new DomainException(
                'La modalidad debe ser linea, equis o completa.'
            );
        }

        if ($premio <= 0) {
            throw new DomainException(
                'El premio debe ser mayor que cero.'
            );
        }

        $this->modalidad = $modalidad;
        $this->premio = round($premio, 2);
    }

    public function getModalidad(): string
    {
        return $this->modalidad;
    }

    public function getPremio(): float
    {
        return $this->premio;
    }

    public function toArray(): array
    {
        return [
            'modalidad' => $this->modalidad,
            'premio' => $this->premio,
        ];
    }
}
