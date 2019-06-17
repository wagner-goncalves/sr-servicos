<?php
    namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	
	class Search{
		protected $container;
		public function __construct($container){
			$this->container = $container;
		}
		
		public function info(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				
                $sql = "SELECT COUNT(oidPolitico) as total FROM politico";
				$contaPoliticos = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$arrInstituicao = $this->container->db->select("instituicao", ["nome"], ["flgAtivo" => 1]);
				$instituicoes = [];
				foreach($arrInstituicao as $instituicao){
					$instituicoes[] = $instituicao["nome"];
				}
				return $response->withJson(["contaPoliticos" => $contaPoliticos[0]["total"], "instituicoes" => implode(", ", $instituicoes)]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }		
		}
		
		public function politicos(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				
				$page = $request->getParam("page");
				$key = $request->getParam("key");
				$sigla = $request->getParam("sigla");
				$oidUsuario = $decoded->id;          

				$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => $oidUsuario]
				]);
				
				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));
				
				$sql = "SELECT p.oidPolitico, i.abreviatura, p.nome, p.uf, p.arquivoFoto, p.arquivoFotoLocal, p.oidInstituicao, pa.sigla, i.titulo,
					(SELECT COUNT(up1.oidPolitico) FROM usuariopolitico up1 WHERE up1.oidUsuario = " . $decoded->id . " AND up1.oidPolitico = p.oidPolitico AND up1.flgAtivo = '1') AS seguindo
					FROM politico p
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					WHERE p.nome LIKE '%" . $key . "%' OR i.nome LIKE '%" . $key . "%' OR pa.nome LIKE '%" . $key . "%' OR pa.sigla LIKE '%" . $key . "%' OR p.uf LIKE '%" . $key . "%'
					AND pa.flgAtivo = 1 ";
				
				if($sigla !== ""){
					$sql .= " AND pa.sigla = '" . $sigla . "' ";
				}	
				$sql .= "
					ORDER BY p.nome
					LIMIT " . ((intval($page) - 1) * 10) . ", 10";

				$arrPoliticos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $response->withJson($arrPoliticos);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }		
		} 

		public function partidos(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				
				$page = $request->getParam("page");
				$key = $request->getParam("key");
				$sql = "SELECT p.*,
				(SELECT COUNT(p1.oidPolitico) FROM politico p1 WHERE p1.oidPartido = p.oidPartido) AS politicos 
				FROM partido p
					WHERE (p.nome LIKE '%" . $key . "%' OR p.sigla LIKE '%" . $key . "%')
					AND p.flgAtivo = 1
					ORDER BY p.nome
					LIMIT " . ((intval($page) - 1) * 10) . ", 10";
				
				$arrPoliticos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $response->withJson($arrPoliticos);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }		
		} 		
		
        public function logBuscaPolitico(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{     
				$decoded = $request->getAttribute("token");
                $postVars = $request->getParsedBody();
				$postVars["oidUsuario"] = $decoded->id;
                $id = $this->container->db->insert("logbuscapolitico", $postVars);
                if($id == "0") throw new \Exception("Erro ao inserir log.");
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }		
		
	}
?>