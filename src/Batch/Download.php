<?php
	namespace MP\Batch;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;

	class Download{

		protected $container;
        
		public function __construct($container){
			$this->container = $container;
		}
		
		public function imagemPoliticosMG(ServerRequestInterface $request, ResponseInterface $response, array $args){
			set_time_limit(60 * 10);
			$arrPoliticos = $this->container->db->select("politico", ["oidPolitico", "arquivoFoto"], ["oidInstituicao" => 3]);
            $download = new \MP\Utils\Image\Download();
			$folder = realpath(".") . DIRECTORY_SEPARATOR . getenv("UPLOAD_FOLDER_POLITICOS") . DIRECTORY_SEPARATOR;
			$arquivosProcessados = [];
			
            foreach($arrPoliticos as $politico){
				if($politico["arquivoFoto"] != ""){
					$file = "almg-" . basename($politico["arquivoFoto"]);
					$caminho = $folder . $file;
					$success = $download->copyFromUrl($politico["arquivoFoto"], $caminho);
					
					$arquivosProcessados[] = [
						"oidPolitico" => $politico["oidPolitico"], 
						"arquivoFoto" => $politico["arquivoFoto"], 
						"arquivoFotoLocal" => $file,
						"caminhoAbsoluto" => $caminho,
						"success" => $success
					];
					
					if($success){
						$this->container->db->update("politico", ["arquivoFotoLocal" => $file], ["oidPolitico" => $politico["oidPolitico"]]);
					}
				}
            }
			return $response->withJson($arquivosProcessados);		
		}	


		
		public function imagemPoliticosRS(ServerRequestInterface $request, ResponseInterface $response, array $args){
			set_time_limit(60 * 10);
			$arrPoliticos = $this->container->db->select("politico", ["oidPolitico", "arquivoFoto"], ["oidInstituicao" => 4]);
            $download = new \MP\Utils\Image\Download();
			$folder = realpath(".") . DIRECTORY_SEPARATOR . getenv("UPLOAD_FOLDER_POLITICOS") . DIRECTORY_SEPARATOR;
			$arquivosProcessados = [];
			
            foreach($arrPoliticos as $politico){
				if($politico["arquivoFoto"] != ""){

					$file = "alrs-" . basename($politico["arquivoFoto"]);

					$unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
					'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
					'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
					'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
					'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
					$file = strtr( $file, $unwanted_array );

					$caminho = $folder . $file;
					$success = $download->copyFromUrl($politico["arquivoFoto"], $caminho);
					
					$arquivosProcessados[] = [
						"oidPolitico" => $politico["oidPolitico"], 
						"arquivoFoto" => $politico["arquivoFoto"], 
						"arquivoFotoLocal" => $file,
						"caminhoAbsoluto" => $caminho,
						"success" => $success
					];
					
					if($success){
						$this->container->db->update("politico", ["arquivoFotoLocal" => $file], ["oidPolitico" => $politico["oidPolitico"]]);
					}
				}
            }
			return $response->withJson($arquivosProcessados);		
		}			

	}
?>