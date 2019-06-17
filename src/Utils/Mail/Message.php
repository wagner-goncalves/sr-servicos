<?php

namespace MP\Utils\Mail;

class Message
{
    protected $mailer;
    
    public function __construct($mailer)
    {
        $this->mailer = $mailer;
    }
    
    public function to($address)
    {
        $this->mailer->addAddress($address);   
    }
    public function subject($subject)
    {
        $this->mailer->Subject = $subject;
    }
    public function body($body)
    {
        $this->mailer->Body = $body;
    }
    
    public function altBody($altBody)
    {
        $this->mailer->AltBody = $altBody;
    }    
    
    public function from($from)     // if you want to add different sender email in mailer call.
    {
        $this->mailer->From = $from;
    }
    public function fromName($fromName) // if you want to add different sender name in mailer call.
    {
        $this->mailer->FromName = $fromName;
    }
    
    public function addReplyTo($address, $name)
    {
        $this->mailer->addReplyTo($address, $name);   
    }    
  
}
