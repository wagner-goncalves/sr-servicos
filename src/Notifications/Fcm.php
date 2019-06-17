<?php
	namespace MP\Notifications;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    
    use paragraph1\phpFCM\Client;
    use paragraph1\phpFCM\Message;
    use paragraph1\phpFCM\Recipient\Topic;
    use paragraph1\phpFCM\Notification;    
    
    class Fcm
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	

		public function send(ServerRequestInterface $request, ResponseInterface $response, array $args){
            
            $apiKey = 'AIzaSyDxXFJiMMSzciRpRuFd9fOWJFaWz7kMJIg';
            $client = new Client();
            $client->setApiKey($apiKey);
            $client->injectHttpClient(new \GuzzleHttp\Client());
            
            $message = new Message();
            $message->addRecipient(new Topic('all'));
            
            $notification = new Notification('test title', 'testing body');
            $notification->setIcon("ic_stat_sr_cidadao");
            
            $message->setNotification($notification)
                ->setData(array('someId' => 111));
            
            $fcmResponse = $client->send($message);
            
			return $response->withJson(["success" => $fcmResponse->getStatusCode()]);		
		}
 
    }
    