<?php

final class ResultadoInicioPse
{
    public function __construct(
        private string $referenciaExterna,
        private string $urlPago,
        private string $estadoRemoto
    ) {
    }

    public function getReferenciaExterna(): string
    {
        return $this->referenciaExterna;
    }

    public function getUrlPago(): string
    {
        return $this->urlPago;
    }

    public function getEstadoRemoto(): string
    {
        return $this->estadoRemoto;
    }
}
