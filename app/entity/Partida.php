<?php

final class Partida
{
    private ?int $id;
    private int $organizacionId;
    private int $operadorId;
    private string $nombre;
    private int $numero;
    private string $fecha;
    private float $precioCarton;
    private bool $gratis;
    private int $cantidadCartones;
    private int $cartonesConfirmados;
    private string $estado;
    private ?string $youtubeVideoId;
    /** @var PartidaModalidad[] */
    private array $modalidades;
    private ?string $creadoEn;
    private ?string $modificadoEn;

    private const ESTADOS = [
        'pendiente',
        'en_curso',
        'finalizada',
        'cancelada',
    ];

    private const CANTIDAD_CARTONES_MAXIMA = 500;

    /**
     * @param PartidaModalidad[] $modalidades
     */
    public function __construct(
        int $organizacionId,
        int $operadorId,
        string $nombre,
        int $numero,
        string $fecha,
        float $precioCarton,
        int $cantidadCartones,
        array $modalidades,
        ?int $id = null,
        string $estado = 'pendiente',
        int $cartonesConfirmados = 0,
        ?string $youtubeVideoId = null,
        bool $gratis = false,
        ?string $creadoEn = null,
        ?string $modificadoEn = null
    ) {
        if ($id !== null && $id <= 0) {
            throw new DomainException(
                'El ID debe ser mayor que cero.'
            );
        }

        if ($organizacionId <= 0) {
            throw new DomainException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        if ($operadorId <= 0) {
            throw new DomainException(
                'El ID del operador debe ser mayor que cero.'
            );
        }

        if ($numero <= 0) {
            throw new DomainException(
                'El número de partida debe ser mayor que cero.'
            );
        }

        if ($gratis) {
            if ($precioCarton < 0) {
                throw new DomainException(
                    'El precio del cartón no puede ser negativo.'
                );
            }

            $precioCarton = 0.0;
        } elseif ($precioCarton <= 0) {
            throw new DomainException(
                'El precio del cartón debe ser mayor que cero (o marca la partida como gratis).'
            );
        }

        if ($cantidadCartones <= 0) {
            throw new DomainException(
                'La cantidad de cartones debe ser mayor que cero.'
            );
        }

        if ($cantidadCartones > self::CANTIDAD_CARTONES_MAXIMA) {
            throw new DomainException(
                'La cantidad de cartones no puede superar '
                . self::CANTIDAD_CARTONES_MAXIMA . '.'
            );
        }

        if ($cartonesConfirmados < 0) {
            throw new DomainException(
                'Los cartones confirmados no pueden ser negativos.'
            );
        }

        if ($cartonesConfirmados > $cantidadCartones) {
            throw new DomainException(
                'Los cartones confirmados no pueden superar la cantidad de cartones.'
            );
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            throw new DomainException(
                'El estado debe ser pendiente, en_curso, finalizada o cancelada.'
            );
        }

        $this->id = $id;
        $this->organizacionId = $organizacionId;
        $this->operadorId = $operadorId;
        $this->nombre = $this->validarNombre($nombre);
        $this->numero = $numero;
        $this->fecha = $this->validarFecha($fecha);
        $this->precioCarton = round($precioCarton, 2);
        $this->gratis = $gratis;
        $this->cantidadCartones = $cantidadCartones;
        $this->cartonesConfirmados = $cartonesConfirmados;
        $this->estado = $estado;
        $this->youtubeVideoId = YoutubeVideoId::parse($youtubeVideoId);
        $this->modalidades = $this->validarModalidades($modalidades);
        $this->creadoEn = $creadoEn;
        $this->modificadoEn = $modificadoEn;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizacionId(): int
    {
        return $this->organizacionId;
    }

    public function getOperadorId(): int
    {
        return $this->operadorId;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getFecha(): string
    {
        return $this->fecha;
    }

    public function getPrecioCarton(): float
    {
        return $this->precioCarton;
    }

    public function esGratis(): bool
    {
        return $this->gratis;
    }

    public function marcarGratis(bool $gratis, ?float $precioCarton = null): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo una partida pendiente se puede marcar como gratis.'
            );
        }

        if ($gratis) {
            $this->gratis = true;
            $this->precioCarton = 0.0;
            return;
        }

        if ($precioCarton === null || $precioCarton <= 0) {
            throw new DomainException(
                'Para quitar el bingo gratis debes enviar un precioCarton mayor que cero.'
            );
        }

        $this->gratis = false;
        $this->precioCarton = round($precioCarton, 2);
    }

    public function getCantidadCartones(): int
    {
        return $this->cantidadCartones;
    }

    public function getCartonesConfirmados(): int
    {
        return $this->cartonesConfirmados;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function getYoutubeVideoId(): ?string
    {
        return $this->youtubeVideoId;
    }

    /**
     * @return PartidaModalidad[]
     */
    public function getModalidades(): array
    {
        return $this->modalidades;
    }

    public function asignarYoutubeVideoId(?string $youtubeVideoId): void
    {
        $this->youtubeVideoId = YoutubeVideoId::parse($youtubeVideoId);
    }

    public function getCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function getModificadoEn(): ?string
    {
        return $this->modificadoEn;
    }

    public function iniciar(): void
    {
        if ($this->estado !== 'pendiente') {
            throw new DomainException(
                'Solo una partida pendiente puede pasar a en curso.'
            );
        }

        $this->estado = 'en_curso';
    }

    public function finalizar(): void
    {
        if ($this->estado !== 'en_curso') {
            throw new DomainException(
                'Solo una partida en curso puede finalizarse.'
            );
        }

        $this->estado = 'finalizada';
    }

    public function cancelar(): void
    {
        if ($this->estado === 'finalizada') {
            throw new DomainException(
                'Una partida finalizada no se puede cancelar.'
            );
        }

        $this->estado = 'cancelada';
    }

    private function validarNombre(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new DomainException(
                'El nombre no puede estar vacío.'
            );
        }

        if (mb_strlen($nombre) > 150) {
            throw new DomainException(
                'El nombre no puede superar los 150 caracteres.'
            );
        }

        return $nombre;
    }

    private function validarFecha(string $fecha): string
    {
        $fecha = trim($fecha);

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha);

        if (
            $dt === false ||
            $dt->format('Y-m-d H:i:s') !== $fecha
        ) {
            throw new DomainException(
                'La fecha debe tener el formato YYYY-MM-DD HH:MM:SS.'
            );
        }

        return $fecha;
    }

    /**
     * @param PartidaModalidad[] $modalidades
     * @return PartidaModalidad[]
     */
    private function validarModalidades(array $modalidades): array
    {
        if ($modalidades === []) {
            throw new DomainException(
                'Debes definir al menos una modalidad de premio.'
            );
        }

        $vistas = [];

        foreach ($modalidades as $modalidad) {
            if (!$modalidad instanceof PartidaModalidad) {
                throw new DomainException(
                    'Cada modalidad debe ser una PartidaModalidad válida.'
                );
            }

            $codigo = $modalidad->getModalidad();

            if (isset($vistas[$codigo])) {
                throw new DomainException(
                    "La modalidad {$codigo} está duplicada."
                );
            }

            $vistas[$codigo] = true;
        }

        return array_values($modalidades);
    }
}
