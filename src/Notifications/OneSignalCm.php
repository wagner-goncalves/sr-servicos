<?php
	namespace MP\Notifications;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    
	use GuzzleHttp\Client as GuzzleClient;
	use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
	use Http\Client\Common\HttpMethodsClient as HttpClient;
	use Http\Message\MessageFactory\GuzzleMessageFactory;	
	
	use OneSignal\Config;
	use OneSignal\Devices;
	use OneSignal\OneSignal;

    class OneSignalCm implements ICloudMessage
    {
		protected $container;
        
        private function getCmApi(){
			//Configurações
			$config = new Config();
			$config->setApplicationId(getenv("ONESIGNAL_APP_ID"));
			$config->setApplicationAuthKey(getenv("ONESIGNAL_APP_KEY"));
			//Http client
			$guzzle = new GuzzleClient([
				// ..config
			]);

			$client = new HttpClient(new GuzzleAdapter($guzzle), new GuzzleMessageFactory());
            return new OneSignal($config, $client);
        }
        
		//Adiciona device no OneSignal
        public function addDevice(array $device){
            $api = $this->getCmApi();
			return $api->devices->add($device);
        }        
        
		//Envia uma notificação
        public function sendToDevice(array $notification){
            $api = $this->getCmApi();
			return $api->notifications->add($notification);	
        }
        
        public function sendToAll(array $notification){
            $api = $this->getCmApi();
			return $api->notifications->add($notification);		            
        }   
        
        public function defineLike($like){
            return $like == "gostei" ? 1 : 0;
        }
        
		//Identifica notificações agrupadas e indivuais e devolve um vetor plano com notificações normalizadas
        public function parse($postVars){
			
            $dadosActionNotificacao = [];
            $notificationData = [];
			$dadosEspecificosNotificacao = [];
            
            if(isset($postVars["action"])){ //Ação de CLICK em GOSTEI/NÃO GOSTEI em notificação individual
                $dadosActionNotificacao = [
                    //OSNotificationAction.action
                    "type" => $postVars["action"]["type"], 
                    "actionID" => $postVars["action"]["type"] != "0" ? $postVars["action"]["actionID"] : null,
                    "tipoRecebimento" => 1
                ];
                
                //Se é uma ação de click recupera a notificação do local correto
                $notificationData = $postVars["notification"];
				$dadosEspecificosNotificacao[] = $postVars["notification"]["payload"];
            }else if(!isset($postVars["groupedNotifications"])){ //Apenas recebimento da notificação, sem nenhuma ação do usuário
                //Se é uma ação de recebimento, recupera a notificação do local correto
                $dadosActionNotificacao = [
                    "tipoRecebimento" => 0
                ];
                $notificationData = $postVars; 
				$dadosEspecificosNotificacao[] = $postVars["payload"];				
            }else if(isset($postVars["groupedNotifications"])){ //Clicou no grupo de notificações
                $dadosActionNotificacao = [
                    "tipoRecebimento" => 1
                ];
				$notificationData = $postVars; 
				$dadosEspecificosNotificacao = $postVars["groupedNotifications"];
			}
            
            $dadosBaseNotificacao = [
                //Dados personalizados
                "dados" => json_encode($postVars), 
                "dataHora" => date("Y-m-d H:i:s"), 
                //OSNotification.notification
                "isAppInFocus" => $notificationData["isAppInFocus"], 
                "shown" => $notificationData["shown"], 
                "androidNotificationId" => $notificationData["androidNotificationId"], 
                "displayType" => $notificationData["displayType"]
            ]; 

            $notificacoesFormatadas = [];
            foreach($dadosEspecificosNotificacao as $notification){
                $dadosEspecificos = [
                    //OSNotification.notification.payload
                    "notificationId" => $notification["notificationID"], 
                    "title" => $notification["title"], 
                    "body" => $notification["body"], 
                    "smallIcon" => $notification["smallIcon"], 
                    "largeIcon" => $notification["largeIcon"], 
                    //"bigPicture" => $notification["bigPicture"], 
                    //"smallIconAccentColor" => $notification["smallIconAccentColor"], 
                    //"launchUrl" => $notification["launchUrl"], 
                    //"sound" => $notification["sound"], 
                    "ledColor" => $notification["ledColor"], 
                    "lockScreenVisibility" => $notification["lockScreenVisibility"], 
                    "groupKey" => $notification["groupKey"], 
                    "groupMessage" => $notification["groupMessage"], 
                    "fromProjectNumber" => $notification["fromProjectNumber"], 
                    //"priority" => $notification["priority"], 
                    "rawPayLoad" => $notification["rawPayload"], 
                    
                    //OSNotification.notification.payload.additionalData
                    "oidUsuario" => $notification["additionalData"]["oidUsuario"],
                    "oidNotificacao" => $notification["additionalData"]["oidNotificacao"],
					"oidTipoMensagem" => $notification["additionalData"]["oidTipoMensagem"],
					"stateGo" => $notification["additionalData"]["state"]["go"],
					"stateParams" => $notification["additionalData"]["state"]["params"],
                    "notificacoes" => isset($notification["additionalData"]["notificacoes"]) ? $notification["additionalData"]["notificacoes"] : ""
                ]; 
                $notificacoesFormatadas[] = array_merge($dadosActionNotificacao, $dadosBaseNotificacao, $dadosEspecificos);
            }
			
			
			
            return $notificacoesFormatadas;
        }     

		public function send(ServerRequestInterface $request, ResponseInterface $response, array $args){
			//Envia notificação
			$cmResponse = $this->sendToAll([
				'headings' => [
					'en' => 'Oi, fofinha'
				],
				'contents' => [
					'en' => 'Amo vc'
				],
                'include_player_ids' => ['b77f8d23-3e6e-4587-ae96-8d195a57a68a'],
				//'included_segments' => ['All'],
				'data' => ["oidUsuario" => "1", "oidNotificacao" => "1"],
				'buttons' => [
					["id" => "id1", "text" => "button1", "icon" => "ic_menu_share"], 
					["id" => "id2", "text" => "button2", "icon" => "ic_menu_send"]
				],
				'small_icon' => 'ic_stat_sr_cidadao',
				'large_icon' => 'http://www.srcidadao.com.br/servicos/public/politicos/101309.jpg',
				'android_led_color' => '00FF00FF',
				'android_group' => 'SrCidadao',
				'android_group_message' => ["en" => "$[notif_count] alertas"]
			]);		
            
			return $response->withJson(["response" => $cmResponse]);		
		}
 
    }
    