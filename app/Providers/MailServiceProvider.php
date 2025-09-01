<?php

namespace App\Providers;

use Illuminate\Mail\MailServiceProvider as BaseMailServiceProvider;
use Swift_SmtpTransport;

class MailServiceProvider extends BaseMailServiceProvider
{
    /**
     * Register the Swift Transport instance.
     *
     * @param  array  $config
     * @return \Swift_SmtpTransport
     */
    protected function createSmtpDriver(array $config)
    {
        $transport = new Swift_SmtpTransport($config['host'], $config['port']);

        if (! empty($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        if (! empty($config['username'])) {
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password']);
        }

        if (! empty($config['timeout'])) {
            $transport->setTimeout($config['timeout']);
        }

        // Disable SSL verification for Gmail
        $transport->setStreamOptions([
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return $transport;
    }
}