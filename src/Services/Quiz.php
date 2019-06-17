<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;

	class Quiz{

		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function quizzesVotacao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$usuario = $decoded->id;
				$arrQuizzes = $this->container->db->select("quizvotacao", "*", ["AND" => [
					"flgAtivo" => "1",
					"dataHoraInicio[<]" => date("Y:m:d H:i:s"),
					"dataHoraFim[>]" => date("Y:m:d H:i:s") 
				]]);
				
				
				for($i = 0; $i < count($arrQuizzes); $i++){
					//Contadores
					$arrQuizzes[$i]["stats"] = $this->getStatsRespostas($usuario, $arrQuizzes[$i]["oidQuizVotacao"]);
					$arrQuizzes[$i]["detalhes"] = $this->getDetalheVotacaoUsuario($usuario, $arrQuizzes[$i]["oidQuizVotacao"]);
				}
				
				$this->container->logger->info("GET quizzesVotacao");
				
				return $response->withJson(["success" => true, "quizzes" => $arrQuizzes]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
		
		public function getQuiz(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

				$decoded = $request->getAttribute("token");
				$usuario = $decoded->id;
				
				$id = intval($args["id"]);
				if($id == 0) throw new \Exception("Quiz inválido");
				
				//Base do Quiz
				$quiz = $this->container->db->get("quizvotacao", "*", ["AND" => [
					"flgAtivo" => "1",
					"oidQuizVotacao" => $id,
					"dataHoraInicio[<]" => date("Y-m-d H:i:s"),
					"dataHoraFim[>]" => date("Y-m-d H:i:s") 
				]]);
				
				//Contadores
				$quiz["stats"] = $this->getStatsRespostas($usuario, $id);
				
				//Informações quiz/usuário
				$quiz["info"] = $this->container->db->get("quizvotacaousuario", "*", ["AND" => [
					"oidQuizVotacao" => $id,
					"oidUsuario" => $usuario 
				]]);
				
				//Perguntas
				$sql = "SELECT * FROM quizpergunta p
					INNER JOIN proposicao pr ON pr.oidProposicao = p.oidProposicao
					WHERE p.flgAtivo = 1 AND p.oidQuizVotacao = " . $id . " AND pr.oidInstituicao = 1 ORDER BY p.numero";
				$quiz["perguntas"] = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

				
				//Respotas para as perguntas
				for($j = 0; $j < count($quiz["perguntas"]); $j++){
					$quiz["perguntas"][$j]["respondida"] = false;

					$quiz["perguntas"][$j]["proposicao"] = $this->container->db->get("proposicao", "*", [
						"oidProposicao" => $quiz["perguntas"][$j]["oidProposicao"]
					]);
					
					$quiz["perguntas"][$j]["respostas"] = $this->container->db->select("quizresposta", "*", ["AND" => [
							"oidQuizPergunta" => $quiz["perguntas"][$j]["oidQuizPergunta"]
						], "ORDER" => ["ordem" => "ASC"]
					]);
					
					for($k = 0; $k < count($quiz["perguntas"][$j]["respostas"]); $k++){
						$respostaUsuario = $this->container->db->select("quizrespostausuario", "*", ["AND" => [
								"oidQuizResposta" => $quiz["perguntas"][$j]["respostas"][$k]["oidQuizResposta"],
								"oidUsuario" => $usuario 
							]
						]);
						
						if($respostaUsuario && count($respostaUsuario) > 0){
							$quiz["perguntas"][$j]["respostas"][$k]["selected"] = true;
							$quiz["perguntas"][$j]["respondida"] = true;
						}else{
							$quiz["perguntas"][$j]["respostas"][$k]["selected"] = false;
						}
					}
				}
				
				$this->container->logger->info("GET getQuiz");
				
				return $response->withJson(["success" => true, "quiz" => $quiz]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
		
		public function getQuizSlim(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$id = intval($args["id"]);
				if($id == 0) throw new \Exception("Quiz inválido");
				
				//Base do Quiz
				$quiz = $this->container->db->get("quizvotacao", "*", ["AND" => [
					"flgAtivo" => "1",
					"oidQuizVotacao" => $id,
					"dataHoraInicio[<]" => date("Y-m-d H:i:s"),
					"dataHoraFim[>]" => date("Y-m-d H:i:s") 
				]]);


				return $response->withJson(["success" => true, "quiz" => $quiz]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
		
		public function salvaResultado(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$decoded = $request->getAttribute("token");
				$usuario = $decoded->id;
				
				$ideologia = $request->getParam("ideologia");
				$politico = $request->getParam("politico");
				$quiz = $request->getParam("quiz");
				
				
				$this->container->db->update("quizvotacaousuario", [
						"oidPolitico" => intval($politico), 
						"oidIdeologia" => intval($ideologia), 
						"dataHoraIdeologia" => date("Y-m-d H:i:s"),
						"dataHoraPolitico" => date("Y-m-d H:i:s")
					],["AND" => [
							"oidUsuario" => $decoded->id, 
							"oidQuizVotacao" => intval($quiz)
					]
				]);  
				
				return $response->withJson(["success" => true]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }	
		}
		
		public function responder(ServerRequestInterface $request, ResponseInterface $response, array $args){
			try{
				$postVars = $request->getParsedBody();
                $decoded = $request->getAttribute("token");
				$tipoPergunta = "";
				
				//Identifica o tipo da pergunta
				$sql = "SELECT p.flgTipo FROM quizpergunta p
					INNER JOIN quizresposta r ON r.oidQuizPergunta = p.oidQuizPergunta
					WHERE r.oidQuizResposta = " . intval($postVars["resposta"]);

				$perguntas = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
				if(count($perguntas) > 0) $tipoPergunta = $perguntas[0]["flgTipo"];

				//Se o tipo é RADIO, apaga antes
				if($tipoPergunta == "radio"){
					$sql = "DELETE quizrespostausuario FROM quizrespostausuario
						JOIN quizresposta ON quizresposta.oidQuizResposta = quizrespostausuario.oidQuizResposta
						WHERE quizresposta.oidQuizPergunta = '" . intval($postVars["pergunta"]) . "' AND oidUsuario = " . $decoded->id;
					$this->container->db->query($sql)->fetchAll();
				}
				
				//Identifica a votação de origem, com base na pergunta
				$quizPergunta = $this->container->db->get("quizpergunta", "oidQuizVotacao", ["AND" => [
					"oidQuizPergunta" => intval($postVars["pergunta"])
				]]);
				
				//Salva a resposta
				$id = $this->container->db->insert("quizrespostausuario", [
					"oidUsuario" => $decoded->id,
					"oidTipoNotificacao" => intval($postVars["opcaoSelecionada"]),
					"oidQuizResposta" => intval($postVars["resposta"]),
					"oidQuizVotacao" => $quizPergunta
				]);
				
				return $response->withJson(["success" => true]);
		   
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            } 
		}
		
		public function getDetalheVotacaoUsuario($oidUsuario, $oidQuizVotacao){
			$sql = "SELECT vu.oidIdeologia, i.nome AS ideologia, vu.dataHoraIdeologia, vu.oidPolitico, 
					p.oidPolitico, p.nome AS politico, pa.sigla AS partido, p.uf, p.arquivoFotoLocal, 
					vu.dataHoraPolitico
					FROM quizvotacaousuario vu
				LEFT JOIN politico p ON p.oidPolitico = vu.oidPolitico
				LEFT JOIN ideologia i ON i.oidIdeologia = vu.oidIdeologia
				LEFT JOIN partido pa ON pa.oidPartido = p.oidPartido
				WHERE vu.oidUsuario = " . $oidUsuario . " AND vu.oidQuizVotacao = " . $oidQuizVotacao;
			$detalhes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
			
			return count($detalhes) > 0 ? $detalhes[0] : [];
		}
		
		public function getStatsRespostas($oidUsuario, $oidQuizVotacao){
			$sql = "SELECT (
				SELECT COUNT(qp.oidQuizPergunta) FROM quizvotacao qv
				INNER JOIN quizpergunta qp ON qp.oidQuizVotacao = qp.oidQuizVotacao
				WHERE qp.flgAtivo = 1 AND qp.oidQuizVotacao = " . $oidQuizVotacao . ") AS totalPerguntas ,
			(
				SELECT COUNT(qrp.oidQuizResposta) FROM quizvotacao qv
				INNER JOIN quizpergunta qp ON qp.oidQuizVotacao = qp.oidQuizVotacao
				INNER JOIN quizresposta qr ON qr.oidQuizPergunta = qp.oidQuizPergunta
				INNER JOIN quizrespostausuario qrp ON qrp.oidQuizResposta = qr.oidQuizResposta
				WHERE qp.flgAtivo = 1 AND qp.oidQuizVotacao = " . $oidQuizVotacao . " AND qrp.oidUsuario = " . $oidUsuario . "
			) AS totalRespostas";
			$stats = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
			return $stats[0];
		}
	}
?>