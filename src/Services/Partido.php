<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;

	class Partido{

		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function getPartidos(ServerRequestInterface $request, ResponseInterface $response, array $args){

				$arrPartidos = $this->container->db->select("partidos", "*");
				$newResponse = $response->withJson($arrPartidos);
				$this->container->logger->info("GET partidos");
				return $newResponse ;

		}

		public function getPartido(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$partido = $this->container->db->get("partidos", "*", ["id" => $args["id"]]);
				$newResponse = $response->withJson($partido);
				$this->container->logger->info("GET partido/" . $args["id"]);

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $newResponse;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
		
		public function getPartidoMaisPoliticos(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$objPolitico = new Politico($this->container);
				$partido = $this->container->db->get("partidos", "*", ["id" => $args["id"]]);
				$partido["politicos"] = $objPolitico->getPoliticosPartido($args["id"]);
				$newResponse = $response->withJson($partido);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					

				return $newResponse;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
	}
?>