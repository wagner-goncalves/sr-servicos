<?php

    namespace MP\App;

    use phpFastCache\CacheManager;

    class Config
    {
        
		public static function ambienteDesenvolvimento(){
			$servidor = $_SERVER['SERVER_NAME'];
			if (strpos($servidor, 'desenv') !== false || strpos($servidor, 'dev') !== false || strpos($servidor, 'local') !== false) return true;
			else return false;
		}        
        
        public static function getLogSettings(){
            return [
                'name' => getenv("LOG_NAME"),
                'path' => __DIR__ . getenv("LOG_PATH"),
            ];
        }
        
        public static function getDatabaseSettings(){
            
			if(Config::ambienteDesenvolvimento()) return [
                'server' => getenv("DATABASE_SERVER"),
                'username' => getenv("DATABASE_USER"),
                'password' => getenv("DATABASE_PASSWORD"),
                'database' => getenv("DATABASE_NAME"),
                'charset' => getenv("DATABASE_CHARSET"),
            ];
			else return [
                'server' => getenv("DATABASE_SERVER_PRODUCAO"),
                'username' => getenv("DATABASE_USER_PRODUCAO"),
                'password' => getenv("DATABASE_PASSWORD_PRODUCAO"),
                'database' => getenv("DATABASE_NAME_PRODUCAO"),
                'charset' => getenv("DATABASE_CHARSET_PRODUCAO"),
            ];            
        }   
        
        public static function getAppSettings(){
            return [
                'settings' => [
                    'displayErrorDetails' => getenv("DISPLAY_ERROR_DETAILS"), // set to false in production
            
                    // Renderer settings
                    'renderer' => [
                        'template_path' => realpath("../") . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR
                    ]  
                ]
            ];
        }
        
        public static function getRendererSettings(){
            return [
                'template_path' => realpath("../") . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR,
            ];
        }
        
        public static function setContainer($container){
         
            // cache
            $container['cache'] = function ($c) {
                CacheManager::setDefaultConfig(array(
                    "path" => __DIR__ . getenv("CACHE_PATH")
                ));
                return CacheManager::getInstance('files');
            };            
            
            // view renderer
            $container['renderer'] = function ($c) {
                $settings = Config::getRendererSettings();
                return new Slim\Views\PhpRenderer($settings['template_path']);
            };
            
            // view renderer
            $container['db'] = function ($c) {
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
            $container['logger'] = function($c) {
                $settings = Config::getLogSettings();
                $logger = new \Monolog\Logger($settings['name']);
                $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], \Monolog\Logger::DEBUG));
                return $logger;
            }; 
            

            $container['mailer'] = function ($container) {
                $mailer = new \PHPMailer(true);

				if(Config::ambienteDesenvolvimento()){
					$mailer->Host = "localhost";
					$mailer->SMTPAuth = false;
					$mailer->SMTPSecure = "";
					$mailer->Port = 25;
					$mailer->Username = "";
					$mailer->Password = "";
					$mailer->isHTML(true);
                    $mailer->CharSet = "UTF-8";
				}else{
                    $mailer->isSMTP();
					$mailer->Host = getenv("SMTP_HOST"); // your email host, to test I use localhost and check emails using test mail server application (catches all  sent mails)
					$mailer->SMTPAuth = getenv("SMTP_AUTH"); // I set false for localhost
					$mailer->SMTPSecure = getenv("SMTP_SECURE"); // set blank for localhost
					$mailer->Port = getenv("SMTP_PORT"); // 25 for local host
					$mailer->Username = getenv("SMTP_USERNAME"); // I set sender email in my mailer call
					$mailer->Password = getenv("SMTP_PASSWORD");
					$mailer->isHTML(getenv("SMTP_ISHTML"));
                    $mailer->CharSet = "UTF-8";
					//$mailer->SMTPDebug = 2;
				}

                return new \MP\Utils\Mail\Mailer($mailer);
            };

                 
        }
    }