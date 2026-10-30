<?php

final class PartidaResponseDTO
{
    public function __construct(
        private int $id,
        private int $organizacionId,
        private int $operadorId,
        private string $nombre,
        private int $numero,
        private string $fecha,
        private float $precioCarton,
        private bool $gratis,
        private int $cantidadCartones,
        private int $cartonesConfirmados,
        private string $estado,
        private ?string $youtubeVideoId,
        private array $modalidades,
        private string $creadoEn,
        private string $modificadoEn
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizacionId' => $this->organizacionId,
            'operadorId' => $this->operadorId,
            'nombre' => $this->nombre,
            'numero' => $this->numero,
            'fecha' => $this->fecha,
            'precioCarton' => $this->precioCarton,
            'gratis' => $this->gratis,
            'cantidadCartones' => $this->cantidadCartones,
            'cartonesConfirmados' => $this->cartonesConfirmados,
            'estado' => $this->estado,
            'youtubeVideoId' => $this->youtubeVideoId,
            'youtubeEmbedUrl' => YoutubeVideoId::embedUrl($this->youtubeVideoId),
            'modalidades' => $this->modalidades,
            'creadoEn' => $this->creadoEn,
            'modificadoEn' => $this->modificadoEn,
        ];
    }
}
