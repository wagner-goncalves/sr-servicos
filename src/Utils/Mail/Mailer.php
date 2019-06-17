<?php

namespace MP\Utils\Mail;

class Mailer
{
    protected $mailer;
    
    public function __construct($mailer)
    {
        $this->mailer = $mailer;
    }
    
    public function send($template, $data, $callback)
    {
        $message = new Message($this->mailer);
        
        $message->body($this->fetch($template, $data));
        
        call_user_func($callback, $message);
        
        $success = $this->mailer->send();
		//print_r($this->mailer);
		//echo "#";
		//echo $this->mailer->ErrorInfo;
		return $success;
    }
	
	private function fetch($template, $data){
		$filledTemplate = $template;
		foreach($data as $chave => $valor){
			$filledTemplate = str_replace($chave, $valor, $filledTemplate);
		}
		return $filledTemplate;
	}
    
    public function clearAllRecipients()
    {
        $this->mailer->clearAllRecipients();
    }      
}
