<?php
    namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	
	class Share{
		protected $container;
		public function __construct($container){
			$this->container = $container;
		}
		
		public function getFicha(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$shareFicha = $this->container->db->get("shareficha", "*", ["oidShareFicha" => intval($args["id"])]);

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
					WHERE rd.flgAtivo = 1 AND rd.oidPolitico = " . $shareFicha["oidPolitico"] . " 
					ORDER BY rd.anoEleicao ASC";		

				$arrFicha = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$sql = "SELECT p.nome, p.oidInstituicao, p.uf, pa.sigla, p.arquivoFotoLocal, i.titulo
					FROM politico p
					INNER JOIN partido pa ON pa.oidPartido = p.oidPartido
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					WHERE p.oidPolitico = " . $shareFicha["oidPolitico"];		

				$politico = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
                
                $sql = "SELECT 
                    n.oidNotificacao, CONCAT(n.siglaTipo, ' ', n.numero, '/', n.ano) AS proposicao, 
                    pr.ementa, CASE tn.oidTipoNotificacao 
                        WHEN 3 THEN 'SIM'
                        WHEN 4 THEN 'NÃO'
                        WHEN 6 THEN 'ABSTENÇÃO'
                        WHEN 7 THEN 'OBSTRUÇÃO'
                        WHEN 8 THEN 'NÃO VOTOU'
                        WHEN 9 THEN 'VOTO SECRETO'
                    END AS voto, n.dataHoraEvento, pr.explicacao 
                    FROM notificacao n
                    INNER JOIN tiponotificacao tn ON tn.oidTipoNotificacao = n.oidTipoNotificacao
                    INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
                    INNER JOIN proposicao pr ON pr.oidProposicao = np.oidProposicao
                    WHERE n.oidPolitico = " . $shareFicha["oidPolitico"] . "
                    AND tn.oidTipoNotificacao IN (3,4,6,7,8,9)
                    AND n.flgAtivo = 1 AND pr.flgAtivo = 1 AND pr.explicacao IS NOT NULL
                    ORDER BY n.dataHoraEvento DESC";
                
                $votacao = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
                $sql = "SELECT tn.oidTipoNotificacao, CASE tn.oidTipoNotificacao 
					WHEN 1 THEN 'Presença'
					WHEN 2 THEN 'Ausência'
					WHEN 5 THEN 'Ausência Justificada'
					END AS tipo, COUNT(tn.oidTipoNotificacao) AS total 
					FROM notificacao n
					INNER JOIN tiponotificacao tn ON tn.oidTipoNotificacao = n.oidTipoNotificacao
					WHERE n.oidPolitico = " . $shareFicha["oidPolitico"] . "
					AND tn.oidTipoNotificacao IN (1,2,5)
					GROUP BY tn.oidTipoNotificacao";
                
                $presenca = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);		

				$sql = "SELECT c.oidCitacao, c.operacao, c.descricao, c.linksMaisInfo FROM citacao c
					WHERE c.oidPolitico = " . $shareFicha["oidPolitico"] . "
					AND c.flgAtivo = 1 ORDER BY c.oidCitacao DESC";
				
                $citacao = $this->container->db->cachedQuery($sql, $this->container, 3600);		

				$sql = "SELECT oidContasTcu, cod, inidoneoLicitar, dataLancamento, linkInteiroTeor
					FROM contastcu WHERE oidPolitico =  " . $shareFicha["oidPolitico"] . "
					AND flgAtivo = 1";
					
                $contastcu = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);						
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$this->container->db->insert("logshareficha", [
					"oidShareFicha" => $shareFicha["oidShareFicha"],
					"ip" => $_SERVER['REMOTE_ADDR']
				]);             
				
				return $response->withJson(["success" => true, 
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

		public function registraFicha(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");

				$politico = $request->getParam("politico");
				$instituicao = $request->getParam("instituicao");
			
				$id = $this->container->db->insert("shareficha", [
					"oidUsuario" => $decoded->id,
					"oidPolitico" => intval($politico),
					"oidInstituicao" => intval($instituicao)
				]); 

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");	
				
				return $response->withJson(["success" => true, "id" => $id, "baseMensagemShare" => "Veja o resumo de "]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	
		
		public function registraApp(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");

				$pagina = $request->getParam("pagina");
			
				$id = $this->container->db->insert("sharepagina", [
					"oidUsuario" => $decoded->id,
					"pagina" => $pagina
				]); 

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0 || $id == 0) throw new \Exception("Não pudemos processar sua requisição.");	
				$link = "http://site.srcidadao.com.br";
				return $response->withJson(["success" => true, "id" => $id, "mensagem" => "Conheça o App Sr.Cidadão! Acompanhe o trabalho do seu político no celular.", "link" => $link]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}

		public function getLembrete(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$shareLembrete = $this->container->db->get("sharelembrete", "*", ["oidShareLembrete" => intval($args["id"])]);

				$sql = "SELECT l.*, p.*, i.nome AS instituicao FROM lembrete l
					INNER JOIN proposicao p ON p.oidProposicao = l.oidProposicao
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					WHERE l.oidLembrete = " . $shareLembrete["oidLembrete"];				

				$arrLembrete = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0 || count($arrLembrete) == 0) throw new \Exception("Não pudemos processar sua requisição.");

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
					WHERE np.oidProposicao = " . intval($arrLembrete[0]["oidProposicao"]) . " ORDER BY p.nome";
				$arrVotacao = $this->container->db->cachedQuery($sql, $this->container, 3600 * 24);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$this->container->db->insert("logsharelembrete", [
					"oidShareLembrete" => $shareLembrete["oidShareLembrete"],
					"ip" => $_SERVER['REMOTE_ADDR']
				]);             
				
				return $response->withJson(["success" => true, 
					"lembrete" => $arrLembrete[0],
					"votacao" => $arrVotacao
				]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}	

		public function registraLembrete(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");

				$lembrete = $request->getParam("lembrete");
			
				$id = $this->container->db->insert("sharelembrete", [
					"oidUsuario" => $decoded->id,
					"oidLembrete" => intval($lembrete)
				]); 

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");	
				
				return $response->withJson([
					"success" => true, 
					"id" => $id
				]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}			
		
	}
?>