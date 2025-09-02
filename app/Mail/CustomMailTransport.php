<?php

namespace App\Mail;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class CustomMailTransport
{
    public static function createTransport($config)
    {
        $transport = new EsmtpTransport(
            $config['host'],
            $config['port'],
            $config['encryption'] === 'tls'
        );
        
        $transport->setUsername($config['username']);
        $transport->setPassword($config['password']);
        
        // Get the stream and configure SSL options
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setStreamOptions([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
        }
        
        return $transport;
    }
}