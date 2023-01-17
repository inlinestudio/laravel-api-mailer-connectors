<?php

namespace InlineStudio\MailConnectors\Mailers\O365;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Message;
use Microsoft\Graph\Model\UploadSession;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Str;
use GuzzleHttp\Client as GuzzleClient;

class Office365Connector
{
    /**
     * The O365 API client.
     */
    protected Graph $client;

    protected string $clientId;
    protected string $clientSecret;
    protected string $tenant;

    protected const BYTE_TO_MB = 1048576;

    /**
     * Create a new O365 transport instance.
     */
    public function __construct(string $clientId, string $clientSecret, string $tenant)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tenant = $tenant;

        $this->client = new Graph;
        $this->client->setAccessToken($this->getAccessToken());
    }

    protected function getAccessToken(): string
    {
        $guzzle = new GuzzleClient();
        $url = 'https://login.microsoftonline.com/' . $this->tenant . '/oauth2/v2.0/token';
        $response = $guzzle->post(
            $url,
            [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]
        )->getBody()->getContents();

        $token = json_decode($response);

        return $token->access_token;
    }

    /**
     * Send a request to the API to send out the email.
     */
    public function sendMessageRequest(Email $message): Message
    {
        // If the whole message body is bigger than 4MB we'll handle it differently
        if ($this->getBodySize($message) >= 4) {
            // Create a draft message
            $draft = $this->createDraftMessage($message);
            $this->uploadLargeAttachments($message, $draft->getId());

            // Send the message
            return $this->client->createRequest("POST", "/users/" . (current($message->getFrom())->getAddress()) . "/messages/" . $draft->getId() . "/send")
                ->setReturnType(Message::class)
                ->execute();
        }

        return $this->client->createRequest("POST", "/users/" . (current($message->getFrom())->getAddress()) . "/sendmail")
            ->attachBody($this->getBody($message))
            ->setReturnType(Message::class)
            ->execute();
    }

    protected function createDraftMessage(Email $message): Message
    {
        return $this->client->createRequest("POST", "/users/" . (current($message->getFrom())->getAddress()) . "/messages")
            ->attachBody($this->getBody($message, false, true))
            ->setReturnType(Message::class)
            ->execute();
    }

    protected function uploadLargeAttachments(Email $message, string $draftId): void
    {
        foreach ($message->getAttachments() as $attachment) {
            $fileName = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
            $content = $attachment->getBody();
            $fileSize = strlen($content);
            $size = $fileSize / self::BYTE_TO_MB; //byte -> mb
            $id = Str::random(10);

            if ($size <= 3) {
                $attachmentBody = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $fileName,
                    'contentType' => $attachment->getPreparedHeaders()->get('Content-Type')->getBody(),
                    'contentBytes' => base64_encode($attachment->getBody()),
                    'contentId' => $id
                ];

                $this->client->createRequest("POST", "/users/" . (current($message->getFrom())->getAddress()) . "/messages/" . $draftId . "/attachments")
                    ->attachBody($attachmentBody)
                    ->setReturnType(UploadSession::class)
                    ->execute();
            } else {
                $this->chunkUpload($message, $draftId, $fileName, $content, $fileSize);
            }
        }
    }

    protected function chunkUpload(Email $message, string $draftId, string $fileName, string $content, float $fileSize): void
    {
        $attachmentMessage = [
            'AttachmentItem' => [
                'attachmentType' => 'file',
                'name' => $fileName,
                'size' => $fileSize,
            ]
        ];

        $uploadSession = $this->client->createRequest("POST", "/users/" . (current($message->getFrom())->getAddress()) . "/messages/" . $draftId . "/attachments/createUploadSession")
            ->attachBody($attachmentMessage)
            ->setReturnType(UploadSession::class)
            ->execute();

        $fragSize =  1024 * 1024 * 4; //4mb at once...
        $numFragments = ceil($fileSize / $fragSize);
        $contentChunked = str_split($content, $fragSize);
        $bytesRemaining = $fileSize;
        $guzzle = new GuzzleClient();

        $i = 0;
        while ($i < $numFragments) {
            $chunkSize = $numBytes = $fragSize;
            $start = $i * $fragSize;
            $end = $i * $fragSize + $chunkSize - 1;
            if ($bytesRemaining < $chunkSize) {
                $chunkSize = $numBytes = $bytesRemaining;
                $end = $fileSize - 1;
            }

            $data = $contentChunked[$i];
            $contentRange = "bytes {$start}-{$end}/{$fileSize}";
            $headers = [
                'Content-Length' => $numBytes,
                'Content-Range' => $contentRange
            ];

            $guzzle->put($uploadSession->getUploadUrl(), [
                'headers'         => $headers,
                'body'            => $data,
                'allow_redirects' => false,
                'timeout'         => 1000
            ]);

            $bytesRemaining = $bytesRemaining - $chunkSize;
            $i++;
        }
    }

    protected function getBodySize(Email $message): float
    {
        $messageBody = $this->getBody($message, true);
        $messageBodyLength = mb_strlen(json_encode($messageBody, JSON_NUMERIC_CHECK), '8bit');

        return $messageBodyLength / self::BYTE_TO_MB; //byte -> mb
    }

    /**
     * Get body for the message.
     */
    protected function getBody(Email $message, bool $withAttachments = false, bool $isDraft = false): array
    {
        $messageData = [
            'from' => [
                'emailAddress' => [
                    'address' => current($message->getFrom())->getAddress(),
                    'name' => current($message->getFrom())->getName(),
                ]
            ],
            'toRecipients' => $this->getTo($message),
            'ccRecipients' => $this->getCc($message),
            'bccRecipients' => $this->getBcc($message),
            'replyTo' => $this->getReplyTo($message),
            'subject' => $message->getSubject(),
            'body' => [
                'contentType' => $message->getHtmlBody() ? 'html' : 'text',
                'content' => $message->getHtmlBody() ?: $message->getTextBody()
            ]
        ];

        if (!$isDraft) {
            $messageData = ['message' => $messageData];
        }

        if (count($message->getAttachments()) > 0 && $withAttachments) {
            $attachments = [];
            foreach ($message->getAttachments() as $attachment) {
                $attachments[] = [
                    "@odata.type" => "#microsoft.graph.fileAttachment",
                    "name" => $attachment->getFilename(),
                    "contentType" => $attachment->getContentType(),
                    "contentBytes" => base64_encode($attachment->getBody()),
                    'contentId'    => $attachment->getContentId()
                ];
            }
            $messageData['message']['attachments'] = $attachments;
        }

        return $messageData;
    }

    /**
     * Get the "to" payload field for the API request.
     */
    protected function getTo(Email $message): array
    {
        return collect($message->getTo())->map(
            fn (Address $recipient) => [
                'emailAddress' => [
                    'address' => $recipient->getAddress(),
                    'name' => $recipient->getName(),
                ]
            ]
        )->values()->toArray();
    }

    /**
     * Get the "Cc" payload field for the API request.
     */
    protected function getCc(Email $message): array
    {
        return collect($message->getCc())->map(
            fn (Address $cc)  => [
                'emailAddress' => [
                    'address' => $cc->getAddress(),
                    'name' => $cc->getName(),
                ]
            ]
        )->values()->toArray();
    }

    /**
     * Get the "replyTo" payload field for the API request.
     */
    protected function getReplyTo(Email $message): array
    {
        return collect($message->getReplyTo())->map(
            fn (Address $replyTo) =>
            [
                'emailAddress' => [
                    'address' => $replyTo->getAddress(),
                    'name' => $replyTo->getName(),
                ]
            ]
        )->values()->toArray();
    }

    /**
     * Get the "Bcc" payload field for the API request.
     */
    protected function getBcc(Email $message): array
    {
        return collect($message->getBcc())->map(
            fn (Address $bcc) => [
                'emailAddress' => [
                    'address' => $bcc->getAddress(),
                    'name' => $bcc->getName(),
                ]
            ]
        )->values()->toArray();
    }
}
