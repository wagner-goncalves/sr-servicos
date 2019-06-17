<?php
    namespace MP\Notifications;
    
	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;    
    
    interface ICloudMessage
    {
        public function send(ServerRequestInterface $request, ResponseInterface $response, array $args);
    }