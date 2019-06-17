<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
	use MP\Utils\Image;
    
    class Preferencia
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function instituicoes(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$dados = $this->container->db->select("instituicao", ["oidInstituicao", "nome", "titulo"], ["flgAtivo" => 1]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		
				
				$response->withJson($dados);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	
		
		public function instituicoesUsuario(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				
				//$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					//["flgAtivo" => 1, "oidUsuario" => $decoded->id]
				//]);
				//$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));
				
				//$sql = "SELECT oidInstituicao, nome, titulo FROM instituicao WHERE oidInstituicao IN (" . $instituicoes . ") AND flgAtivo = 1";
				$sql = "SELECT oidInstituicao, nome, titulo FROM instituicao WHERE flgAtivo = 1";
				$dados = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		

				$response->withJson($dados);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	
		
		public function partidos(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$dados = $this->container->db->select("partido", ["oidPartido", "nome", "sigla"], ["flgAtivo" => 1]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		
				
				$response->withJson($dados);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	

		public function estados(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$dados = $this->container->db->select("estado", ["uf", "nome"], ["flgAtivo" => 1]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		
				
				$response->withJson($dados);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	
		
		public function politicos(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				
				$decoded = $request->getAttribute("token");
				
				$getVars = $request->getParams();	
				$ufs = explode(",", $getVars["uf"]);
				if(count($ufs) == 0) throw new \Exception("Não pudemos processar sua requisição.");	
				for($i = 0; $i < count($ufs); $i++){
					$ufs[$i] = substr($ufs[$i], 0, 2);
				}
				$ufs = implode("','", $ufs);
				
				$sql = "SELECT CASE p.oidInstituicao 
					WHEN 1 THEN 'Dep.'
					WHEN 2 THEN 'Sen.'
					END as cargo, p.oidPolitico, p.uf, p.nome, pa.sigla 
					FROM politico p 
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					inner join usuarioinstituicao ui on p.oidInstituicao = ui.oidInstituicao and ui.oidUsuario = " . $decoded->id . "
					WHERE ui.flgAtivo = 1 and pa.flgAtivo = 1 AND p.uf IN ('" . $ufs . "') ORDER BY p.nome";
				
				$politicos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		
				
				$response->withJson($politicos);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}			

		public function interesses(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$dados = $this->container->db->select("interesse", ["nome"], ["flgAtivo" => 1]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");		
				
				$response->withJson($dados);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}

		public function salvar(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				
				$decoded = $request->getAttribute("token");
				$pref = $request->getParam("pref");
				$id = $request->getParam("id");
				$flgAtivo = $request->getParam("flgAtivo");
				$flgAtivo = $flgAtivo ? "1" : "0";
				$tabela = "";
				$colunaId = "";
				
				switch($pref){
					case "instituicao": $tabela = "usuarioinstituicao"; $colunaId = "oidInstituicao"; break;
					case "partido": $tabela = "usuariopartido"; $colunaId = "oidPartido"; break;
					case "estado": $tabela = "usuarioestado"; $colunaId = "uf"; break;
					case "interesse": $tabela = "usuariointeresse"; $colunaId = "oidInteresse"; break;
					case "politico": $tabela = "usuariopolitico"; $colunaId = "oidPolitico"; break;
				}
				
				if($tabela == "usuarioinstituicao" && $flgAtivo == false){
					$sql = "SELECT * FROM usuarioinstituicao WHERE oidUsuario = " . $decoded->id . " AND flgAtivo = 1";
					$usuarioinstituicao = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
					if(count($usuarioinstituicao) <= 1){ //Não apaga a instituição caso exista só uma
						throw new \Exception("Escolha pelo menos uma casa legislativa.");	
					}
				}
				
				if($tabela == "usuarioestado" && $flgAtivo == false){
					$sql = "SELECT * FROM usuarioestado WHERE oidUsuario = " . $decoded->id . " AND flgAtivo = 1";
					$usuarioestado = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
					if(count($usuarioestado) <= 1){ //Não apaga a instituição caso exista só uma
						throw new \Exception("Escolha pelo menos um Estado.");	
					}
				}
				
				if($flgAtivo == "0"){
					$this->container->db->update($tabela, ["flgAtivo" => 0], ["AND" => [
						"oidUsuario" => $decoded->id, $colunaId => $id
					]]);
				}
				
				if($flgAtivo == "1"){
					$id = $this->container->db->insert($tabela, ["oidUsuario" => $decoded->id, $colunaId => $id, "flgAtivo" => $flgAtivo]);
					if($id == "0") throw new \Exception("Erro ao registrar ação.");	
				}
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar ação.");						
				
				$response->withJson($id);
            }catch(\Exception $e){
				return $response->withJson(["success" => false, "message" => $e->getMessage()]);
            }
		}		
    }
    