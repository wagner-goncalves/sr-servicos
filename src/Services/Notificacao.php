<?php
	namespace MP\Services;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
	
	use MP\Notifications\ICloudMessage;
	use MP\Notifications\OneSignalCm;
    use MP\Utils\Parametro;
    use MP\Services\Feed;
    
    class Notificacao
    {
		protected $container;
		protected  $templateNotificacao = [
				'headings' => [
					'en' => ''
				],
				'contents' => [
					'en' => ''
				],
                'include_player_ids' => [],
				'data' => [],
				'buttons' => [],
				'small_icon' => 'ic_stat_sr_cidadao',
				'large_icon' => 'icon',
				'android_led_color' => '00FF00FF',
				'android_group' => 'SrCidadao',
				'android_group_message' => ["en" => "$[notif_count] alertas"]
			];			

		public function __construct($container){
			$this->container = $container;
		}
        
        public function lembrete(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $oidLembrete = intval($args["id"]);
                $message = new OneSignalCm();
                $respostas = [];
                
                $lembrete = $this->container->db->get("lembrete", "*", ["oidLembrete" => $oidLembrete]);
				$umaNotificacao = $this->templateNotificacao;
                $umaNotificacao['headings']['en'] = $lembrete["titulo"];
                $umaNotificacao['contents']['en'] = $lembrete["chamada"];
                $umaNotificacao['included_segments'] = ["All"]; //Todos os dispositivos
                unset($umaNotificacao['include_player_ids']);
				//$umaNotificacao['include_player_ids'] = ["b77f8d23-3e6e-4587-ae96-8d195a57a68a"]; //Wagner MOTOG4
				
                $umaNotificacao['data']["oidLembrete"] = $lembrete["oidLembrete"];
                $umaNotificacao['data']["oidTipoMensagem"] = "4"; //LEMBRETE
				$umaNotificacao['data']["state"] = [
					"go" => "app.lembrete", 
					"params" => [
						"oidLembrete" => $lembrete["oidLembrete"]
					]
				];

                $resposta = $message->sendToAll($umaNotificacao);
                $respostas[] = $resposta;

                return $response->withJson(["success" => true, "mensagens" => $respostas]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }    
        }        
        
        public function boasVindas(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                                    
                $decoded = $request->getAttribute("token");    
                   
                $message = new OneSignalCm();
                $respostas = [];
				
                $sql = "SELECT u.nome, u.idUserCloudMessage, (
						SELECT ue.uf FROM usuarioestado ue WHERE ue.oidUsuario = u.oidUsuario LIMIT 1
					) AS uf
					FROM usuario u 
					WHERE u.oidUsuario = " . $decoded->id;
                $arrNotificacoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

                $baseMensagem = Parametro::getValor($this->container->db, "TEXTO_NOTIFICACAO_BOAS_VINDAS");

                foreach($arrNotificacoes as $notificacao){
                    //$mensagemNotificacao = str_replace("#NOTIFICACOES#", $notificacao["totalNotificacoes"], $baseMensagem);
                    $mensagemNotificacao = $baseMensagem;
					
                    if($notificacao["idUserCloudMessage"] != ""){
                        $umaNotificacao = $this->templateNotificacao;
                        $umaNotificacao['headings']['en'] = Parametro::getValor($this->container->db, "TITULO_NOTIFICACAO_BOAS_VINDAS");
                        $umaNotificacao['contents']['en'] = $mensagemNotificacao;
                        $umaNotificacao['include_player_ids'] = [$notificacao["idUserCloudMessage"]];
                        $umaNotificacao['data']["oidUsuario"] = $decoded->id;
						$umaNotificacao['data']["oidTipoMensagem"] = "1"; //BOAS VINDAS
						$umaNotificacao['data']["state"] = [
							"go" => "walkthrough", 
							"params" => []
						];						

                        $resposta = $message->sendToDevice($umaNotificacao);
                        $respostas[] = $resposta;

                        if(!isset($resposta["errors"]) && isset($resposta["id"])){
                            $this->container->db->update("usuarionotificacao", ["idNotificacaoCm" => $resposta["id"], "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacaoUsuario["oidUsuario"], "oidNotificacao" => $notificacaoUsuario["oidNotificacao"]]]);    
                        }else{
                            $this->container->db->update("usuarionotificacao", ["flgErro" => 1, "mensagemErro" => print_r($resposta["errors"], true), "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacaoUsuario["oidUsuario"], "oidNotificacao" => $notificacaoUsuario["oidNotificacao"]]]);    
                        }
                    }                    
                }

                return $response->withJson(["success" => true, "mensagens" => $respostas]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }    
        }

        //Envia notificações pendentes. De forma agrupada.
		//Rodar de 5 em 5 minutos
        public function enviarAgrupados(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                    
                $message = new OneSignalCm();
                $respostas = [];

                $valor = Parametro::getValor($this->container->db, "VALOR_MINIMO_NOTIFICACAO_AGRUPADA");
				$intervalo = Parametro::getValor($this->container->db, "VALOR_INTERVALO_ENTRE_NOTIFICACOES");
                $sql = "SELECT un.oidUsuario, u.idUserCloudMessage, COUNT(un.oidUsuario) AS totalNotificacoes, 
					GROUP_CONCAT(n.oidNotificacao) AS notificacoes
                    FROM usuarionotificacao un
                    INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
                    INNER JOIN usuario u ON u.oidUsuario = un.oidUsuario
                    WHERE n.flgAtivo = 1 AND n.flgProcessado = 0 AND un.flgReceber = 1 AND un.flgEnviado = 0
						AND (u.dataHoraUltimaNotificacao IS NULL OR u.dataHoraUltimaNotificacao < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL -" . $intervalo . " MINUTE))
                    GROUP BY un.oidUsuario
                    HAVING totalNotificacoes > " . $valor;

                $arrNotificacoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
				
                $baseMensagem = Parametro::getValor($this->container->db, "TEXTO_NOTIFICACAO_AGRUPADA");
                $tituloMensagem = Parametro::getValor($this->container->db, "TITULO_NOTIFICACAO_AGRUPADA");
				$qtdeMaximaItens = Parametro::getValor($this->container->db, "QTDE_MAXIMA_ITENS_PUSH");
                foreach($arrNotificacoes as $notificacao){
					$qtdeNotificacoes = intval($notificacao["totalNotificacoes"]);
					$textoQtde = ($qtdeNotificacoes > $qtdeMaximaItens ? ("mais de " . $qtdeMaximaItens) : $qtdeNotificacoes);
					
                    $mensagemNotificacao = str_replace("#CONTADOR#", $textoQtde, $baseMensagem);
                    
                    if($notificacao["idUserCloudMessage"] != ""){
                        $umaNotificacao = $this->templateNotificacao;
                        $umaNotificacao['headings']['en'] = $tituloMensagem;
                        $umaNotificacao['contents']['en'] = $mensagemNotificacao;
                        $umaNotificacao['include_player_ids'] = [$notificacao["idUserCloudMessage"]];
                        $umaNotificacao['data']["oidUsuario"] = $notificacao["oidUsuario"];

						$arrLimiteNotificacoes = explode(",", $notificacao["notificacoes"]);
						$arrLimiteNotificacoes = array_slice($arrLimiteNotificacoes, 0 ,30);
						$umaNotificacao['data']["notificacoes"] = implode(",", $arrLimiteNotificacoes);
						$umaNotificacao['data']["oidTipoMensagem"] = "2"; //MENSAGEM AGRUPADA POLITICO
						$umaNotificacao['data']["state"] = [
							"go" => "app.feed", 
							"params" => []
						];						
						
                        $resposta = $message->sendToDevice($umaNotificacao);
                        $respostas[] = $resposta;

                        // Identifica as notificações
                        $sql = "SELECT un.oidUsuario, un.oidNotificacao FROM usuarionotificacao un
                            INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
                            WHERE n.flgProcessado = 0 AND un.flgReceber = 1 AND un.flgEnviado = 0 AND un.oidUsuario = " . $notificacao["oidUsuario"];
                        $notificacoesUsuario = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

                        foreach($notificacoesUsuario as $notificacaoUsuario){
                            /*
                                Atualiza dados sobre envio
                                Este update tem uma trigger que verifica se todas as notificações foram enviadas
                            */
                            if(!isset($resposta["errors"]) && isset($resposta["id"])){
                                $this->container->db->update("usuarionotificacao", ["idNotificacaoCm" => $resposta["id"], "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacaoUsuario["oidUsuario"], "oidNotificacao" => $notificacaoUsuario["oidNotificacao"]]]);    
                            }else{
                                $this->container->db->update("usuarionotificacao", ["flgErro" => 1, "mensagemErro" => print_r($resposta["errors"], true), "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacaoUsuario["oidUsuario"], "oidNotificacao" => $notificacaoUsuario["oidNotificacao"]]]);    
                            }
                        }
                    }                    
                }
				
                //Envia individuais
                $respostasDetalhada = $this->detalheMensagem();
                $novasRespostas = array_merge($respostasDetalhada, $respostas);

                return $response->withJson(["success" => true, "mensagens" => $novasRespostas]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }    
        }
        
        //Envia notificações detalhadas.
		//Rodar de 5 em 5 minutos
        public function enviarDetalhada(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                    
                $respostas = $this->detalheMensagem();

                return $response->withJson(["success" => true, "mensagens" => $respostas]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }    
        }        
        
        private function detalheMensagem(){
            $message = new OneSignalCm();
            $respostas = [];
            $valor = Parametro::getValor($this->container->db, "TEMPO_MINIMO_INTERVALO_PUSH");
                $sql = "SELECT un.oidNotificacao, un.oidUsuario, u.idUserCloudMessage, p.arquivoFoto, n.titulo, n.texto, CONCAT(p.nome, ' ', (SELECT pa.sigla FROM partido pa WHERE pa.oidPartido = p.oidPartido), '-', p.uf) AS politico
                    FROM usuarionotificacao un
                    INNER JOIN notificacao n ON n.oidNotificacao = un.oidNotificacao
                    INNER JOIN usuario u ON u.oidUsuario = un.oidUsuario
                    INNER JOIN politico p ON p.oidPolitico = n.oidPolitico
                    WHERE n.flgAtivo = 1 AND n.flgProcessado = 0 AND un.flgReceber = 1 AND un.flgEnviado = 0
                    LIMIT 0, 1
                    ";
                
            $arrNotificacoes = $this->container->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            foreach($arrNotificacoes as $notificacao){

                //$tituloMensagem = $notificacao["politico"] . " " . $notificacao["titulo"];
				//substr($notificacao["texto"], (strlen($notificacao["texto"]) - strlen("#EMENTA#") - strrpos($notificacao["texto"], "#EMENTA#")) * -1);
                
				$mensagemNotificacao = []; 
				
				if($notificacao["tipo"] != ""){ //Mensagem possui tipo
					$mensagemNotificacao[] = $notificacao["tipo"];
				}
				
				if($notificacao["tema"] != ""){ //Mensagem possui tema
					$mensagemNotificacao[] = $notificacao["tema"];
				}		

				if($notificacao["texto"] != ""){ //Mensagem possui texto
					$mensagemNotificacao[] = $notificacao["texto"];
				}		

                if($notificacao["idUserCloudMessage"] != ""){
                    $umaNotificacao = $this->templateNotificacao;
                    $umaNotificacao['headings']['en'] = $notificacao["titulo"];
                    $umaNotificacao['contents']['en'] = implode("\r\n", $mensagemNotificacao);
                    $umaNotificacao['include_player_ids'] = [$notificacao["idUserCloudMessage"]];
                    $umaNotificacao['large_icon'] = $notificacao["arquivoFoto"];
                    $umaNotificacao['data']["oidUsuario"] = $notificacao["oidUsuario"];
                    $umaNotificacao['data']["oidNotificacao"] = $notificacao["oidNotificacao"];
					$umaNotificacao['data']["oidTipoMensagem"] = "3"; //MENSAGEM INDIVIDUAL POLITICO
					$umaNotificacao['data']["state"] = [
						"go" => "app.feed", 
						"params" => []
					];					
					
                    $umaNotificacao['buttons'][] = ["id" => "gostei", "text" => "Gostei", "icon" => "ic_stat_action_thumb_up"];
                    $umaNotificacao['buttons'][] = ["id" => "naogostei", "text" => "Não gostei", "icon" => "ic_stat_action_thumb_down"];
					
					
                    $resposta = $message->sendToDevice($umaNotificacao);
                    $respostas[] = $resposta;

                    if(!isset($resposta["errors"]) && isset($resposta["id"])){
                        $this->container->db->update("usuarionotificacao", ["idNotificacaoCm" => $resposta["id"], "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacao["oidUsuario"], "oidNotificacao" => $notificacao["oidNotificacao"]]]);    
                    }else{
                        $this->container->db->update("usuarionotificacao", ["flgErro" => 1, "mensagemErro" => print_r($resposta["errors"], true), "dataHoraEntrega" => date("Y-m-d H:i:s"), "flgEnviado" => 1], ["AND" => ["oidUsuario" => $notificacao["oidUsuario"], "oidNotificacao" => $notificacao["oidNotificacao"]]]);    
                    }

                }                    
            }
            return $respostas;
        }
		
		//Aparelho celular dá post nesta função
		public function registraAbertura(ServerRequestInterface $request, ResponseInterface $response, array $args){

            try{           

				$postVars = $request->getParsedBody();
                $decoded = $request->getAttribute("token");
                $message = new OneSignalCm();
                $notifications = $message->parse($postVars);

                $oidNotificacao = [];
				$oidTipoMensagem = [];
                $feed = new Feed($this->container);
                foreach($notifications as $oneNotification){
                    $oneNotification["ip"] = $_SERVER["REMOTE_ADDR"];
                    $oneNotification["oidUsuario"] = $decoded->id;
					
					if($oneNotification["isAppInFocus"] == "") $oneNotification["isAppInFocus"] = "0";
       
					$oidNotificacao[] = $oneNotification["oidNotificacao"];
					$oidTipoMensagem[] = $oneNotification["oidTipoMensagem"];
					
					//Notificação agrupada
					if(isset($oneNotification["notificacoes"]) && $oneNotification["notificacoes"] != ""){
						$oidNotificacao = explode(",", $oneNotification["notificacoes"]);
						foreach($oidNotificacao as $oid){
							$oneNotification["oidNotificacao"] = $oid;
							unset($oneNotification["notificacoes"]);

							$notificacaoFormatada = $this->parseNotificacao($oneNotification);
							$id = $this->container->db->insert("dadosnotificacao", $notificacaoFormatada);
							//$this->salvaLog(print_r($notificacaoFormatada, true));						
						}
					}else{ //Uma notificação
						$notificacaoFormatada = $this->parseNotificacao($oneNotification);
						$id = $this->container->db->insert("dadosnotificacao", $notificacaoFormatada);
						
						//$this->salvaLog("\r\n#################\r\n");
						//$this->salvaLog(print_r($this->container->db->log(), true));	
						//$this->salvaLog(print_r($notificacaoFormatada, true));	
						
						//Se usuário interage com a notificação, registra interação
						if(isset($oneNotification["actionID"])){
							$feed->registraLike($message->defineLike($oneNotification["actionID"]), $oneNotification["oidNotificacao"], $oneNotification["oidUsuario"]);
						}						
					}
					
                    //Se usuário interage com a notificação, registra interação
                    if(isset($oneNotification["actionID"])){
                        $feed->registraLike($message->defineLike($oneNotification["actionID"]), $oneNotification["oidNotificacao"], $oneNotification["oidUsuario"]);
                    }
                }
                
				return $response->withJson([
					"success" => true,
					"notificacoes" => $oidNotificacao,
					"tipoMensagem" => $oidTipoMensagem,
					"stateGo" => $oneNotification["stateGo"],
					"stateParams" => $oneNotification["stateParams"]
				]);
            }catch(\Exception $e){
                
                $notification["dados"] = print_r($e, true);
                $id = $this->container->db->insert("dadosnotificacao", $notification);  
                
				return $response->withJson([
					"success" => false,
					"message" => $e->getMessage(),
					"stateGo" => "app.feed",
					"stateParams" => []
				]);
            }
		}
		
		private function salvaLog($content){
			
			$fp = fopen("NotificacaoLog.txt","a");
			fwrite($fp,$content);
			fclose($fp);
		}
		
		private function parseNotificacao($oneNotification){
			$notificacaoFormatada = [
                "oidUsuario" => isset($oneNotification["oidUsuario"]) ? $oneNotification["oidUsuario"] : "",
				"oidNotificacao" => isset($oneNotification["oidNotificacao"]) ? $oneNotification["oidNotificacao"] : null, 
				"oidTipoMensagem" => isset($oneNotification["oidTipoMensagem"]) ? $oneNotification["oidTipoMensagem"] : null, 
				"dados" => isset($oneNotification["dados"]) ? $oneNotification["dados"] : "", 
				"dataHora" => isset($oneNotification["dataHora"]) ? $oneNotification["dataHora"] : "", 
				"ip" => isset($oneNotification["ip"]) ? $oneNotification["ip"] : "", 
				"tipoRecebimento" => isset($oneNotification["tipoRecebimento"]) ? $oneNotification["tipoRecebimento"] : "", 
				"type" => isset($oneNotification["type"]) ? $oneNotification["type"] : "", 
				"actionID" => isset($oneNotification["actionID"]) ? $oneNotification["actionID"] : "", 
				"isAppInFocus" => isset($oneNotification["isAppInFocus"]) ? $oneNotification["isAppInFocus"] : "", 
				"shown" => isset($oneNotification["shown"]) ? $oneNotification["shown"] : "", 
				"androidNotificationId" => isset($oneNotification["androidNotificationId"]) ? $oneNotification["androidNotificationId"] : "", 
				"displayType" => isset($oneNotification["displayType"]) ? $oneNotification["displayType"] : "", 
				"notificationId" => isset($oneNotification["notificationId"]) ? $oneNotification["notificationId"] : "", 
				"title" => isset($oneNotification["title"]) ? $oneNotification["title"] : "", 
				"body" => isset($oneNotification["body"]) ? $oneNotification["body"] : "", 
				"smallIcon" => isset($oneNotification["smallIcon"]) ? $oneNotification["smallIcon"] : "", 
				"largeIcon" => isset($oneNotification["largeIcon"]) ? $oneNotification["largeIcon"] : "", 
				"bigPicture" => isset($oneNotification["bigPicture"]) ? $oneNotification["bigPicture"] : "", 
				"smallIconAccentColor" => isset($oneNotification["smallIconAccentColor"]) ? $oneNotification["smallIconAccentColor"] : "", 
				"launchUrl" => isset($oneNotification["launchUrl"]) ? $oneNotification["launchUrl"] : "", 
				"sound" => isset($oneNotification["sound"]) ? $oneNotification["sound"] : "", 
				"ledColor" => isset($oneNotification["ledColor"]) ? $oneNotification["ledColor"] : "", 
				"lockScreenVisibility" => isset($oneNotification["lockScreenVisibility"]) ? $oneNotification["lockScreenVisibility"] : "", 
				"groupKey" => isset($oneNotification["groupKey"]) ? $oneNotification["groupKey"] : "", 
				"groupMessage" => isset($oneNotification["groupMessage"]) ? $oneNotification["groupMessage"] : "", 
				"fromProjectNumber" => isset($oneNotification["fromProjectNumber"]) ? $oneNotification["fromProjectNumber"] : "", 
				"rawPayLoad" => isset($oneNotification["rawPayLoad"]) ? $oneNotification["rawPayLoad"] : ""];
			return $notificacaoFormatada;
		}

		public function registraRecebimento(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				return $this->registraAbertura($request, $response, $args);
            }catch(\Exception $e){
				return $response->withJson([
					"success" => false,
					"message" => $e->getMessage()
				]);
            }	
		}			
		
    }
/*

            //Usuário clicou no botão GOSTEI
            $n1 = json_decode('{
                "action": {
                    "type": "ActionTaken",
                    "actionID": "gostei"
                },
                "notification": {
                    "isAppInFocus": false,
                    "shown": true,
                    "androidNotificationId": 1227916170,
                    "displayType": 0,
                    "payload": {
                        "notificationID": "fc2fb957-1f1d-4328-99f2-aec79beeb8aa",
                        "title": "Teste",
                        "body": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ",
                        "additionalData": {
                            "oidUsuario": "11",
                            "oidNotificacao": "2"
                        },
                        "smallIcon": "ic_stat_sr_cidadao",
                        "largeIcon": "http:\/\/www.camara.gov.br\/internet\/deputado\/bandep\/4930.jpg",
                        "ledColor": "00FF00FF",
                        "lockScreenVisibility": 1,
                        "groupKey": "SrCidadao",
                        "groupMessage": "$[notif_count] alertas",
                        "actionButtons": "[com.onesignal.OSNotificationPayload$ActionButton@98b7e4a, com.onesignal.OSNotificationPayload$ActionButton@81b81bb]",
                        "fromProjectNumber": "884092009918",
                        "priority": 0,
                        "rawPayload": ""
                    }
                }
            }', true);
            
            //Usuário apenas recebeu a notificação
            $n2 = json_decode('{
                    "isAppInFocus": false,
                    "shown": true,
                    "androidNotificationId": 1227916170,
                    "displayType": 0,
                    "payload": {
                        "notificationID": "fc2fb957-1f1d-4328-99f2-aec79beeb8aa",
                        "title": "Teste",
                        "body": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ",
                        "additionalData": {
                            "oidUsuario": "11",
                            "oidNotificacao": "2"
                        },
                        "smallIcon": "ic_stat_sr_cidadao",
                        "largeIcon": "http:\/\/www.camara.gov.br\/internet\/deputado\/bandep\/4930.jpg",
                        "ledColor": "00FF00FF",
                        "lockScreenVisibility": 1,
                        "groupKey": "SrCidadao",
                        "groupMessage": "$[notif_count] alertas",
                        "actionButtons": "[com.onesignal.OSNotificationPayload$ActionButton@98b7e4a, com.onesignal.OSNotificationPayload$ActionButton@81b81bb]",
                        "fromProjectNumber": "884092009918",
                        "priority": 0,
                        "rawPayload": ""
                    }
                }', true);    
                
            //Usuário clicou em um grupo de notificações
            $n3 = json_decode('{
                    "isAppInFocus": false,
                    "shown": true,
                    "androidNotificationId": 1227916170,
                    "displayType": 0,
                    "groupedNotifications": [
                        {
                            "notificationID": "fc2fb957-1f1d-4328-99f2-aec79beeb8aa",
                            "title": "Teste",
                            "body": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ",
                            "additionalData": {
                                "oidUsuario": "11",
                                "oidNotificacao": "2"
                            },
                            "smallIcon": "ic_stat_sr_cidadao",
                            "largeIcon": "http:\/\/www.camara.gov.br\/internet\/deputado\/bandep\/4930.jpg",
                            "ledColor": "00FF00FF",
                            "lockScreenVisibility": 1,
                            "groupKey": "SrCidadao",
                            "groupMessage": "$[notif_count] alertas",
                            "actionButtons": "[com.onesignal.OSNotificationPayload$ActionButton@98b7e4a, com.onesignal.OSNotificationPayload$ActionButton@81b81bb]",
                            "fromProjectNumber": "884092009918",
                            "priority": 0,
                            "rawPayload": ""
                        },
                        {
                            "notificationID": "fc2fb957-1f1d-4328-99f2-aec79beeb8aa",
                            "title": "Teste",
                            "body": "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. ",
                            "additionalData": {
                                "oidUsuario": "11",
                                "oidNotificacao": "4"
                            },
                            "smallIcon": "ic_stat_sr_cidadao",
                            "largeIcon": "http:\/\/www.camara.gov.br\/internet\/deputado\/bandep\/4930.jpg",
                            "ledColor": "00FF00FF",
                            "lockScreenVisibility": 1,
                            "groupKey": "SrCidadao",
                            "groupMessage": "$[notif_count] alertas",
                            "actionButtons": "[com.onesignal.OSNotificationPayload$ActionButton@98b7e4a, com.onesignal.OSNotificationPayload$ActionButton@81b81bb]",
                            "fromProjectNumber": "884092009918",
                            "priority": 0,
                            "rawPayload": ""
                        } 
                    ]                   
                }', true);  
*/