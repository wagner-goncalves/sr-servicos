<?php
    namespace MP\Batch;
    
	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;    
    
    class Camara implements ICasaLegislativa
    {
        
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	        
        
        public function processaNotificacoes(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
                $this->processaPresenca();
                return $response->withJson(["success" => true]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }                  
        }
        
        private function processaPresenca(){
            // Cria notificação a partir do evento de ausência
            // Rodar de hora em hora?            
            $this->container->db->query("SET FOREIGN_KEY_CHECKS = 0");	
            $sql = "INSERT INTO notificacao (oidPolitico, oidPartido, oidInteresse, oidInstituicao, titulo, texto, justificativa)
                SELECT ideCadastro, (SELECT p.oidPartido FROM partido p WHERE p.sigla = siglaPartido), 2, 1,
                CASE descricaoFrequenciaDia 
                    WHEN descricaoFrequenciaDia = 'Presença' THEN CONCAT(nomeParlamentar, ' presente.')
                    WHEN descricaoFrequenciaDia = 'Ausência' THEN CONCAT(nomeParlamentar, ' ausente.')
                    WHEN descricaoFrequenciaDia = 'Ausência justificada' THEN CONCAT(nomeParlamentar, ' ausente com justificativa.')
                END,
                CASE descricaoFrequenciaDia 
                    WHEN descricaoFrequenciaDia = 'Presença' THEN CONCAT(nomeParlamentar, ' faltou ao trabalho em ', DATA, '.')
                    WHEN descricaoFrequenciaDia = 'Ausência' THEN CONCAT(nomeParlamentar, ' registrou presença em ', DATA, '.')
                    WHEN descricaoFrequenciaDia = 'Ausência justificada' THEN CONCAT(nomeParlamentar, ' faltou ao trabalho em ', DATA, ' com a justificativa \"' , justificativa, '\".')
                END,
                justificativa
                FROM camara_presenca 
                WHERE dataHoraProcessamento IS NULL
                ORDER BY DATA;
                UPDATE camara_presenca SET dataHoraProcessamento = CURRENT_TIMESTAMP";
            $this->container->db->query($sql);	
            $this->container->db->query("SET FOREIGN_KEY_CHECKS = 1");
            
            $error = $this->container->db->error();				
            if(intval($error[0]) > 0) throw new \Exception("Erro ao processar presenças.");		
        }
    }