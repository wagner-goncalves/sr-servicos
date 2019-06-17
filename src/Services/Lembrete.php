<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;

    class Lembrete
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function votacaoProposicao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      

				$decoded = $request->getAttribute("token");
				$sql = "SELECT p.nome, p.uf, p.oidInstituicao, n.oidTipoNotificacao, pa.sigla, 
					CASE tn.oidTipoNotificacao 
						WHEN 3 THEN 'SIM'
						WHEN 4 THEN 'NÃO'
						ELSE tn.nome
					END AS voto 
					FROM notificacao n
					INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
					INNER JOIN politico p ON p.oidPolitico = n.oidPolitico
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN tiponotificacao tn ON tn.oidTipoNotificacao = n.oidTipoNotificacao
					WHERE np.oidProposicao = " . intval($args["id"]) . " ORDER BY p.nome";
				$arrVotacao = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					

				return $response->withJson(["success" => true, "votacao" => $arrVotacao]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  
		}		
		
		public function curtirProposicao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$decoded = $request->getAttribute("token");
				$curtir = $request->getParam("curtir");
				$tipoNotificacao = $request->getParam("tipoNotificacao");
				$oidProposicao = intval($args["id"]);
				
				$usuario = $this->container->db->update("usuarionotificacao", 
					["flgCurtir" => intval($curtir)], 
					["AND" => [
						"oidUsuario" => $decoded->id, 
						"oidProposicao" => intval($oidProposicao), 
						"oidTipoNotificacao" => intval($tipoNotificacao)
						]
					]
				);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					

				$notificacoes = [];
				
				return $response->withJson(["success" => true, "notificacoes" => $notificacoes]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  
		}			
		
		public function getLembrete(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$decoded = $request->getAttribute("token");
				$sql = "SELECT l.oidLembrete, l.oidProposicao, 
					CASE WHEN p.explicacao IS NOT NULL THEN p.explicacao ELSE l.titulo END  AS titulo, 
					concat(p.tipo, ' ', p.numero, '/', p.ano) as chamada, 
					p.*, i.nome AS instituicao FROM lembrete l
					INNER JOIN proposicao p ON p.oidProposicao = l.oidProposicao
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					WHERE l.oidLembrete = " . intval($args["id"]);
					
				$arrLembretes = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$lembrete = count($arrLembretes) > 0 ? $arrLembretes[0] : null;

				$this->container->db->insert("statsviewlembrete", ["oidUsuario" => intval($decoded->id), "oidLembrete" => intval($args["id"])]); 
				return $response->withJson(["success" => true, "lembrete" => $lembrete]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  
		}		

		public function cria(ServerRequestInterface $request, ResponseInterface $response, array $args){
           try{

				$oidProposicao = intval($args["id"]);
				
				$proposicao = $this->container->db->get("proposicao", ["oidProposicao", "tema", "resumo", "objeto"], ["oidProposicao" => $oidProposicao]);
				if(!$proposicao) throw new \Exception("Erro ao recuperar proposição.");
				$lembrete = $this->container->db->get("lembrete", ["oidLembrete"], ["oidProposicao" => $oidProposicao]);
				if(!$lembrete){
					$lembrete = [
                        "oidProposicao" => $proposicao["oidProposicao"], 
                        "titulo" => $proposicao["resumo"], 
                        "chamada" => $proposicao["objeto"], 
                        "dataHoraFim" => "2018-05-31 14:34:51", 
                        "flgAtivo" => "0"
                    ];
					$id = $this->container->db->insert("lembrete", $lembrete); 
				}else{
					$id = $lembrete["oidLembrete"];
				}
                
                return $response->withJson(["success" => true, "id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            } 
		}
			

        //Lista de lembretes
		public function listar(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$page = $request->getParam("page");
				$decoded = $request->getAttribute("token");
				$sql = "SELECT l.oidLembrete, l.oidProposicao, l.titulo, l.chamada, i.nome as instituicao 
					FROM lembrete l
					INNER JOIN proposicao p ON p.oidProposicao = l.oidProposicao
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					WHERE l.flgAtivo = 1 
					AND CURRENT_TIMESTAMP BETWEEN l.dataHoraInicio AND l.dataHoraFim
					ORDER BY l.oidLembrete DESC
					LIMIT " . ((intval($page) - 1) * 10) . ", 10";

				$arrLembretes = $this->container->db->cachedQuery($sql, $this->container, 5);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$this->container->db->insert("statslistlembrete", ["oidUsuario" => intval($decoded->id), "page" => intval($page)]); 			
				return $response->withJson(["success" => true, "lembretes" => $arrLembretes]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  				
		}

		public function ultimo(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$decoded = $request->getAttribute("token");

				$sql = "SELECT l.oidLembrete, l.titulo, l.oidProposicao, l.chamada FROM lembrete l
					-- LEFT JOIN usuariolembrete ul ON ul.oidLembrete = l.oidLembrete
					WHERE l.flgAtivo = 1 
					AND CURRENT_TIMESTAMP BETWEEN l.dataHoraInicio AND l.dataHoraFim
					ORDER BY RAND()
					LIMIT 0, 1";

				$arrLembretes = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$retorno = ["success" => true, "lembrete" => null];
				
				if(count($arrLembretes) > 0){ 
					$retorno = ["success" => true, "lembrete" => $arrLembretes[0]];
					$this->container->db->insert("statsultimolembrete", ["oidUsuario" => intval($decoded->id), "oidLembrete" => $arrLembretes[0]["oidLembrete"]]); 
				}
				
				return $response->withJson($retorno);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  
		}
    }
