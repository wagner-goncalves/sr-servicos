<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
	use MP\Utils\Parametro;
	use MP\Utils\Bcrypt\Bcrypt;
	use Defuse\Crypto\Crypto;
	use Defuse\Crypto\Key;
    
    class Feed
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		//Lista de proposições
		public function proposicoes(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$page = intval($request->getParam("page"));
				$key = $request->getParam("key");
				
				$instituicao = $request->getParam("instituicao");
				$semAvaliacao = $request->getParam("semavaliacao");
				$comAvaliacao = $request->getParam("comavaliacao");
				$searchText = $request->getParam("searchText");

				$sql = "SELECT DISTINCT up.oidTipoNotificacao, up.dataHora as dataResposta, p.*, i.nome AS instituicao, 
				CASE WHEN qp.titulo IS NOT NULL THEN qp.titulo ELSE CONCAT(p.tipoProposicao, ' ', p.numero, '/', p.ano) END AS titulo, 
				IF(qp.pergunta IS NOT NULL, qp.pergunta, (IF(p.explicacao IS NOT NULL, p.explicacao, p.resumo))) AS pergunta
				FROM proposicao p
					INNER JOIN instituicao i ON i.oidInstituicao = p.oidInstituicao
					LEFT JOIN quizpergunta qp ON qp.oidProposicao = p.oidProposicao AND qp.flgAtivo = 1
					LEFT JOIN usuarioproposicao up ON up.oidProposicao = p.oidProposicao AND up.oidUsuario = " . $decoded->id  . "
					WHERE p.flgAtivo = 1 AND p.dataHoraEvento IS NOT NULL
					";
				
				if(is_array($instituicao) && count($instituicao) > 0){
					$sql .= " AND i.oidInstituicao IN (" . implode(",", $instituicao) . ")";
				}

				if(!empty($searchText) && strlen($searchText) > 3){
					$sql .= " AND (p.explicacao LIKE '%" . $searchText . "%' OR p.resumo LIKE '%" . $searchText . "%')";	
				}
				
				if($semAvaliacao == "false") $sql .= " AND up.oidTipoNotificacao IS NOT NULL";	
				if($comAvaliacao == "false") $sql .= " AND up.oidTipoNotificacao IS NULL";	
				
				$sql .= " ORDER BY up.oidTipoNotificacao, p.dataHoraEvento DESC";
				$sql .= " LIMIT " . ((intval($page) - 1) * 10) . ", 10";

				$arrProposicoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $response->withJson(["success" => true, "proposicoes" => $arrProposicoes]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}
		
		public function marcarProposicao(ServerRequestInterface $request, ResponseInterface $response, array $args){
			try{
				$postVars = $request->getParsedBody();
                $decoded = $request->getAttribute("token");

				//Salva a resposta
				$sql = "REPLACE INTO usuarioproposicao
					VALUES (" . intval($postVars["proposicao"]) . ", " . $decoded->id . ", " . intval($postVars["tiponotificacao"]) . ", '" . date("Y-m-d H:i:s") . "')";

				$this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				//Verifica se existe quiz com pergunta relacionada e já responde
				$sql = "SELECT p.oidQuizVotacao, r.oidQuizResposta, r.oidQuizPergunta FROM quizpergunta p
					INNER JOIN quizresposta r ON p.oidQuizPergunta = r.oidQuizResposta
					WHERE p.oidProposicao = " . intval($postVars["proposicao"]);
				
				$quizVotacao = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				if(count($quizVotacao) > 0){
					$sql = "DELETE quizrespostausuario FROM quizrespostausuario
						INNER JOIN quizresposta ON quizresposta.oidQuizResposta = quizrespostausuario.oidQuizResposta
						WHERE quizrespostausuario.oidUsuario = " . $decoded->id . " AND quizresposta.oidQuizResposta = '" . $quizVotacao[0]["oidQuizResposta"] . "'";

					$this->container->db->query($sql)->fetchAll();					
					
					$id = $this->container->db->insert("quizrespostausuario", [
						"oidUsuario" => $decoded->id,
						"oidTipoNotificacao" => intval($postVars["tiponotificacao"]),
						"oidQuizResposta" => $quizVotacao[0]["oidQuizResposta"],
						"oidQuizVotacao" => $quizVotacao[0]["oidQuizVotacao"]
					]);

				}

				return $response->withJson(["success" => true]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            } 
		}

        //Lista de notificações
		public function events(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				$page = $request->getParam("page");
				$key = $request->getParam("key");
				$id = $request->getParam("id");
				
				//if(intval($id) == 0) throw new \Exception("Parâmetros inválidos.");

				//Filtros
				$proposicoes = $request->getParam("proposicoes");
				$presencas = $request->getParam("presencas");
				$curtidas = $request->getParam("curtidas");
				$gostei = $request->getParam("gostei");
				$naogostei = $request->getParam("naogostei");
				$instituicao = $request->getParam("instituicao");
				$semavaliacao = $request->getParam("semavaliacao");
				
				$notificacoes = $request->getParam("notificacoes");
				
				$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => intval($decoded->id)]
				]);
				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));

				$this->container->db->insert("statsfeed", [
					"oidUsuario" => intval($decoded->id), 
					"page" => intval($page),
					"chave" => $key,
					"proposicoes" => $proposicoes == "true" ? 1 : $proposicoes == "false" ? 0 : null,
					"presencas" => $presencas == "true" ? 1 : $presencas == "false" ? 0 : null,
					"curtidas" => $curtidas == "true" ? 1 : $curtidas == "false" ? 0 : null,
					"notificacoes" => $notificacoes
				]); 
				
				
				$sql = "SELECT n.oidInstituicao, 
						p.oidPolitico, 
						CONCAT(CASE p.oidInstituicao 
								WHEN 1 THEN 'Dep.'
								WHEN 2 THEN 'Sen.'
								WHEN 3 THEN 'Dep.'
							END, ' ', p.nome) AS nome,
						p.arquivoFoto, 
						p.arquivoFotoLocal, 
						pa.sigla, 
						p.uf,
						n.oidNotificacao, 
						CASE WHEN qp.titulo IS NOT NULL THEN qp.titulo ELSE CONCAT(n.siglaTipo, ' ', n.numero, '/', n.ano) END AS titulo, 
						CASE WHEN (pr.explicacao) IS NOT NULL THEN pr.explicacao ELSE n.texto END AS texto, 
						n.url, 
						n.justificativa, 
						n.dataHoraNotificacao, 
						-- un.dataHoraCriacao, 
						-- un.flgVisualizado, 
						-- un.dataHoraVisualizacao, 
						un.flgCurtir, 
						-- (
						--	CASE WHEN pr.oidProposicao IS NOT NULL THEN 
						--		(SELECT up2.oidTipoNotificacao = n.oidTipoNotificacao
						--			FROM usuarioproposicao up2
						--			WHERE up2.oidUsuario = " . intval($decoded->id) . " AND up2.oidProposicao = pr.oidProposicao)
						--	ELSE 
						--		(SELECT gp2.oidTipoNotificacao = n.oidTipoNotificacao
						--			FROM gosteipresenca gp2
						--			WHERE gp2.flgCurtir = 1 AND gp2.oidUsuario = " . intval($decoded->id) . " AND gp2.dataHoraEvento = n.dataHoraEvento LIMIT 1) 
						--	END
						-- ) as flgCurtir,
						(SELECT np.oidProposicao FROM notificacaoproposicao np WHERE np.oidNotificacao = n.oidNotificacao) AS oidProposicao,
						(SELECT COUNT(c1.oidComentario) FROM comentario c1 WHERE c1.oidUsuario = " . intval($decoded->id) . " AND c1.oidNotificacao = n.oidNotificacao AND c1.flgAtivo = '1') AS contaComentarios,
						(SELECT COUNT(fp1.oidFacebookPost) > 0 FROM facebookpost fp1 WHERE fp1.oidUsuario = " . intval($decoded->id) . " AND fp1.oidNotificacao = n.oidNotificacao AND fp1.idPost IS NOT NULL) AS temPostFb,
						CASE WHEN qp.titulo IS NOT NULL THEN 
								CONCAT(n.siglaTipo, ' ', n.numero, '/', n.ano, ' - ', qp.titulo)
							ELSE
								CONCAT(n.siglaTipo, ' ', n.numero, '/', n.ano, ' - ', n.tipo)
							END as tipo,
						n.tema, n.dataHoraEvento, n.oidTipoNotificacao, n.objeto,
						(SELECT l.oidLembrete FROM lembrete l 
							INNER JOIN notificacaoproposicao np ON np.oidProposicao = l.oidProposicao
							WHERE np.oidNotificacao = n.oidNotificacao) AS oidLembrete 
					FROM politico p
					INNER JOIN notificacao n ON p.oidPolitico = n.oidPolitico
					LEFT JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao 
						AND un.oidUsuario = " . intval($decoded->id) . " 
						-- AND un.flgAtivo = 1
					-- LEFT JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
					INNER JOIN partido pa ON p.oidPartido = pa.oidPartido
					LEFT JOIN quizpergunta qp ON qp.oidProposicao = (SELECT np.oidProposicao FROM notificacaoproposicao np WHERE np.oidNotificacao = n.oidNotificacao) AND qp.flgAtivo = 1
					LEFT JOIN proposicao pr ON pr.oidProposicao = un.oidProposicao
					WHERE n.flgAtivo = 1 
					";

				if(intval($id) == 0) $sql .= " AND p.oidInstituicao IN (" . $instituicoes  . ") ";

				if(intval($id) > 0) $sql .= " AND p.oidPolitico = " . intval($id) . " ";	
				
				if(is_array($notificacoes) && count($notificacoes) > 0){
					$sql .= " AND n.oidNotificacao IN (" . implode(",", $notificacoes) . ")";	
				}
				
				//Filtros
				if($proposicoes == "true" && $presencas == "false") $sql .= " AND n.oidTipoNotificacao IN (3, 4, 6, 7, 8)";	
				else if($presencas == "true" && $proposicoes == "false") $sql .= " AND n.oidTipoNotificacao IN (1, 2, 5)";	
				//if($curtidas == "true"){ //Legado 0.3.1 ou mais antigo
					//$sql .= " AND un.flgCurtir IS NOT NULL";
				//s}
				
				/*
				if($gostei != "false" || $naogostei != "false" || $semavaliacao != "false"){
					$arrOpcoes = [];
					if($gostei == "true") $arrOpcoes[] = "un.flgCurtir = 1";
					if($naogostei == "true") $arrOpcoes[] = "un.flgCurtir = 0";
					if($semavaliacao == "true") $arrOpcoes[] = "un.flgCurtir IS NULL";	
					if(count($arrOpcoes) > 0) $sql .= " AND (" . implode(" OR ", $arrOpcoes) . ")";
				}
				*/
				
				//if($gostei == "true") $sql .= " AND un.flgCurtir = 1";
				//else if($naogostei == "true") $sql .= " AND un.flgCurtir = 0";
				//else if($semavaliacao == "true") $sql .= " AND un.flgCurtir IS NULL";
				
				if(is_array($instituicao) && count($instituicao) > 0 && intval($id) == 0){
					$sql .= " AND p.oidInstituicao IN (" . implode(",", $instituicao) . ")";
				}
				

				$sql .= "
					ORDER BY n.dataHoraEvento DESC
				";

				
				$sql .= " LIMIT " . ((intval($page) - 1) * 10) . ", 10";

				$arrEvents = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$newResponse = $response->withJson($arrEvents);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}
		
		public function lembreteEvents(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$page = $request->getParam("page");
				$oidLembrete = intval($request->getParam("oidLembrete"));
				
				//Criar as notificações sobre o lembrete (usuarionotificacao), caso o usuário não as tenha.
				//Só depois fazer o select
				$this->container->db->insert("usuariolembrete", [
					"oidLembrete" => intval($oidLembrete), 
					"oidUsuario" => intval($decoded->id),
					"dataHoraClick" => date("Y-m-d H:i:s")
				]);  
				
				$sql = "SELECT " . $oidLembrete . " AS oidLembrete, p.oidPolitico, 
					p.nome, p.arquivoFoto, p.arquivoFotoLocal,p.uf, pa.sigla, 
					n.oidNotificacao, n.titulo, n.texto, n.url, n.justificativa, n.dataHoraNotificacao, 
					un.dataHoraCriacao, un.flgVisualizado, un.dataHoraVisualizacao, un.flgCurtir,
					(SELECT COUNT(c1.oidComentario) FROM comentario c1 WHERE c1.oidUsuario = un.oidUsuario AND c1.oidNotificacao = n.oidNotificacao AND c1.flgAtivo = '1') AS contaComentarios,
					(SELECT COUNT(fp1.oidFacebookPost) > 0 FROM facebookpost fp1 WHERE fp1.oidUsuario = un.oidUsuario AND fp1.oidNotificacao = n.oidNotificacao AND fp1.idPost IS NOT NULL) AS temPostFb,
					n.tipo, n.tema, n.dataHoraEvento, n.oidTipoNotificacao, n.objeto, np.oidProposicao, 
					rp.evolucaoBens,
					rp.evolucaoReceitas,
					rp.evolucaoDespesas,
					rp.valorVoto,
					rp.quantidadeCitacoes,
					rp.percentualPresenca,
					rp.quantidadeTcu,
					rp.quantidadeVotaComoVoce,
					rp.quantidadeMateriasVotadas,
					rp.percentualVotaComoVoce
				FROM politico p
				INNER JOIN notificacao n ON p.oidPolitico = n.oidPolitico
				LEFT JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao  AND un.oidUsuario = " . intval($decoded->id) . "
				INNER JOIN partido pa ON p.oidPartido = pa.oidPartido
				INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
				LEFT JOIN resumopolitico rp ON rp.oidPolitico = p.oidPolitico
				WHERE
				1 = 1 
				-- un.oidUsuario = " . intval($decoded->id) . " 
				-- AND un.flgAtivo = 1 
				AND np.oidProposicao = (SELECT oidProposicao FROM lembrete WHERE oidLembrete = " . $oidLembrete . " 
				LIMIT 0, 1)";
								
				$sql .= "
					ORDER BY n.dataHoraEvento DESC, p.nome 
					LIMIT " . ((intval($page) - 1) * 10) . ", 10"; 

				$rsEvents = $this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$arrEvents = [];
				if($rsEvents) $arrEvents = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				$newResponse = $response->withJson($arrEvents);
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}		
        
        //Meus políticos -- últimos 3 com evento
		public function featured(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				
				return [];
				
				$decoded = $request->getAttribute("token");
				
				$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => intval($decoded->id)]
				]);
				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));
				
				$sql = "SELECT DISTINCT p.oidPolitico, CONCAT(CASE p.oidInstituicao 
								WHEN 1 THEN 'Dep.'
								WHEN 2 THEN 'Sen.'
							END, ' ', p.nome) AS nome, p.arquivoFoto, p.arquivoFotoLocal, 
					0 AS eventosTotal,
					(SELECT COUNT(n1.oidPolitico) FROM notificacao n1 WHERE n1.flgAtivo = 1 AND n1.oidPolitico = p.oidPolitico) AS eventosNovos, 
					n.dataHoraEvento, n.oidTipoNotificacao, n.objeto
					FROM politico p
					INNER JOIN notificacao n ON p.oidPolitico = n.oidPolitico
					INNER JOIN usuarionotificacao un ON un.oidNotificacao = n.oidNotificacao
					WHERE p.oidInstituicao IN (" . $instituicoes . ") AND n.flgAtivo = 1 AND un.oidUsuario = " . intval($decoded->id) . " AND un.flgAtivo = 1
					ORDER BY RAND()
					LIMIT 0, 3";
				
				$arrFeatured = $this->container->db->cachedQuery($sql, $this->container, 60);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$newResponse = $response->withJson($arrFeatured);
				return $newResponse ;			
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
        }
        
		public function likePresencaEmLote(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $like = $request->getParam("like");
				$dataHora = $request->getParam("dataHora");
                $oidTipoNotificacao = $request->getParam("id");
				
				if(empty($like) || $like == "" || $like == null || $like == "null"){
					$like = "null";
				}else{ 
					$like = intval($like);
				}
				
				$sql = "UPDATE usuarionotificacao un
					INNER JOIN notificacao n ON un.oidNotificacao = n.oidNotificacao 
					AND un.oidUsuario = " . $decoded->id . " 
					AND un.oidTipoNotificacao = " . intval($oidTipoNotificacao) . " 
					AND n.dataHoraEvento = '" . $dataHora . "'
					SET un.flgCurtir = " . $like . ", un.dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE un.oidUsuario = " . $decoded->id;
				
				$this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");	

				$sql = "INSERT INTO gosteipresenca (dataHoraEvento, oidUsuario, oidTipoNotificacao, flgCurtir)
					VALUES ('" . $dataHora . "', " . intval($decoded->id) . ", " . intval($oidTipoNotificacao) . ", " . intval($like) . ")
					ON DUPLICATE KEY UPDATE flgCurtir = " . intval($like);
				
                $this->container->db->query($sql);

                return $response->withJson(["success" => true]);		 
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        } 
		
		public function likePresencaEmLoteExclusivo(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $like = $request->getParam("like");
				$dataHora = $request->getParam("dataHora");
                $oidTipoNotificacao = $request->getParam("id");
				
				if(empty($like) || $like == "" || $like == null || $like == "null"){
					$like = "null";
				}else{ 
					$like = intval($like);
				}

				$sql = "SELECT un.oidNotificacao FROM  usuarionotificacao un
				INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
				WHERE un.oidUsuario = " . intval($decoded->id) . "
				AND n.dataHoraEvento = '" . $dataHora . "' 
				AND un.oidProposicao IS NULL 
				LIMIT 1";

				$usuarioNotificacao = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

				//Se não encontrou, insere usuarionotificacaos
				if(count($usuarioNotificacao) == 0){
					$sql = "INSERT IGNORE INTO usuarionotificacao(oidUsuario, oidNotificacao, flgReceber, oidProposicao, oidTipoNotificacao)
						SELECT " . intval($decoded->id) . ", n.oidNotificacao, 1, null, n.oidTipoNotificacao
						FROM notificacao n
						WHERE n.dataHoraEvento = '" . $dataHora . "'
						AND n.oidTipoNotificacao in (1,2,5)";
					
					$this->container->db->query($sql);	
				}

				// Limpa likes anteriores
				$sql = "UPDATE usuarionotificacao un
					INNER JOIN notificacao n ON un.oidNotificacao = n.oidNotificacao 
					AND n.dataHoraEvento = '" . $dataHora . "'
					SET un.flgCurtir = null, un.dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE un.oidUsuario = " . $decoded->id;
				
				$this->container->db->query($sql);
					
				// Insere novo like
				$sql = "UPDATE usuarionotificacao un
					INNER JOIN notificacao n ON un.oidNotificacao = n.oidNotificacao 
					AND un.oidTipoNotificacao = " . intval($oidTipoNotificacao) . " 
					AND n.dataHoraEvento = '" . $dataHora . "'
					SET un.flgCurtir = " . $like . ", un.dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE un.oidUsuario = " . $decoded->id;
				
				$this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");	

				$sql = "INSERT INTO gosteipresenca (dataHoraEvento, oidUsuario, oidTipoNotificacao, flgCurtir)
					VALUES ('" . $dataHora . "', " . intval($decoded->id) . ", " . intval($oidTipoNotificacao) . ", " . intval($like) . ")
					ON DUPLICATE KEY UPDATE flgCurtir = " . intval($like);
				
				$this->container->db->query($sql);
			
                return $response->withJson(["success" => true]);		 
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
		} 
		

		private function preparaDadosUsuarioNotificacao($oidUsuario, $oidProposicao){

			$sql = "SELECT oidNotificacao FROM  usuarionotificacao
				WHERE oidUsuario = " . $oidUsuario . "	 
				AND oidProposicao = " . $oidProposicao . "	
				LIMIT 1";
			$usuarioNotificacao = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
			
			//Se não encontrou, insere usuarionotificacao
			if(count($usuarioNotificacao) == 0){
				$sql = "INSERT IGNORE INTO usuarionotificacao(oidUsuario, oidNotificacao, flgReceber, oidProposicao, oidTipoNotificacao)
					SELECT " . $oidUsuario . ", n.oidNotificacao, 1, np.oidProposicao, n.oidTipoNotificacao FROM notificacao n
					INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
					WHERE np.oidProposicao = " . $oidProposicao;

				$this->container->db->query($sql);	
			}
		}
		
		public function likeVotacaoEmLote(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $like = $request->getParam("like");
				$oidProposicao = $request->getParam("oidProposicao");
                $oidTipoNotificacao = $request->getParam("id");

				if(empty($like) || $like == "" || $like == null || $like == "null"){
					$like = "null";
				}else{ 
					$like = intval($like);
				}

				$this->preparaDadosUsuarioNotificacao(intval($decoded->id), intval($oidProposicao));
				 
				
				//Limpa curtidas anteriores
				$sql = "UPDATE usuarionotificacao
					SET flgCurtir = 0
					WHERE oidUsuario = " . $decoded->id . " 
					AND oidProposicao = '" . intval($oidProposicao) . "'";
				
				$this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");	
				
				$sql = "UPDATE usuarionotificacao
					SET flgCurtir = " . $like . ", dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE oidUsuario = " . $decoded->id . " 
					AND oidTipoNotificacao = " . intval($oidTipoNotificacao) . " 
					AND oidProposicao = '" . intval($oidProposicao) . "'";
				
				$this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");		

				$sql = "INSERT INTO gosteiproposicao (oidProposicao, oidUsuario, oidTipoNotificacao, flgCurtir)
					VALUES ('" . intval($oidProposicao) . "', " . intval($decoded->id) . ", " . intval($oidTipoNotificacao) . ", " . intval($like) . ")
					ON DUPLICATE KEY UPDATE flgCurtir = " . intval($like);
				
                $this->container->db->query($sql);		

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");		

				$sql = "CALL atualizaQuantidadeVotos(?)";
				$stmt = $this->container->db->pdo->prepare($sql);
				$stmt->bindParam(1, $decoded->id, \PDO::PARAM_INT);
				$stmt->execute();	

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar");		

                return $response->withJson(["success" => true]);		 
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        } 		
		
		public function likeVotacaoEmLoteExclusivo(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $like = $request->getParam("like");
				$oidProposicao = $request->getParam("oidProposicao");
                $oidTipoNotificacao = $request->getParam("id");

				if(empty($like) || $like == "" || $like == null || $like == "null"){
					$like = "null";
				}else{ 
					$like = intval($like);
				}
				
				$this->preparaDadosUsuarioNotificacao(intval($decoded->id), intval($oidProposicao));

				// Limpa likes anteriores
				$sql = "UPDATE usuarionotificacao
					SET flgCurtir = null, dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE oidUsuario = " . $decoded->id . " 
					AND oidProposicao = '" . intval($oidProposicao) . "'";

				$this->container->db->query($sql);
				
				//Insere novo like
				$sql = "UPDATE usuarionotificacao
					SET flgCurtir = " . $like . ", dataHoraCurtir = CURRENT_TIMESTAMP
					WHERE oidUsuario = " . $decoded->id . " 
					AND oidTipoNotificacao = " . intval($oidTipoNotificacao) . " 
					AND oidProposicao = '" . intval($oidProposicao) . "'";

				$this->container->db->query($sql);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar likes");		

				$sql = "INSERT INTO gosteiproposicao (oidProposicao, oidUsuario, oidTipoNotificacao, flgCurtir)
					VALUES ('" . intval($oidProposicao) . "', " . intval($decoded->id) . ", " . intval($oidTipoNotificacao) . ", " . intval($like) . ")
					ON DUPLICATE KEY UPDATE flgCurtir = " . intval($like);
 
				$this->container->db->query($sql);		

				//Apaga like
				if($like != "1"){
					$sql = "DELETE FROM usuarioproposicao
					WHERE oidUsuario = " . $decoded->id . " AND oidProposicao = " . $oidProposicao;	
					$this->container->db->query($sql);	
				}else{
					//Salva a resposta
					$sql = "REPLACE INTO usuarioproposicao
						VALUES (" . $oidProposicao . ", " . $decoded->id . ", " . intval($oidTipoNotificacao) . ", '" . date("Y-m-d H:i:s") . "')";
					$this->container->db->query($sql);	
				}

				$sql = "CALL atualizaQuantidadeVotos(?)";
				$stmt = $this->container->db->pdo->prepare($sql);
				$stmt->bindParam(1, intval($decoded->id), \PDO::PARAM_INT);
				$stmt->execute();	

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar");				

                return $response->withJson(["success" => true]);		 
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        } 		
		
		public function like(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$decoded = $request->getAttribute("token");
			$like = $request->getParam("like");
			$oidNotificacao = $request->getParam("id");
			//$this->registraLike($like, $oidNotificacao, $decoded->id);
			return $response->withJson(["id" => $oidNotificacao]);		               
        } 
        
        public function registraLike($like, $oidNotificacao, $oidUsuario){
			$this->container->db->update("usuarionotificacao", 
				["flgCurtir" => $like, "dataHoraCurtir" => date("Y-m-d H:i:s")],
				["AND" => [
					"oidNotificacao" => intval($oidNotificacao), "oidUsuario" => intval($oidUsuario)
					]
				]);				
        }
		
		public function inteiroTeor(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidNotificacao = $request->getParam("id");
				
				$notificacao = $this->container->db->get("notificacao", ["url"], ["oidNotificacao" => $oidNotificacao]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
    
                $this->container->db->insert("inteiroteor", [
                    "oidNotificacao" => intval($oidNotificacao), 
                    "oidUsuario" => intval($decoded->id)
                ]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar leitura");				
				
                return $response->withJson(["success" => true, "url" => $notificacao["url"]]);		
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        } 		
        
		public function addComment(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidNotificacao = $request->getParam("id");
				$oidPolitico = $request->getParam("politico");
                $msg = $request->getParam("msg");
				
				$politico = $this->container->db->get("politico", ["nome", "email", "uf", "oidInstituicao", "oidPartido"], ["oidPolitico" => intval($oidPolitico)]);
	
				$comentario = [
                    "oidNotificacao" => intval($oidNotificacao), 
                    "oidUsuario" => intval($decoded->id), 
					"oidPolitico" => intval($oidPolitico), 
					"oidInstituicao" => intval($politico["oidInstituicao"]), 
					"email" => $politico["email"], 
                    "comentario" => $msg, 
                    "dataHoraComentario" => date("Y-m-d H:i:s"), 
                    "flgAtivo" => "1"
                ];
                $comentario["oidComentario"] = $this->container->db->insert("comentario", $comentario);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao incluir comentário");
				
				//Email
				$partido = $this->container->db->get("partido", ["sigla"], ["oidPartido" => $politico["oidPartido"]]);
				$usuario = $this->container->db->get("usuario", ["nome", "email"], ["oidUsuario" => intval($decoded->id)]);
				$notificacao = $this->container->db->get("notificacao", "*", ["oidNotificacao" => intval($oidNotificacao)]);
				
				$successEmail = $this->sendCommentEmail($politico, $partido, $usuario, $notificacao, $comentario);

                if($successEmail){
                    $this->container->db->update("comentario", 
						["flgSucessoEmail" => 1], 
						["oidComentario" => intval($comentario["oidComentario"])]
					);
                } 
                
                return $response->withJson(["success" => true, "message" => "Comentário enviado com sucesso!"]);					
				
                //return $response->withJson(["id" => $oidNotificacao]);		
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }      
		
		
		private function sendCommentEmail($politico, $partido, $usuario, $notificacao, $comentario){
            try{
				$template = Parametro::getValor($this->container->db, "EMAIL_MENSAGEM_POLITICO");
				$templateCidadao = Parametro::getValor($this->container->db, "EMAIL_MENSAGEM_CIDADAO");
				
				$emailRemetente = $usuario["email"];
				$nomeRemetente = $usuario["nome"];
				$emailDestinatario = $politico["email"];
				$nomeDestinatario = $politico["nome"] . " " . $partido["sigla"] . "-" . $politico["uf"];
				$assunto = "[Sr.Cidadão] Novo comentário: " . $notificacao["titulo"];
				$dataHoraEvento = \DateTime::createFromFormat('Y-m-d H:i:s', $notificacao["dataHoraEvento"]);
				
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
				$encryptedId = Crypto::encrypt($comentario["oidComentario"], $keyAscii);					
				
				$texto = "<p>" . $notificacao["titulo"] . "</p>";

				if(strlen($notificacao["tipo"]) > 5){
					$texto .= '<p><strong>' . $notificacao["tipo"] . '</strong>';
					if(strlen($notificacao["tema"]) > 5) $texto .= ' sobre <strong>' . $notificacao["tema"] . '</strong>';
					$texto .= '</p>';
				}
				$texto .= '<p>' . $notificacao["texto"];
				if(strlen($notificacao["objeto"]) > 5) $texto .= '<p><strong>Objeto desta votação: </strong>' . $notificacao["objeto"] . '</p>';
				if(strlen($notificacao["justificativa"]) > 5)  $texto .= '<span><strong>Justificativa</strong>: ' . $notificacao["justificativa"] . '</span>';
				$texto .= '</p>';	

				$data = [
					"#DATAEVENTO#" => $dataHoraEvento->format('d/m/Y'),
					"#POLITICO#" => $nomeDestinatario, 
					"#EMAILPOLITICO#" => $emailDestinatario, 
					"#CIDADAO#" => $nomeRemetente, 
					"#EMAILCIDADAO#" => $emailRemetente, 
					"#ALERTA#" => $texto, 
					"#COMENTARIO#" => $comentario["comentario"], 
					"#OIDCOMENTARIO#" => $encryptedId
				];

				//Envia para o POLITICO
				//$emailDestinatario = "wagnerggg@gmail.com";
				//$emailRemetente = "contato@srcidadao.com.br";
				$this->container->mailer->clearAllRecipients();
				$successPolitico = $this->container->mailer->send($template, $data, function($message) use ($emailDestinatario, $assunto, $emailRemetente, $nomeRemetente){
					$message->to($emailDestinatario);
					$message->subject($assunto);
					$message->from(getenv("REPLY_TO_EMAIL"));
					$message->fromName($nomeRemetente);
					$message->addReplyTo($emailRemetente, $nomeRemetente);
				});
				
				//Envia para o CIDADAO
				$this->container->mailer->clearAllRecipients();
				$successCidadao = $this->container->mailer->send($templateCidadao, $data, function($message) use ($emailDestinatario, $assunto, $emailRemetente, $nomeRemetente){
					$message->to($emailRemetente);
					$message->subject($assunto);
					$message->from(getenv("REPLY_TO_EMAIL"));
					$message->fromName($nomeRemetente);
					$message->addReplyTo($emailRemetente, $nomeRemetente);                
				});
				
				return $successPolitico;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}
		
		private function sendRespostaEmail($politico, $partido, $usuario, $notificacao, $comentario, $resposta){
            try{
				$template = Parametro::getValor($this->container->db, "EMAIL_MENSAGEM_RESPOSTA");
				
				$emailRemetente = $politico["email"];
				$nomeRemetente = $politico["nome"] . " " . $partido["sigla"] . "-" . $politico["uf"];
				$emailDestinatario = $usuario["email"];
				$nomeDestinatario = $usuario["nome"];
				$assunto = "[Sr.Cidadão] Nova resposta: " . $notificacao["titulo"];
				$dataHoraEvento = \DateTime::createFromFormat('Y-m-d H:i:s', $notificacao["dataHoraEvento"]);
				
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
				$encryptedId = Crypto::encrypt($comentario["oidComentario"], $keyAscii);					
				
				$texto = "<p>" . $notificacao["titulo"] . "</p>";

				if(strlen($notificacao["tipo"]) > 5){
					$texto .= '<p><strong>' . $notificacao["tipo"] . '</strong>';
					if(strlen($notificacao["tema"]) > 5) $texto .= 'sobre <strong>' . $notificacao["tema"] . '</strong>';
					$texto .= '</p>';
				}
				$texto .= '<p>' . $notificacao["texto"];
				if(strlen($notificacao["objeto"]) > 5) $texto .= '<p><strong>Objeto desta votação: </strong>' . $notificacao["objeto"] . '</p>';
				if(strlen($notificacao["justificativa"]) > 5)  $texto .= '<span><strong>Justificativa</strong>: ' . $notificacao["justificativa"] . '</span>';
				$texto .= '</p>';	

				$data = [
					"#DATAEVENTO#" => $dataHoraEvento->format('d/m/Y'),
					"#POLITICO#" => $nomeDestinatario, 
					"#EMAILPOLITICO#" => $emailDestinatario, 
					"#CIDADAO#" => $nomeRemetente, 
					"#EMAILCIDADAO#" => $emailRemetente, 
					"#ALERTA#" => $texto, 
					"#COMENTARIO#" => $comentario["comentario"], 
					"#OIDCOMENTARIO#" => $encryptedId,
					"#RESPOSTA#" => $resposta["resposta"]
				];

				//Envia para o POLITICO
				$this->container->mailer->clearAllRecipients();
				//$emailDestinatario = "contato@srcidadao.com.br";
				//$emailRemetente = "wagnerggg@gmail.com";
				$successPolitico = $this->container->mailer->send($template, $data, function($message) use ($emailDestinatario, $assunto, $emailRemetente, $nomeRemetente){
					$message->to($emailDestinatario);
					$message->subject($assunto);
					$message->from(getenv("REPLY_TO_EMAIL"));
					$message->fromName($nomeRemetente);
					$message->addReplyTo($emailRemetente, $nomeRemetente);                
					
				});
				
				//Envia para o CIDADAO
				$this->container->mailer->clearAllRecipients();
				$successCidadao = $this->container->mailer->send($template, $data, function($message) use ($emailDestinatario, $assunto, $emailRemetente, $nomeRemetente){
					$message->to($emailDestinatario);
					$message->subject($assunto);
					$message->from(getenv("REPLY_TO_EMAIL"));
					$message->fromName($nomeRemetente);
					$message->addReplyTo($emailRemetente, $nomeRemetente);                
				});
				
				return $successPolitico;
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}
		
		public function deleteComment(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidComentario = $request->getParam("id");
    
                $this->container->db->update("comentario", ["flgAtivo" => 0, "dataHoraDesativacao" => date("Y-m-d H:i:s")], 
				["AND" => 
					["oidComentario" => intval($oidComentario), "oidUsuario" => $decoded->id]
				]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao apagar comentário");				
				
                return $response->withJson(["success" => true, "message" => "Comentário excluido com sucesso!"]);		
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }      		
        
		public function markAsRead(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidComentario = $request->getParam("id");
                $this->container->db->update("usuarionotificacao", ["flgVisualizado" => 1, "dataHoraVisualizacao" => date("Y-m-d H:i:s")], 
					["AND" => 
						["oidUsuario" => intval($decoded->id), "flgAtivo" => 1]
					]);
					
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");
					
                return $response->withJson(["success" => true]);		
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }
		}
		
		public function unread(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidNotificacao = $request->getParam("id");
                $count = $this->container->db->count("usuarionotificacao", [
                    "AND" => [
                        "oidUsuario" => intval($decoded->id), "flgVisualizado[!]" => 1
                    ]
                ]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
                return $response->withJson(["count" => $count]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }  	
        }  		
		
		public function comments(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $decoded = $request->getAttribute("token");
                $oidNotificacao = $request->getParam("id");
                $comments = $this->container->db->select("comentario", "*", [
                    "AND" => [
                        "oidUsuario" => intval($decoded->id), "oidNotificacao" => intval($oidNotificacao), "flgAtivo" => "1"
                    ]
                ]);
				
				for($i = 0; $i < count($comments); $i++){
					$comments[$i]["respostas"] = $this->container->db->select("resposta", "*", [
						"AND" => [
							"oidComentario" => intval($comments[$i]["oidComentario"]), 
							"flgAtivo" => "1"
						]
					]);
				}
				
                return $response->withJson($comments);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }  	
        }
		
		public function comment(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
                $oidComentario = Crypto::decrypt($args["id"], $keyAscii);
				
                $comments = $this->container->db->select("comentario", ["oidUsuario", "oidNotificacao", "oidPolitico", "comentario", "dataHoraComentario", "email", "textoEmail"], [
                    "AND" => [
                        "oidComentario" => intval($oidComentario), "flgAtivo" => "1"
                    ]
                ]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0 || count($comments) == 0) throw new \Exception("Erro ao recuperar comentário.");						
				
				for($i = 0; $i < count($comments); $i++){
					$comments[$i]["oidComentario"] = $args["id"];
					$comments[$i]["respostas"] = $this->container->db->select("resposta", ["resposta", "dataHoraResposta"], [
						"AND" => [
							"oidComentario" => $oidComentario, 
							"flgAtivo" => "1"
						]
					]);
					
					$comments[$i]["politico"] = $this->container->db->get("politico", ["nome", "email", "uf", "oidInstituicao", "oidPartido"], ["oidPolitico" => $comments[$i]["oidPolitico"]]);
					$comments[$i]["partido"] = $this->container->db->get("partido", ["sigla"], ["oidPartido" => $comments[$i]["politico"]["oidPartido"]]);
					$comments[$i]["usuario"] = $this->container->db->get("usuario", ["nome", "email"], ["oidUsuario" => $comments[$i]["oidUsuario"]]);
					$comments[$i]["notificacao"] = $this->container->db->get("notificacao", "*", ["oidNotificacao" => $comments[$i]["oidNotificacao"]]);	

					unset($comments[$i]["oidUsuario"]);
					unset($comments[$i]["oidNotificacao"]);
					unset($comments[$i]["oidPolitico"]);
				}
				
				$comment = $comments > 0 ? $comments[0] : [];
				
                return $response->withJson(["success" => true, "comentario" => $comment]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  	
        }		
		
		
		public function resposta(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{		
				$postVars = $request->getParsedBody();
				$id = $postVars["id"];
				$resposta = $postVars["resposta"];
				$textoResposta = strip_tags($resposta);
				
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
                $id = Crypto::decrypt($id, $keyAscii);			
				
				$idResposta = $this->container->db->insert("resposta", [
					"oidComentario" => $id,
					"resposta" => substr($resposta, 0, 500)
				]);
				
				$error = $this->container->db->error();
				if(intval($error[0]) > 0) throw new \Exception("Erro ao registrar resposta.");
				
				//Email
				$comentario = $this->container->db->get("comentario", "*", ["oidComentario" => $id]);
				$politico = $this->container->db->get("politico", ["nome", "email", "uf", "oidInstituicao", "oidPartido"], ["oidPolitico" => $comentario["oidPolitico"]]);
				$partido = $this->container->db->get("partido", ["sigla"], ["oidPartido" => $politico["oidPartido"]]);
				$usuario = $this->container->db->get("usuario", ["nome", "email"], ["oidUsuario" => $comentario["oidUsuario"]]);
				$notificacao = $this->container->db->get("notificacao", "*", ["oidNotificacao" => $comentario["oidNotificacao"]]);
				$resposta = $this->container->db->get("resposta", "*", ["oidComentario" => $id]);
				$successEmail = $this->sendRespostaEmail($politico, $partido, $usuario, $notificacao, $comentario, $resposta);
                if($successEmail){
                    $this->container->db->update("resposta", ["flgSucessoEmail" => 1], ["oidResposta" => $idResposta]);
                }				

				return $response->withJson(["success" => true, "resposta" => substr($textoResposta, 0, 500)]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }				
		}			
		
		//Recupera estatísticas do usuário
		public function stats(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				
				$arrInstituicao = $this->container->db->select("usuarioinstituicao", ["oidInstituicao"], ["AND" => 
					["flgAtivo" => 1, "oidUsuario" => intval($decoded->id)]
				]);
				$instituicoes = implode(',', array_map(function($el){ return $el['oidInstituicao']; }, $arrInstituicao));
                
                $sql = "SELECT * FROM (
				(SELECT COUNT(up.oidProposicao) AS curtidas
					FROM usuarioproposicao up
					WHERE up.oidTipoNotificacao = 3 AND up.oidUsuario = " . $decoded->id . ") AS curtidas,
				(SELECT COUNT(up.oidProposicao) AS descurtidas
					FROM usuarioproposicao up
					WHERE up.oidTipoNotificacao = 4 AND up.oidUsuario = " . $decoded->id . "
					) AS descurtidas,
				(SELECT 0 AS curtidasAmigos ) AS curtidasAmigos,
				(SELECT 0 AS descurtidasAmigos ) AS descurtidasAmigos,				
				(SELECT COUNT(up.oidPolitico) AS friends FROM usuariopolitico up 
					INNER JOIN politico p ON p.oidPolitico = up.oidPolitico
					WHERE p.oidInstituicao IN (" . $instituicoes . ") AND up.oidUsuario = " . $decoded->id . " AND up.flgAtivo = 1) AS friends
				)";

				$arrPoliticos = $this->container->db->cachedQuery($sql, $this->container, 60);
				$newResponse = $response->withJson(count($arrPoliticos) > 0 ? $arrPoliticos[0] : false);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				return $newResponse ;	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }					
		}	
		
		public function setIdNotificacaoFb(ServerRequestInterface $request, ResponseInterface $response, array $args){
			try{
				$decoded = $request->getAttribute("token");
				$oidFacebookPost = $args["id"];
				$idPost = $args["idFb"];
				$this->container->db->update("facebookpost", ["idPost" => intval($idPost) ], ["oidFacebookPost" => intval($oidFacebookPost)]);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");					
				
				$response->withJson(["success" => true, "id" => $oidFacebookPost]);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }					
		}
		
		public function getNotificacaoFb(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$oidNotificacao = $args["id"];
				$notificacao = $this->container->db->get("notificacao", ["titulo", "texto", "siglaTipo", "numero", "ano", "justificativa", "url", "tipo", "tema", "oidPolitico", "objeto", "dataHoraEvento"], ["oidNotificacao" => $oidNotificacao]);
				
				$sql = "SELECT p.oidProposicao, p.explicacao FROM proposicao p
					INNER JOIN notificacaoproposicao np ON np.oidProposicao = p.oidProposicao
					WHERE np.oidNotificacao = " . $oidNotificacao;
				$proposicao = $this->container->db->cachedQuery($sql, $this->container, 60);
				
				if($notificacao){ 
				
					$politico = $this->container->db->get("politico", ["nome", "uf", "oidPartido"], ["oidPolitico" => $notificacao["oidPolitico"]]);
					$partido = $this->container->db->get("partido", ["sigla"], ["oidPartido" => $politico["oidPartido"]]);
				
					$texto = $politico["nome"] . " • " . $partido["sigla"] . "-" . $politico["uf"] . "\r\n";
					$texto .= $notificacao["titulo"] . "\r\n\r\n";
					$texto .= $notificacao["siglaTipo"];
					$texto .= " " . $notificacao["numero"] . "/" . $notificacao["ano"];
					
					$texto .= "\r\n\r\n";

					if(count($proposicao) > 0 && $proposicao[0]["explicacao"] != ""){
						$texto .= $proposicao[0]["explicacao"];
					}else{
						$texto .= $notificacao["texto"];
					}
					
					if($notificacao["objeto"] != "" && strlen($notificacao["objeto"]) > 5){
						$texto .= "\r\n\r\n";
						$texto .= "Objeto desta votação: " . $notificacao["objeto"];
					}
					
					$oidFacebookPost = $this->container->db->insert("facebookpost", [
						"oidUsuario" => $decoded->id, 
						"oidNotificacao" => intval($oidNotificacao), 
						"mensagem" => $texto
						]);
					
					$link = "http://www.srcidadao.com.br";
					
					$response->withJson(["id" => $oidFacebookPost, "texto" => $texto, "link" => $link]);
				}else{
					throw new \Exception("Operação inválida.");
				}
            }catch(\Exception $e){
                return $response->withStatus(500);
            }			
		}
    }
    