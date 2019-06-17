<?php
    namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	
	class Politico{
		protected $container;
		public function __construct($container){
			$this->container = $container;
		}
		
		public function getPoliticos(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$arrPoliticos = $this->container->db->select("politicos", "*");
			$newResponse = $response->withJson($arrPoliticos);
			return $newResponse ;
		}	
		
		public function getRating(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				$notapolitico = $this->container->db->get("notapolitico", ["nota"], ["AND" => [
						"oidUsuario" => $decoded->id, 
						"oidPolitico" => intval($args["id"]),
						"flgAtivo" => "1"
					]
				]);
				
				$response->withJson(["success" => true, "notapolitico" => $notapolitico]);

            }catch(\Exception $e){
				print_r($e);
                return $response->withStatus(500)->withJson(["success" => false]);
            }
		}
		
		public function setRating(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				$nota = intval($request->getParam("nota"));
				$nota = $nota > 5 || $nota < 0 ? 0 : $nota;
				
				$this->container->db->update("notapolitico", ["flgAtivo" => "0"], [
					"AND" => [
						"oidUsuario" => intval($decoded->id), 
						"oidPolitico" => intval($args["id"])
					]
				]);
				
				$this->container->db->insert("notapolitico", [
					"oidUsuario" => intval($decoded->id), 
					"oidPolitico" => intval($args["id"]),
					"nota" => $nota,
					"flgAtivo" => "1"
				]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");	
				
				$response->withJson(["success" => true, "nota" => $nota]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false]);
            }
		}		
				
		
		public function getPolitico(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$sql = "SELECT p.*, i.oidInstituicao, i.abreviatura AS instituicao, pa.sigla,
					(SELECT COUNT(up1.oidPolitico) FROM usuariopolitico up1 WHERE up1.oidUsuario = " . $decoded->id . " AND up1.oidPolitico = p.oidPolitico AND up1.flgAtivo = 1) AS seguindo,
					(SELECT COUNT(up1.oidPolitico) FROM usuariopolitico up1 WHERE up1.oidPolitico = p.oidPolitico AND up1.flgAtivo = 1) AS seguidores,
					(SELECT COUNT(n1.oidPolitico) FROM notificacao n1 WHERE n1.flgAtivo = 1 AND n1.oidPolitico = p.oidPolitico) AS notificacoes,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 1 AND un1.flgAtivo = 1 AND un1.oidUsuario = " . $decoded->id . " AND n1.oidPolitico = p.oidPolitico
						) AS curtidas,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 0 AND un1.flgAtivo = 1 AND un1.oidUsuario = " . $decoded->id . " AND n1.oidPolitico = p.oidPolitico
						) AS descurtidas,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 1 AND un1.flgAtivo = 1 AND n1.oidPolitico = p.oidPolitico
						) AS curtidasTotais,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 0 AND un1.flgAtivo = 1 AND n1.oidPolitico = p.oidPolitico
						) AS descurtidasTotais, rp.*
					FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
					WHERE p.oidPolitico = " . intval($args["id"]);		

				$arrPoliticos = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$this->container->db->insert("logpolitico", [
					"oidUsuario" => intval($decoded->id), 
					"oidPolitico" => intval($args["id"]),
					"oidInstituicao" => $arrPoliticos[0]["oidInstituicao"]
				]);             
				
				return $response->withJson(count($arrPoliticos) > 0 ? $arrPoliticos[0] : false);
            }catch(\Exception $e){
                print_r($e);
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
		
		
		public function getPoliticoSlim(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$sql = "SELECT p.*, i.oidInstituicao, i.abreviatura AS instituicao, pa.sigla,
					null AS seguindo,
					null AS seguidores,
					null AS notificacoes,
					null AS curtidas,
					null AS descurtidas,
					null AS curtidasTotais,
					null AS descurtidasTotais,
					rp.evolucaoBens,
					rp.evolucaoReceitas,
					rp.evolucaoDespesas,
					rp.valorVoto,
					rp.quantidadeCitacoes,
					rp.percentualPresenca,
					rp.quantidadeTcu,
					rp.quantidadeMateriasVotadas,
					rp.percentualVotaComoVoce
					FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
					WHERE p.oidPolitico = " . intval($args["id"]);		

				$arrPoliticos = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$sql = "SELECT votaIgual, votaDiferente FROM usuariopoliticoresumo WHERE oidUsuario = " . $decoded->id . " AND oidPolitico = " . intval($args["id"]);
				$arrVotos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

				$this->container->db->insert("logpolitico", [
					"oidUsuario" => intval($decoded->id), 
					"oidPolitico" => intval($args["id"]),
					"oidInstituicao" => $arrPoliticos[0]["oidInstituicao"]
				]);             
				
				$resultado = false;
				if(count($arrPoliticos) > 0){
					$resultado = $arrPoliticos[0];
					if(count($arrVotos) > 0){
						$resultado["votaIgual"] = $arrVotos[0]["votaIgual"];
						$resultado["votaDiferente"] = $arrVotos[0]["votaDiferente"];
						$resultado["totalVotos"] = $arrVotos[0]["votaIgual"] + $arrVotos[0]["votaDiferente"];
					}
				}


				return $response->withJson(count($arrPoliticos) > 0 ? $resultado : false);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}		
		
		public function contadores(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$sql = "SELECT 
					(SELECT COUNT(up1.oidPolitico) FROM usuariopolitico up1 WHERE up1.oidUsuario = " . $decoded->id . " AND up1.oidPolitico = " . intval($args["id"]) . " AND up1.flgAtivo = 1) AS seguindo,
					(SELECT COUNT(up1.oidPolitico) FROM usuariopolitico up1 WHERE up1.oidPolitico = " . intval($args["id"]) . " AND up1.flgAtivo = 1) AS seguidores,
					(SELECT COUNT(n1.oidPolitico) FROM notificacao n1 WHERE n1.flgAtivo = 1 AND n1.oidPolitico = " . intval($args["id"]) . ") AS notificacoes,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 1 AND un1.flgAtivo = 1 AND un1.oidUsuario = " . $decoded->id . " AND n1.oidPolitico = " . intval($args["id"]) . "
						) AS curtidas,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 0 AND un1.flgAtivo = 1 AND un1.oidUsuario = " . $decoded->id . " AND n1.oidPolitico = " . intval($args["id"]) . "
						) AS descurtidas,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 1 AND un1.flgAtivo = 1 AND n1.oidPolitico = " . intval($args["id"]) . "
						) AS curtidasTotais,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 0 AND un1.flgAtivo = 1 AND n1.oidPolitico = " . intval($args["id"]) . "
						) AS descurtidasTotais ";

				$arrPoliticos = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $response->withJson(count($arrPoliticos) > 0 ? $arrPoliticos[0] : false);
            }catch(\Exception $e){
                print_r($e);
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}		
		
		public function getFicha(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$sql = "SELECT rd.oidPolitico, 
					rd.anoEleicao, 
					rd.totalBens AS totalBens, 
					rd.totalDespesas AS totalDespesas, 
					rd.totalReceitas AS totalReceitas, 
					rd.cargo,
					re.votos,
					(rd.totalDespesas / re.votos) AS valorVoto,
					CASE 
						WHEN re.situacao = 'QP' THEN 'Eleito por Quociente Parlamentar' 
						ELSE re.situacao 
					END as situacao
					FROM resumodeclaracoes rd
					INNER JOIN politico p ON p.oidPolitico = rd.oidPolitico
					LEFT JOIN resultadoeleicao re ON re.oidPolitico = rd.oidPolitico AND re.anoEleicao = rd.anoEleicao AND re.uf = p.uf
					WHERE rd.flgAtivo = 1 AND rd.oidPolitico = " . intval($args["id"]) . " 
					-- ORDER BY rd.anoEleicao ASC
					
					UNION
					
					SELECT re.oidPolitico, 
					re.anoEleicao, 
					rd.totalBens AS totalBens, 
					rd.totalDespesas AS totalDespesas, 
					rd.totalReceitas AS totalReceitas, 
					re.cargo,
					re.votos,
					(rd.totalDespesas / re.votos) AS valorVoto,
					CASE 
						WHEN re.situacao = 'QP' THEN 'Eleito por Quociente Parlamentar' 
						ELSE re.situacao 
					END AS situacao

					FROM resultadoeleicao re -- resumodeclaracoes rd
					INNER JOIN politico p ON p.oidPolitico = re.oidPolitico
					LEFT JOIN resumodeclaracoes rd ON re.oidPolitico = rd.oidPolitico AND re.anoEleicao = rd.anoEleicao -- AND rd.uf = p.uf
					WHERE re.oidPolitico = " . intval($args["id"]) . " AND rd.oidPolitico IS NULL
					ORDER BY anoEleicao ASC ";		

				$arrFicha = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$sql = "SELECT p.oidPolitico, p.nome, p.oidInstituicao, p.uf, pa.sigla, p.arquivoFotoLocal, i.titulo
					FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					WHERE p.oidPolitico = " . intval($args["id"]);		

				$politico = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
                $sql = "SELECT 
                    n.oidNotificacao, CONCAT(n.siglaTipo, ' ', n.numero, '/', n.ano) AS proposicao, 
                    pr.ementa, CASE tn.oidTipoNotificacao 
                        WHEN 3 THEN 'SIM'
                        WHEN 4 THEN 'NÃO'
                        WHEN 6 THEN 'ABSTENÇÃO'
                        WHEN 7 THEN 'OBSTRUÇÃO'
                        WHEN 8 THEN 'NÃO VOTOU'
                        WHEN 9 THEN 'VOTO SECRETO'
                    END AS voto, 
					CASE up.oidTipoNotificacao 
                        WHEN 3 THEN 'SIM'
                        WHEN 4 THEN 'NÃO'
                        WHEN 6 THEN 'ABSTENÇÃO'
                        WHEN 7 THEN 'OBSTRUÇÃO'
                        WHEN 8 THEN 'NÃO VOTOU'
                        WHEN 9 THEN 'VOTO SECRETO'
                        ELSE '-'
                    END AS seuvoto,    
					n.dataHoraEvento, pr.explicacao 
                    FROM notificacao n
                    INNER JOIN tiponotificacao tn ON tn.oidTipoNotificacao = n.oidTipoNotificacao
                    INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
                    INNER JOIN proposicao pr ON pr.oidProposicao = np.oidProposicao
					LEFT JOIN usuarioproposicao up ON up.oidProposicao = pr.oidProposicao AND up.oidUsuario = " . $decoded->id .  "
                    WHERE n.oidPolitico = " . intval($args["id"]) . "
                    AND tn.oidTipoNotificacao IN (3,4,6,7,8,9)
                    AND n.flgAtivo = 1 AND pr.flgAtivo = 1 AND pr.explicacao IS NOT NULL
                    ORDER BY n.dataHoraEvento DESC";
                
                $votacao = $this->container->db->cachedQuery($sql, $this->container, 3600);
				
                $sql = "SELECT tn.oidTipoNotificacao, CASE tn.oidTipoNotificacao 
					WHEN 1 THEN 'Presença'
					WHEN 2 THEN 'Ausência'
					WHEN 5 THEN 'Ausência Justificada'
					END AS tipo, COUNT(tn.oidTipoNotificacao) AS total 
					FROM notificacao n
					INNER JOIN tiponotificacao tn ON tn.oidTipoNotificacao = n.oidTipoNotificacao
					WHERE n.oidPolitico = " . intval($args["id"]) . "
					AND tn.oidTipoNotificacao IN (1,2,5)
					GROUP BY tn.oidTipoNotificacao";
                
                $presenca = $this->container->db->cachedQuery($sql, $this->container, 3600);						

				$sql = "SELECT c.oidCitacao, c.operacao, c.descricao, c.linksMaisInfo FROM citacao c
					WHERE c.oidPolitico = " . intval($args["id"]) . "
					AND c.flgAtivo = 1 ORDER BY c.oidCitacao DESC";
					
                $citacao = $this->container->db->cachedQuery($sql, $this->container, 3600);		

				$sql = "SELECT oidContasTcu, cod, inidoneoLicitar, dataLancamento, linkInteiroTeor
					FROM contastcu WHERE oidPolitico =  " . intval($args["id"]) . "
					AND flgAtivo = 1";
					
                $contastcu = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);					
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");						
				
				$this->container->db->insert("logpolitico", [
					"oidUsuario" => intval($decoded->id), 
					"oidPolitico" => intval($args["id"]),
					"oidInstituicao" => $politico[0]["oidInstituicao"],
					"flgFicha" => 1
				]);             

				return $response->withJson([
					"success" => true, 
					"ficha" => $arrFicha, 
					"politico" => $politico[0], 
					"votacao" => $votacao,
					"presenca" => $presenca,
					"citacao" => $citacao,
					"contastcu" => $contastcu
				]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
		
		public function getPoliticosPartido($idPartido){
			$arrPoliticos = $this->container->db->select("politicos", "*", ["partido" => $idPartido]);
			return $arrPoliticos;
		}
        
        //Recupera políticos do usuário
		public function friends(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				$page = $request->getParam("page");
				$key = $request->getParam("key");
				
				$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => intval($decoded->id)]
				]);
				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));

				
				$sql = "SELECT DISTINCT p.*, i.abreviatura, pa.sigla, rp.* FROM politico p
					INNER JOIN usuariopolitico up ON up.oidPolitico = p.oidPolitico
					INNER JOIN partido pa ON p.oidPartido = pa.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
					WHERE p.oidInstituicao IN (" . $instituicoes . ") AND up.oidUsuario = " . intval($decoded->id) . "
					AND up.flgAtivo = 1 ";
					//if(strlen($key) > 3) 
					$sql .= " AND p.nome LIKE '%" . $key . "%' ";
					$sql .= "
					ORDER BY p.nome
					LIMIT " . ((intval($page) - 1) * 10) . ", 10";
				
				$arrPoliticos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$newResponse = $response->withJson($arrPoliticos);
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}		
		
        //Recupera políticos do usuário com contador de curtidas
		public function friendsAdvisor(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				
				$page = $request->getParam("page");
				$key = $request->getParam("key");
				$order = $request->getParam("order");
				$instituicao = $request->getParam("instituicao");
				
				$politicianType = $request->getParam("politicianType");
				
				$arrInstituicao = $this->container->db->select("instituicao", ["oidInstituicao"], ["flgAtivo" => 1]);

				$arrEstados = $this->container->db->select("usuarioestado", ["uf"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => $decoded->id]
				]);

				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));
				$estados = implode("','", array_map(function($el){ return $el['uf']; }, $arrEstados));
				
					$sql = "SELECT p.oidPolitico, 
						CONCAT(CASE p.oidInstituicao 
								WHEN 1 THEN 'Dep.'
								WHEN 2 THEN 'Sen.'
								WHEN 3 THEN 'Dep.'
							END, ' ', p.nome) AS nome,
						p.arquivoFoto, p.arquivoFotoLocal, pa.sigla, p.uf, i.abreviatura, pa.sigla,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 1 AND un1.flgAtivo = 1 AND un1.oidUsuario = up.oidUsuario AND n1.oidPolitico = p.oidPolitico
						) AS curtidas,
						(
							SELECT COUNT(un1.flgCurtir)
							FROM usuarionotificacao un1
							INNER JOIN notificacao n1 ON n1.oidNotificacao = un1.oidNotificacao
							WHERE n1.flgAtivo = 1 AND un1.flgCurtir = 0 AND un1.flgAtivo = 1 AND un1.oidUsuario = up.oidUsuario AND n1.oidPolitico = p.oidPolitico
						) AS descurtidas, 
						rp.evolucaoBens,
						rp.evolucaoReceitas,
						rp.evolucaoDespesas,
						rp.valorVoto,
						rp.quantidadeCitacoes,
						rp.percentualPresenca,
						rp.quantidadeTcu,
						(SELECT COUNT(*) FROM notificacao n
							INNER JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao
							WHERE n.oidPolitico = p.oidPolitico AND un.oidUsuario = " . $decoded->id . " AND un.flgCurtir = 1) quantidadeVotaComoVoce
							
					FROM politico p
					INNER JOIN usuariopolitico up ON up.oidPolitico = p.oidPolitico
					INNER JOIN partido pa ON p.oidPartido = pa.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
					WHERE p.oidInstituicao IN (" . $instituicoes . ") 
					AND p.uf IN ('" . $estados . "') 
					AND up.oidUsuario = " . $decoded->id .  "
					AND up.flgAtivo = 1 ";			

				if($politicianType == "alertas"){
					$sql = "SELECT  p.oidPolitico, 
						CONCAT(CASE p.oidInstituicao 
								WHEN 1 THEN 'Dep.'
								WHEN 2 THEN 'Sen.'
								WHEN 3 THEN 'Dep.'
							END, ' ', p.nome) AS nome,
						p.arquivoFoto, p.arquivoFotoLocal, pa.sigla, p.uf, i.abreviatura, pa.sigla, 
						SUM(CASE WHEN un.flgCurtir = 1 THEN 1 ELSE 0 END) AS curtidas, 
						SUM(CASE WHEN un.flgCurtir = 0 THEN 1 ELSE 0 END) AS descurtidas, 
						rp.evolucaoBens,
						rp.evolucaoReceitas,
						rp.evolucaoDespesas,
						rp.valorVoto,
						rp.quantidadeCitacoes,
						rp.percentualPresenca,
						rp.quantidadeTcu
						FROM politico p
						INNER JOIN partido pa ON p.oidPartido = pa.oidPartido
						INNER JOIN notificacao n ON n.oidPolitico = p.oidPolitico
						INNER JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao
						INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
						LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
						WHERE p.oidInstituicao IN (" . $instituicoes . ") 
						AND p.uf IN ('" . $estados . "') 
						AND n.flgAtivo = 1 
						AND pa.flgAtivo = 1 
						AND un.flgAtivo = 1 
						AND un.oidUsuario = " . $decoded->id;
						if(intval($instituicao) > 0) $sql .= " AND i.oidInstituicao = " . intval($instituicao) . " ";
						$sql .= " GROUP BY p.oidPolitico ";
				}
				

				if(strlen($key) > 3) $sql .= " AND p.nome LIKE '%" . $key . "%' ";
				
				if($order != ""){ 
					if($order == "curtidas"){
						$sql .= " ORDER BY curtidas DESC";
					}else{
						$sql .= " ORDER BY curtidas ASC";
					}
				}else $sql .= " ORDER BY curtidas DESC, descurtidas DESC"; 
				$sql .= "
					LIMIT " . ((intval($page) - 1) * 10) . ", 10";

				$arrPoliticos = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");
				
				$newResponse = $response->withJson($arrPoliticos);
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}

		//Recupera curtidas
		public function curtidas(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$arrResposta = $this->container->db->query("SELECT COUNT(un.flgCurtir) AS curtidas
					FROM usuarionotificacao un
					INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
					WHERE n.flgAtivo = 1 
					AND un.flgCurtir = 1 
					AND un.flgAtivo = 1 
					AND n.oidPolitico = " . intval($args["id"]) . "
					AND un.oidUsuario = " . $decoded->id)->fetchAll(\PDO::FETCH_ASSOC);
				$newResponse = $response->withJson(count($arrResposta) > 0 ? $arrResposta[0] : false);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}		

		//Recupera descurtidas
		public function descurtidas(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				$arrResposta = $this->container->db->query("SELECT COUNT(un.flgCurtir) AS descurtidas
					FROM usuarionotificacao un
					INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
					WHERE n.flgAtivo = 1 
					AND un.flgCurtir = 0 
					AND un.flgAtivo = 1 
					AND n.oidPolitico = " . intval($args["id"]) . "
					AND un.oidUsuario = " . $decoded->id)->fetchAll(\PDO::FETCH_ASSOC);
				$newResponse = $response->withJson(count($arrResposta) > 0 ? $arrResposta[0] : false);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}				

        //Recupera quantidade de políticos do usuário
		public function friendsCount(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
                $sql = "SELECT COUNT(up.oidPolitico) AS total FROM usuariopolitico up WHERE up.oidUsuario = " . $decoded->id . " AND up.flgAtivo = 1";
				$arrPoliticos = $this->container->db->cachedQuery($sql, $this->container, 60);
                
                $newResponse = $response->withJson(count($arrPoliticos) > 0 ? $arrPoliticos[0] : false);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");
				
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}   
		
		public function representantes(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");

                $sql = "SELECT pu.oidAboutPoliticoUsuario, pu.oidPolitico, p.nome AS politico, pa.sigla, p.uf, p.arquivoFotoLocal, i.oidInstituicao, ins.nome AS instituicao, ins.minimoPredicao
				FROM aboutpoliticousuario pu
				INNER JOIN aboutpoliticousuarioinstituicao i ON pu.oidAboutPoliticoUsuario = i.oidAboutPoliticoUsuario
				INNER JOIN instituicao ins ON ins.oidInstituicao = i.oidInstituicao
				INNER JOIN politico p ON p.oidPolitico = pu.oidPolitico
				INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
				WHERE 
				(
					SELECT COUNT(p.oidProposicao) AS proposicoes FROM usuarioproposicao up
					INNER JOIN proposicao p ON p.oidProposicao = up.oidProposicao
					WHERE up.oidUsuario = " . $decoded->id . " AND p.oidInstituicao = i.oidInstituicao
				) >= ins.minimoPredicao AND				
				pu.oidAboutPoliticoUsuario IN (
					SELECT MAX(pu2.oidAboutPoliticoUsuario) 
					FROM aboutpoliticousuario pu2
					INNER JOIN aboutpoliticousuarioinstituicao i2 ON pu2.oidAboutPoliticoUsuario = i2.oidAboutPoliticoUsuario
					WHERE pu2.oidUsuario = " . $decoded->id . "
					GROUP BY i2.oidInstituicao
				)";

				$representantes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");


				$sql = "SELECT * FROM instituicao WHERE flgAtivo = 1";
				$instituicoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

				for($i = 0; $i < count($instituicoes); $i++){

					$sql = "SELECT COUNT(p.oidProposicao) AS proposicoes FROM usuarioproposicao up
					INNER JOIN proposicao p ON p.oidProposicao = up.oidProposicao
					WHERE up.oidUsuario = " . $decoded->id . " AND p.oidInstituicao = " . $instituicoes[$i]["oidInstituicao"];
					$quantidadeInteracoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

					$instituicoes[$i]["quantidadeInteracoes"] = $quantidadeInteracoes[0]["proposicoes"];
					$instituicoes[$i]["interacoesRestantes"] = $instituicoes[$i]["minimoPredicao"] - $quantidadeInteracoes[0]["proposicoes"];

					for($j = 0; $j < count($representantes); $j++){
						if($instituicoes[$i]["oidInstituicao"] == $representantes[$j]["oidInstituicao"]){

							$instituicoes[$i]["representantes"][] = $representantes[$j];
							break;
						}
					}
				}
				
				return $response->withJson($instituicoes);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}   

	}
?>