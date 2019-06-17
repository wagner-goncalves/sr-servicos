<?php
    namespace MP\Middleware;
    
    use MySQLHandler\MySQLHandler;
    use MP\App\Config;    

	class Environment{
        
        private $container;
        
        public function __construct($container) {
            $this->container = $container;
        }           
		
		public function __invoke($request, $response, $next){			
            
			// view renderer
			$this->container['renderer'] = function ($c) {
				$settings = Config::getRendererSettings();
				return new Slim\Views\PhpRenderer($settings['template_path']);
			};
            
			// view renderer
			$this->container['db'] = function ($c) {
				$settings = Config::getDatabaseSettings();
				$db = new \MP\Model\Database(array(
					'database_type' => 'mysql',
					'database_name' => $settings["database"],
					'server' => $settings["server"],
					'username' => $settings["username"],
					'password' => $settings["password"],
					'charset' => $settings["charset"],
				));
				if(!$db) throw new Exception();
				else return $db;
			};

			// monolog
			$this->container['logger'] = function($c) {
				$settings = Config::getLogSettings();
				$logger = new \Monolog\Logger($settings['name']);
				$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
				$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], \Monolog\Logger::DEBUG));
				return $logger;
			};   

            $response = $next($request, $response);
            return $response;                     
		}
	}
?>