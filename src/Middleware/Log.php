<?php
    namespace MP\Middleware;
    
    use MySQLHandler\MySQLHandler;
    
    class Log
    {
        
        private $pdo;
        private $settings;
        
        public function __construct(\PDO $pdo, $settings) {
            $this->pdo = $pdo;
            $this->settings = $settings;
        }        
        
        public function __invoke($request, $response, $next){
            $mySQLHandler = new MySQLHandler($this->pdo, "logrequisicao", array("get", "post"), \Monolog\Logger::DEBUG);
            $logger = new \Monolog\Logger($request->getUri()->getPath());
            $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
            $logger->pushHandler($mySQLHandler);
            $logger->addInfo(print_r($request->getHeaders(), true), 
                array("get" => print_r($request->getQueryParams(), true), "post" => print_r($request->getParsedBody(), true)
            ));   
            $response = $next($request, $response);
            return $response;
        }    
    }