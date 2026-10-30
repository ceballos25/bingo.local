<?php

final class BalotaService
{
    public function __construct(
        private BalotaRepository $balotaRepository,
        private PartidaRepository $partidaRepository,
        private UsuarioRepository $usuarioRepository,
        private ReclamoRepository $reclamoRepository,
        private PartidaRealtime $partidaRealtime,
        private MesaEstadoService $mesaEstadoService
    ) {
    }

    public function sacar(int $partidaId, int $operadorId): array
    {
        $partida = $this->asegurarPartidaEnCurso($partidaId, $operadorId);

        if ($this->reclamoRepository->existePendientePorPartida($partidaId)) {
            throw new DomainException(
                'Hay un reclamo pendiente: no se pueden sacar balotas.'
            );
        }

        $sacadas = $this->balotaRepository->numerosSacados($partidaId);

        if (count($sacadas) >= 75) {
            throw new DomainException(
                'Ya se sacaron las 75 balotas.'
            );
        }

        $disponibles = array_values(array_diff(range(1, 75), $sacadas));
        $numero = $disponibles[random_int(0, count($disponibles) - 1)];
        $balota = new Balota($numero);
        $orden = count($sacadas) + 1;

        $id = $this->balotaRepository->guardar($partidaId, $balota, $orden);

        $resultado = [
            'id' => $id,
            'partidaId' => $partidaId,
            'numero' => $balota->getNumero(),
            'letra' => $balota->getLetra(),
            'etiqueta' => $balota->etiqueta(),
            'orden' => $orden,
            'restantes' => 75 - $orden,
            'mesa' => $this->mesaEstadoService->snapshot($partidaId),
            'partida' => [
                'id' => (int) $partida['id'],
                'nombre' => $partida['nombre'],
                'estado' => $partida['estado'],
            ],
        ];

        $this->partidaRealtime->publicar(
            $partidaId,
            'balota',
            [
                'mesa' => $resultado['mesa'],
                'balota' => [
                    'id' => $id,
                    'numero' => $resultado['numero'],
                    'letra' => $resultado['letra'],
                    'etiqueta' => $resultado['etiqueta'],
                    'orden' => $orden,
                    'restantes' => $resultado['restantes'],
                ],
            ]
        );

        return $resultado;
    }

    public function listarPorPartida(int $partidaId): array
    {
        if ($partidaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        if ($this->partidaRepository->buscarPorId($partidaId) === null) {
            throw new DomainException(
                'La partida no existe.'
            );
        }

        $balotas = $this->balotaRepository->listarPorPartida($partidaId);

        return [
            'partidaId' => $partidaId,
            'total' => count($balotas),
            'restantes' => 75 - count($balotas),
            'mesa' => $this->mesaEstadoService->snapshot($partidaId),
            'balotas' => $balotas,
        ];
    }

    private function asegurarPartidaEnCurso(
        int $partidaId,
        int $operadorId
    ): array {
        if ($partidaId <= 0 || $operadorId <= 0) {
            throw new InvalidArgumentException(
                'partidaId y operadorId deben ser mayores que cero.'
            );
        }

        $partida = $this->partidaRepository->buscarPorId($partidaId);

        if ($partida === null) {
            throw new DomainException(
                'La partida no existe.'
            );
        }

        $this->asegurarOperadorValido(
            $operadorId,
            (int) $partida['organizacionId']
        );

        if ($partida['estado'] !== 'en_curso') {
            throw new DomainException(
                'Solo se pueden sacar balotas de una partida en curso.'
            );
        }

        return $partida;
    }

    private function asegurarOperadorValido(
        int $operadorId,
        int $organizacionId
    ): void {
        $operador = $this->usuarioRepository->buscarPorId($operadorId);

        if ($operador === null) {
            throw new DomainException(
                'El operador no existe.'
            );
        }

        if ((int) $operador['organizacionId'] !== $organizacionId) {
            throw new DomainException(
                'El operador no pertenece a la organización.'
            );
        }

        if (($operador['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El operador está suspendido.'
            );
        }

        $rol = $operador['rol'] ?? '';

        if ($rol !== 'administrador' && $rol !== 'operador') {
            throw new DomainException(
                'Solo un administrador u operador puede sacar balotas.'
            );
        }
    }
}
