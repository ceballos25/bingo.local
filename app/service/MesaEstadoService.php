<?php

/**
 * Snapshot de mesa para el front (bloqueo de botones Bingo).
 */
final class MesaEstadoService
{
    public function __construct(
        private ReclamoRepository $reclamoRepository
    ) {
    }

    public function snapshot(int $partidaId): array
    {
        $pendiente = $this->reclamoRepository->buscarPendientePorPartida(
            $partidaId
        );
        $cerradas = $this->reclamoRepository->listarModalidadesAprobadas(
            $partidaId
        );

        return [
            'botonesBloqueados' => $pendiente !== null,
            'reclamoPendienteId' => $pendiente['id'] ?? null,
            'modalidadesCerradas' => $cerradas,
        ];
    }
}
