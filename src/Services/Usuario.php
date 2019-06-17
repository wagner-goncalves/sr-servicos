<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
	use MP\Utils\Bcrypt\Bcrypt;
	use MP\Utils\Parametro;
	use Defuse\Crypto\Crypto;
	use Defuse\Crypto\Key;	
    
    class Usuario
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
		public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				$usuario = $this->container->db->get("usuario", ["nome", "uf", "telefone", "email", "flgEmailVerificado", "dataHoraVerificacao", "flgRecebeEmail", "flgRecebeNotificacao", "arquivoImagem", "flgSexo", "flgFacebook"], ["oidUsuario" => $decoded->id]);
				if($usuario) $response->withJson($usuario);
				else throw new \Exception("Usuário inválido.");
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	
		
		public function recuperarSenha(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$postVars = $request->getParsedBody();
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
				$oidRecuperarSenha = Crypto::decrypt($postVars["id"], $keyAscii);				
				if(strlen($postVars["senha"]) < 3)  throw new \Exception("A senha deve conter pelo menos 3 caracteres.");		

				$recuperarSenha = $this->validaTokenRecuperarSenha($oidRecuperarSenha);

				//Atualiza senha
				$this->container->db->update("usuario", [
					"senha" => \MP\Utils\Bcrypt\Bcrypt::hash($postVars["senha"])
				], ["oidUsuario" => $recuperarSenha["oidUsuario"]]);

				//Log recuperar senha
				$this->container->db->update("recuperarsenha", ["flgRecuperado" => "1"], ["oidRecuperarSenha" => $oidRecuperarSenha]);				

                return $response->withJson(["success" => true, "message" => "Senha atualizada com sucesso!"]);	
            }catch(\Exception $e){
                return $response->withJson(["success" => false, "message" => $e->getMessage()]);
            }  	
        }
		
		private function validaTokenRecuperarSenha($oidRecuperarSenha){
			$recuperarSenha = $this->container->db->get("recuperarsenha", ["oidUsuario"], 
				["AND" => [
					"oidRecuperarSenha" => $oidRecuperarSenha,
					"flgRecuperado" => 0
				]
			]);	
			$error = $this->container->db->error();				
			if(intval($error[0]) > 0 || !$recuperarSenha) throw new \Exception("Token de recuperação de senha inválido. Você já usou este link para recuperar a senha. Acesse o App Sr.Cidadão e solicite NOVA recuperação de senha.");		
			else return $recuperarSenha;
		}
		
		public function validaEsqueciSenha(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
                $oidRecuperarSenha = Crypto::decrypt($args["id"], $keyAscii);
					
				$sql = "SELECT u.nome, r.flgRecuperado from recuperarsenha r
					INNER JOIN usuario u ON u.oidUsuario = r.oidUsuario
					WHERE r.oidRecuperarSenha = " . intval($oidRecuperarSenha);		
					
				$arrRecuperarSenha = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);	
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0 || count($arrRecuperarSenha) == 0) throw new \Exception("Token de recuperação de senha inválido.");		
				
                return $response->withJson(["success" => true, "usuario" => $arrRecuperarSenha[0]]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  	
        }		
		
		public function esqueciSenha(ServerRequestInterface $request, ResponseInterface $response, array $args){			
            try{
				$postVars = $request->getParsedBody();
                $usuario = $this->container->db->get("usuario", ["oidUsuario", "email", "nome"], 
					["email" => $postVars["email"]]);

				if(!$usuario || count($usuario) == 0){ 
					$this->container->db->insert("recuperarsenha", [
						"ip" => $_SERVER["REMOTE_ADDR"]
					]);					
				
					throw new \Exception("Não encontramos o e-mail informado. Verifique se você digitou corretamente o endereço.");	
				}
				
				$template = Parametro::getValor($this->container->db, "EMAIL_ESQUECI_SENHA");
				$emailRemetente = getenv("REPLY_TO_EMAIL");
				$nomeRemetente = getenv("REPLY_TO_NAME");
				$emailDestinatario = $usuario["email"];
				$nomeDestinatario = $usuario["nome"];
				$assunto = "[Sr.Cidadão] Solicitação de recuperação de senha";		
				
				$idRecuperar = $this->container->db->insert("recuperarsenha", [
					"oidUsuario" => $usuario["oidUsuario"], 
					"ip" => $_SERVER["REMOTE_ADDR"], 
					"template" => $template, 
					"emailRemetente" => $emailRemetente, 
					"nomeRemetente" => $nomeRemetente, 
					"emailDestinatario" => $emailDestinatario, 
					"nomeDestinatario" => $nomeDestinatario, 
					"assunto" => $assunto
				]);						
				
				$keyAscii = Key::loadFromAsciiSafeString(getenv("CYPHER_KEY"));
				$encryptedId = Crypto::encrypt($idRecuperar, $keyAscii);		
				
				$baseLink = Parametro::getValor($this->container->db, "LINK_RECUPERAR_SENHA");

				$data = [
					"#USUARIO#" => $usuario["nome"],
					"#LINK#" => $baseLink . $encryptedId
				];				
				
				//Envia para o USUÁRIO
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
				
				if($successPolitico){
					$message = Parametro::getValor($this->container->db, "MSG_SUCESSO_RECUPERAR_SENHA");
					return $response->withJson(["success" => true, "message" => $message]);
				}else{
					throw new \Exception("Desculpe-nos, não conseguimos enviar o e-mail para redefinição da senha. Tente mais tarde.");	
				}
				
                return $response->withJson($termoUso);
            }catch(\Exception $e){
				return $response->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);				
            }
		}			
		
		public function termosDeUso(ServerRequestInterface $request, ResponseInterface $response, array $args){			
            try{
                $termoUso = $this->container->db->get("termouso", ["termo"], ["dataFimVigencia" => null]);
                return $response->withJson($termoUso);
            }catch(\Exception $e){
				return $response->withStatus(500)->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);				
            }
		}			

		public function updateProfile(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				$patchVars = $request->getParams();		
				
				//Se alteração de senha, criptografa
				if(array_key_exists("senhaAtual", $patchVars)){
					$senha = $this->checkPassword($decoded->id, $patchVars["senhaAtual"], $patchVars["novaSenha"]);
					$patchVars = [];
					$patchVars["senha"] = $senha;
				}
				
				$usuario = $this->container->db->update("usuario", $patchVars, ["oidUsuario" => $decoded->id]);
				
				//Alteração de email: envia email de verificação. Salva log.
				
				if($usuario) $response->withJson($patchVars);
				else throw new \Exception("Dados inválidos.");
            }catch(\Exception $e){
				return $response->withStatus(500)->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);
            }
		}
		
		private function checkPassword($id, $senhaAtual, $novaSenha){
			$hash = $this->getHashById($id); //Obtém hash do banco
			$hashValido = \MP\Utils\Bcrypt\Bcrypt::check($senhaAtual, $hash); //Confere hash com nova senha
			
			if(!$hashValido){
				throw new \Exception("Senha atual inválida.");
			}					
			
			return \MP\Utils\Bcrypt\Bcrypt::hash($novaSenha);
		}
		
		private function usuarioValido($oidUsuario, $senha){			
            $usuario = $this->container->db->get("usuario", "oidUsuario", [
                "AND" => [
                    "oidUsuario" => $oidUsuario, 
                    "senha" => $senha
                ]
            ]);	
			if($usuario) return true;
			return false;
		}
		
		public function login(ServerRequestInterface $request, ResponseInterface $response, array $args){			
			$hash = $this->getHash($request->getParam("email"));
			$hashValido = \MP\Utils\Bcrypt\Bcrypt::check($request->getParam("password"), $hash);
			if($hashValido){
				$usuario = $this->getUsuario($request->getParam("email"), $hash);
				if($usuario){
					$jwt = $this->criaJwt($usuario["oidUsuario"]);
					$this->logLogin($usuario["oidUsuario"]);
					return $response->withJson(["token" => $jwt, "usuario" => $usuario]);
				}
			}
            return $response->withStatus(500);
		}
		
		public function loginFb(ServerRequestInterface $request, ResponseInterface $response, array $args){			
			$usuario = $this->getUsuarioFb($request->getParam("email"), $request->getParam("token"));
			if($usuario){
				$jwt = $this->criaJwt($usuario["oidUsuario"]);
				$this->logLogin($usuario["oidUsuario"]);
				return $response->withJson(["token" => $jwt, "usuario" => $usuario]);
			}
            return $response->withStatus(500);
		}	
		
		private function logLogin($oidUsuario){
			$login = ["oidUsuario" => $oidUsuario, "ip" => $_SERVER["REMOTE_ADDR"], "modeloAparelho" => $_SERVER["HTTP_USER_AGENT"]];
			$this->container->db->insert("login", $login);
		}
        
		public function emailDisponivel(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $usuario = $this->container->db->get("usuario", ["oidUsuario"], [ "email" => $request->getParam("email") ]);
                $email = false;
                if(is_array($usuario) && count($usuario) > 0){ 
                    $email = true;
                }
                return $response->withJson(["email" => $email]);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}        
        
        public function criaJwt($idUsuario, $minutos = 9999){
            $secretKey = getenv("JWT_KEY");
            $tokenInfo = [
                "iss" => getenv("JWT_ISS"), //Emissor
                "iat" => time(), //Criação
                "exp" => time() + 60 * $minutos, //Expiração
                "jti" => time(), // ID deste token
                "id" => $idUsuario
            ];
            return JWT::encode($tokenInfo, $secretKey);
        }
        
        public function getUsuario($email, $password){
            return $this->container->db->get("usuario", "*", [
                "AND" => [
                    "email" => $email, 
                    "senha" => $password
                ]
            ]);
        }
		
        public function getUsuarioFb($email, $token){
			
			$sql = "SELECT u.* FROM usuario u
				INNER JOIN social s ON s.oidUsuario = u.oidUsuario
				WHERE s.flgAtivo = 1 AND s.token = '" . $token . "' AND u.email = '" . $email . "'";		

			$arrUsuarios = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);	
			if(count($arrUsuarios) > 0) return $arrUsuarios[0];
			else return null;
        }		
		
        public function getHash($email){
            return $this->container->db->get("usuario", "senha", ["email" => $email]);
        }	

		public function getHashById($oidUsuario){
            return $this->container->db->get("usuario", "senha", ["oidUsuario" => $oidUsuario]);
        }	
        
        public function cadastro(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$postVars = $request->getParsedBody();
				$postVars["senha"] = \MP\Utils\Bcrypt\Bcrypt::hash($postVars["senha"]);
                
                $id = $this->container->db->insert("usuario", $postVars);
                if($id == "0") throw new \Exception("Erro ao cadastrar usuário.");
				
				//Envia email de verificação
				
                return $response->withJson(["oidUsuario" => "1", "firstAccess" => true]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }
		
        public function cadastroFb(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$postVars = $request->getParsedBody();
				$oidUsuario = $this->idUsuario($postVars["email"]);
				
				$senha = \MP\Utils\Bcrypt\Bcrypt::hash(time()); //Cria senha randômica
				$sexo = null;
				$firstAccess = false;
				
				if($postVars["gender"] == "male") $sexo = "M";
				else if($postVars["gender"] == "female") $sexo = "F";
				
				$usuario = [
					"nome" => $postVars["name"], 
					"email" => $postVars["email"], 
					"senha" => $senha,
					"flgEmailVerificado" => 1,
					"flgFacebook" => 1,
					"dataHoraVerificacao" => date("Y-m-d H:i:s"),
					"arquivoImagem" => $postVars["picture"],
					"flgSexo" => $sexo
				];				
				
				
				if($oidUsuario == 0){
					$oidUsuario = $this->container->db->insert("usuario", $usuario);
					if($oidUsuario == "0") throw new \Exception("Erro ao cadastrar usuário.");
					$firstAccess = true;
				}else{
					$this->container->db->update("usuario", $usuario, ["email" => $postVars["email"]]);
				}
				
				$this->container->db->update("social", ["flgAtivo" => 0], ["oidUsuario" => $oidUsuario]);
				$postVars["oidUsuario"] = $oidUsuario;
				$id = $this->container->db->insert("social", $postVars);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro incluir dados sociais.");						

				//Envia email de verificação
				
                return $response->withJson(["firstAccess" => $firstAccess, "oidUsuario" => $oidUsuario]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        }

		private function idUsuario($email){
			$usuario = $this->container->db->get("usuario", ["oidUsuario"], ["email" => $email]);
			if($usuario) return intval($usuario["oidUsuario"]);
			else return 0;
		}
        
        public function excluirAmizade(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{            
                $decoded = $request->getAttribute("token");
                $id = $request->getParam("id");
                $this->container->db->update("usuariopolitico", 
                    ["flgAtivo" => "0", "dataHoraDesativacao" => date("Y-m-d H:i:s")],
                    ["AND" => [
                        "oidPolitico" => $id, "oidUsuario" => $decoded->id
                        ]
                    ]);
					
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Não pudemos processar sua requisição.");										
					
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        } 

        public function deleteImage(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{            
                $decoded = $request->getAttribute("token");
                $id = $request->getParam("id");
                $this->container->db->update("usuario", 
                    ["arquivoImagem" => ""],
                    ["oidUsuario" => $decoded->id]);
					
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao deixar de seguir político.");					
					
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        } 
		
		
        public function adicionarAmizade(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{            
                $decoded = $request->getAttribute("token");
                $id = $request->getParam("id");
				
                $this->container->db->update("usuariopolitico", 
                    ["flgAtivo" => "0", "dataHoraDesativacao" => date("Y-m-d H:i:s")],
                    ["AND" => [
                        "oidPolitico" => $id, "oidUsuario" => $decoded->id
                        ]
                    ]);	

				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao adicionar político aos seus monitorados.");					
				
                $this->container->db->query("INSERT INTO usuariopolitico SET oidPolitico = " . intval($id) . ", oidUsuario = " . intval($decoded->id) . ", flgAtivo = 1, dataHoraCadastro = '" . date("Y-m-d H:i:s") . "'");
				
				//Coloca notificações na linha do tempo - Votações
                $this->container->db->query("
					INSERT IGNORE INTO usuarionotificacao(oidUsuario, oidNotificacao, oidProposicao, oidTipoNotificacao, flgReceber, flgEnviado, flgVisualizado, flgCriadoLembrete, dataHoraVisualizacao) 
					SELECT " .  $decoded->id . ", n.oidNotificacao, np.oidProposicao, n.oidTipoNotificacao, 1, 1, 1, 1, CURRENT_TIMESTAMP 
					FROM notificacao n 
					INNER JOIN notificacaoproposicao np ON np.oidNotificacao = n.oidNotificacao
					INNER JOIN proposicao p ON p.oidProposicao = np.oidProposicao
					WHERE n.oidPolitico = " . $id . " AND n.flgAtivo = 1 AND p.flgAtivo = 1");
					
				//Coloca notificações na linha do tempo - Presença
                $this->container->db->query("
					INSERT IGNORE INTO usuarionotificacao(oidUsuario, oidNotificacao, oidProposicao, oidTipoNotificacao, flgReceber, flgEnviado, flgVisualizado, flgCriadoLembrete, dataHoraVisualizacao) 
					SELECT " .  $decoded->id . ", n.oidNotificacao, null, n.oidTipoNotificacao, 1, 1, 1, 1, CURRENT_TIMESTAMP 
					FROM notificacao n 
					WHERE n.oidPolitico = " . $id . " AND n.oidTipoNotificacao IN (1,2,5) AND n.flgAtivo = 1");

				//Se há gostei em lote, já sapeca nas notificações recém inseridas
				$sql = "UPDATE usuarionotificacao un
					INNER JOIN gosteiproposicao gp ON gp.oidUsuario = un.oidUsuario 
						AND gp.oidProposicao = un.oidProposicao 
						AND gp.oidTipoNotificacao = un.oidTipoNotificacao
					SET un.flgCurtir = gp.flgCurtir
					WHERE un.oidUsuario = " .  $decoded->id;
				$this->container->db->query($sql);
				$sql = "UPDATE usuarionotificacao un
					INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
					INNER JOIN gosteipresenca gp ON gp.oidUsuario = un.oidUsuario 
						AND gp.dataHoraEvento = n.dataHoraEvento 
						AND gp.oidTipoNotificacao = un.oidTipoNotificacao
					SET un.flgCurtir = gp.flgCurtir, gp.dataHoraEvento = n.dataHoraEvento
					WHERE un.oidUsuario = " .  $decoded->id;
				$this->container->db->query($sql);				
				
				//$error = $this->container->db->error();				
				//if(intval($error[0]) > 0) throw new \Exception("Erro ao adicionar político aos seus monitorados.");
					
                return $response->withJson(["id" => $id]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["message" => $e->getMessage()]);
            }                
        } 		
		
		//Adiciona device para notificação
		public function device(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				$decoded = $request->getAttribute("token");
				$patchVars = $request->getParams();
				$aparelhoNovo = false;
				
				//Após salvar device pela primeira vez, envia msg de boas vindas
				$usuario = $this->container->db->get("usuario", ["tokenDevice", "idUserCloudMessage"], ["oidUsuario" => $decoded->id]);
				
				if(($usuario["tokenDevice"] == "" || $usuario["idUserCloudMessage"] == "") 
						&& ($patchVars["tokenDevice"] != "" || $patchVars["idUserCloudMessage"] != "")){
					$aparelhoNovo = true;
				}
				
				$usuario = $this->container->db->update("usuario", $patchVars, ["oidUsuario" => $decoded->id]);
				
				if($aparelhoNovo){
					$notificacao = new Notificacao($this->container);
					$notificacao->boasVindas($request, $response, $args);
				}

				if($usuario) $response->withJson($patchVars);
				else throw new \Exception("Dados inválidos.");
            }catch(\Exception $e){
				return $response->withStatus(500)->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);
            }
		}        
        
		public function preferencias(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{
				
				$decoded = $request->getAttribute("token");
				
				$where = [
					"AND" => [
						"oidUsuario" => $decoded->id,
						"flgAtivo" => 1
					]
				];
				
				$instituicao = $this->container->db->select("usuarioinstituicao", "oidInstituicao", $where);
				$partido = $this->container->db->select("usuariopartido", "oidPartido", $where);
				
				$interesse = $this->container->db->select("usuariointeresse", "oidInteresse", $where);
				$estado = $this->container->db->select("usuarioestado", "uf", $where);
				
				$preferencias = [
					"instituicoes" => $instituicao ? $instituicao : [],
					"partidos" => $partido ? $partido : [],
					"interesses" => $interesse ? $interesse : [],
					"estados" => $estado ? $estado : []
				];
				
				$response->withJson($preferencias);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }
		}	

		public function foto(ServerRequestInterface $request, ResponseInterface $response, array $args){	
            try{		
				$decoded = $request->getAttribute("token");
				$postVars = $request->getParsedBody();
				$arquivoImagem = $postVars["arquivoImagem"];
				$upload = new \MP\Utils\Image\Upload();
				
				$pasta = realpath(".") . DIRECTORY_SEPARATOR . getenv("UPLOAD_FOLDER") . DIRECTORY_SEPARATOR;
				$arquivo = $decoded->id . "-" . time() . ".jpg";
				
				$upload->base64ToJpeg($arquivoImagem, $pasta . $arquivo);
				
				$usuario = $this->container->db->update("usuario", ["arquivoImagem" => $arquivo], ["oidUsuario" => $decoded->id]);
				
				$response->withJson(["arquivoImagem" => $arquivo, "success" => true]);
            }catch(\Exception $e){
                return $response->withStatus(500);
            }				
		}			
    }
    