<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class GmailTransport extends EsmtpTransport
{
    public function __construct()
    {
        parent::__construct('smtp.gmail.com', 587, true);
        
        $this->setUsername('m4rvl.net@gmail.com');
        $this->setPassword('eruj qhms jhaa mhyp');
        
        // Get the stream and disable SSL verification
        $stream = $this->getStream();
        if ($stream instanceof SocketStream) {
            $streamOptions = $stream->getStreamOptions();
            $streamOptions['ssl']['verify_peer'] = false;
            $streamOptions['ssl']['verify_peer_name'] = false;
            $streamOptions['ssl']['allow_self_signed'] = true;
            $stream->setStreamOptions($streamOptions);
        }
    }
}