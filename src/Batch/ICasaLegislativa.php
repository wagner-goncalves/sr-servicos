<?php
    namespace MP\Batch;
    
	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;    
    
    interface ICasaLegislativa
    {
        public function processaNotificacoes(ServerRequestInterface $request, ResponseInterface $response, array $args);
    }