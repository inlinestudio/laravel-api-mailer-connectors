<?php

namespace InlineStudio\MailConnectors\Mailers\O365\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use InlineStudio\MailConnectors\Mailers\O365\Office365Connector;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class Office365MailTransport extends AbstractTransport
{
    /**
     * The O365 API client.
     */
    protected Office365Connector $client;
 
    /**
     * Create a new O365 transport instance.
     */
    public function __construct(Office365Connector $client, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->client = $client;
        parent::__construct($dispatcher, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $this->client->sendMessageRequest($email);
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'O365';
    }
}