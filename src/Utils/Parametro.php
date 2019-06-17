<?php
    namespace MP\Utils;

	class Parametro{
	
			public static function getValor($db, $parametro){
				$parametro = $db->get("parametro", ["valor"], ["chave" => $parametro]);
				if(count($parametro) > 0) return $parametro["valor"];
				else throw new Exception("Parâmetro não encontrado: " . $parametro);
			}		
	
	}
?>