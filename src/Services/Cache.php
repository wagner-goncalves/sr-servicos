<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;

    class Clear
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function clear(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$this->container->cache->clear();
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  
		}		
		
    }
