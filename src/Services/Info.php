<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
    
    class Info
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function getParametro(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$parametro = $this->container->db->get("parametro", ["chave", "valor"], ["chave" => $args["chave"]]);
				$resposta = $response->withJson(["success" => true, "parametro" => $parametro]);
				$this->container->logger->info("GET parametro/" . $args["chave"]);

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $resposta;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
		
		public function getIdeologia(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$ideologia = $this->container->db->get("ideologia", "*", ["oidIdeologia" => $args["id"]]);
				$resposta = $response->withJson(["success" => true, "ideologia" => $ideologia]);
				$this->container->logger->info("GET ideologia/" . $args["id"]);

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $resposta;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
		
		public function checkConnection(ServerRequestInterface $request, ResponseInterface $response, array $args){			
            try{
				$termoUso = $this->container->db->get("termouso", ["oidTermoUso"]);
				if($termoUso) $response->withJson([
						"success" => true
					]);
				else throw new \Exception("Não foi possível obter os termos de uso.");
            }catch(\Exception $e){
				return $response->withStatus(500)->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);				
            }
		}		
		
        public function notificacaoStats(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
                $decoded = $request->getAttribute("token");

                $stats = $this->container->db->get("notificacaostats", ["quantidade"], ["oidUsuario" => $decoded->id]);
                if($id == "0") throw new \Exception("Erro ao recuperar dados.");
				
                return $response->withJson(["success" => true, "quantidade" => $stats["quantidade"]]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }
		
        public function usuario(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
                $decoded = $request->getAttribute("token");
                return $response->withJson(["success" => true, "id" => $decoded->id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }
		
        public function location(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
                $decoded = $request->getAttribute("token");
				$postVars = $request->getParsedBody();
				$postVars["oidUsuario"] = $decoded->id;
                
                $id = $this->container->db->insert("location", $postVars);
                if($id == "0") throw new \Exception("Erro ao inserir localização.");
				
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }

        public function geoInfo(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
				$postVars = $request->getParsedBody();
				$postVars["oidUsuario"] = $decoded->id;
                $postVars["query"] = $_SERVER["REMOTE_ADDR"];
                /*
                    Ao inserir na geoinfo é executada trigger insertGeoInfo para setar estado do usuário
                    Ao setar UF pela primeira vez na tabela usuarioestado é executada trigger insertUsuarioEstado para popular notificações em usuarionotificacao
                */
                $id = $this->container->db->insert("geoinfo", $postVars);
                if($id == "0") throw new \Exception("Erro ao inserir localização.");
				
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }

		
    }
    