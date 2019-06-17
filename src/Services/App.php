<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
    
    class App
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function checkVersion(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$versaoAtualUsuario = $request->getParam("v");
				
				$parametroMinVersao = $this->container->db->get("parametro", ["chave", "valor"], ["chave" => "MIN_VERSAO"]);
				$parametroHtml = $this->container->db->get("parametro", ["chave", "valor"], ["chave" => "HTML_MIN_VERSAO"]);
				$versaoMinimaAceitavel = floatval($parametroMinVersao["valor"]);
				
				$versaoOK = false;
				
				if($versaoAtualUsuario >= $versaoMinimaAceitavel){ //Versão OK
					$versaoOK = true;
				}
				
				$resposta = $response->withJson(["success" => true, "versaoOK" => $versaoOK, "parametroMinVersao" => $parametroMinVersao, "parametroHtml" => $parametroHtml]);
				$this->container->logger->info("GET app/checkVersion");

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $resposta;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
    }
    