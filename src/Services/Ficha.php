<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
    
    class Ficha
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
        public function logCitacao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
                $decoded = $request->getAttribute("token");
				$postVars = $request->getParsedBody();
                
                $id = $this->container->db->insert("logcitacao", [
					"oidUsuario" => $decoded->id,
					"oidCitacao" => $postVars["oidCitacao"]
				]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");
				
                return $response->withJson(["success" => true, "citacao" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }
		
        public function logContasTCU(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
                $decoded = $request->getAttribute("token");
				$postVars = $request->getParsedBody();
                
                $id = $this->container->db->insert("logcontastcu", [
					"oidUsuario" => $decoded->id,
					"oidContasTcu" => $postVars["oidContasTcu"]
				]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");
				
                return $response->withJson(["success" => true, "contastcu" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }		
    }