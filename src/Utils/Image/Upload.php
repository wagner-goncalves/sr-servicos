<?php

namespace MP\Utils\Image;

class Upload
{
    
    public function __construct()
    {

    }
    
    function base64ToJpeg($base64String, $outputFile) {
        $ifp = fopen($outputFile, "wb"); 
        $data = explode(',', $base64String);
        fwrite($ifp, base64_decode($data[1])); 
        fclose($ifp); 
        return $outputFile; 
    }
}
