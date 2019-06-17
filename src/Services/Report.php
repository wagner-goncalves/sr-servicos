<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
    
    class Report
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
        public function lastUser(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      
				$sql = "SELECT u.nome, u.email, u.dataHoraCriacao, g.as, g.city, g.region, g.country, g.lat, g.lon, g.query, g.zip 
					FROM usuario u
					LEFT JOIN geoinfo g ON g.oidUsuario = u.oidUsuario
					ORDER BY u.oidUsuario DESC LIMIT 1";
				$arrUsuario = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				$usuario = [];
				if(count($arrUsuario) > 0) $usuario = $arrUsuario[0];
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao recuperar dados");	
				
                return $response->withJson(["success" => true, "usuario" => $usuario]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }
		
        public function appUseLocation(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{      

				$dataInicio = $request->getParam("dataInicio");
				$dataFim = $request->getParam("dataFim");
				
				$sql = "SELECT latitude, longitude 
				FROM location 
				WHERE 1 = 1 ";
				
				if($dataInicio != "" && $dataFim != "") $sql .=	"AND dataHora BETWEEN '" . $dataInicio . "' AND '" . $dataFim . "'";

				$dados = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao recuperar dados.");	
				
                return $response->withJson(["success" => true, "dados" => $dados]);

            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }

        public function topLike(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{    

				$uf = $request->getParam("uf");
				$partido = $request->getParam("partido");
				$dataInicio = $request->getParam("dataInicio");
				$dataFim = $request->getParam("dataFim");
				
				$sql = "SELECT CONCAT(p.nome, ' ', pa.sigla, '-', p.uf) AS nome, COUNT(un.flgCurtir) AS curtidas FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN notificacao n ON n.oidPolitico = p.oidPolitico
					INNER JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao
					WHERE un.flgCurtir = 1 ";
				
				if($uf != "") $sql .=	"AND p.uf = '" . $uf . "' ";
				if($partido != "") $sql .=	"AND pa.oidPartido = " . $partido;
				if($dataInicio != "" && $dataFim != "") $sql .=	"AND un.dataHoraCurtir BETWEEN '" . $dataInicio . "' AND '" . $dataFim . "'";
				
				$sql .=	"
					GROUP BY p.oidPolitico
					ORDER BY curtidas DESC
					LIMIT 0, 10";
				
				$dados = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao recuperar dados.");	
				
				$labels = array_column($dados, "nome");
				$data = array_column($dados, "curtidas");
				
                return $response->withJson(["success" => true, "labels" => $labels, "data" => $data]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }
		
		public function topDislike(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$this->topLike($request, $response, $args);
		}

        public function gostei(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{     
				$decoded = $request->getAttribute("token");
				$tipo = $request->getParam("tipo");
				$uf = $request->getParam("uf");
				$partido = $request->getParam("partido");
				$dataInicio = $request->getParam("dataInicio");
				$dataFim = $request->getParam("dataFim");
				
				$sql = "SELECT CONCAT(p.nome, ' ', pa.sigla, '-', p.uf) AS nome, COUNT(un.flgCurtir) AS curtidas FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN notificacao n ON n.oidPolitico = p.oidPolitico
					INNER JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao
					WHERE un.flgCurtir = " . ($tipo == "naogostei" ? "0" : "1") . " 	";
				
				if($uf != "") $sql .=	"AND p.uf = '" . $uf . "' ";
				if($partido != "") $sql .=	"AND pa.oidPartido = " . $partido;
				if($dataInicio != "" && $dataFim != "") $sql .=	"AND un.dataHoraCurtir BETWEEN '" . $dataInicio . "' AND '" . $dataFim . "'";
				
				$sql .=	"
					GROUP BY p.oidPolitico
					ORDER BY curtidas DESC
					LIMIT 0, 10";
				
				$dados = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao recuperar dados.");	
				
				$labels = array_column($dados, "nome");
				$data = array_column($dados, "curtidas");
				$soma = array_sum($data);
				
				for($i = 0; $i < count($data); $i++){
					$data[$i] = round($data[$i] / $soma * 100, 2);
				}		
				
				$id = $this->container->db->insert("statsestatisticas", [
					"oidUsuario" => $decoded->id, 
					"tipo" => $tipo, 
					"uf" => $uf == "" ? null : $uf, 
					"partido" => $partido == "" ? null : $partido, 
					"dataInicio" => $dataInicio == "" ? null : $dataInicio, 
					"dataFim" => $dataFim == "" ? null : $dataFim 
				]);
				
                return $response->withJson(["success" => true, "labels" => $labels, "data" => $data]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }		
		
    }
    