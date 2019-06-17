<?php

namespace MP\Utils\Image;

class Download
{
    
    public function __construct()
    {

    }

	//Download de fotos dos políticos	
    public function copyFromUrl($url, $destino){


		if(!file_exists($destino)){
			$header = [
				'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Encoding:gzip, deflate, sdch',
				'Accept-Language:pt-BR,pt;q=0.8,en-US;q=0.6,en;q=0.4',
				'Connection:keep-alive',
				'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36'
				];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_URL,$url);
			$result = curl_exec($ch);
			
			if($result){
				$fp = fopen($destino, "w");
				fwrite($fp, $result);
				fclose($fp);
				return true;
			}
			return false;
		}
        return true;
    }
}
