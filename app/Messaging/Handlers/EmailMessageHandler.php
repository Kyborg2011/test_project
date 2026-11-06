<?php

declare(strict_types=1);

namespace App\Messaging\Handlers;

use App\Mail\NotificationEmail;
use App\Messaging\Exceptions\InvalidMessage;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Mail\Mailer;
use JsonException;

/**
 * Turns a "send an e-mail" message from the queue into a real e-mail sent through Laravel Mail
 * (with MAIL_MAILER=log the letter is written to storage/logs/mail.log instead of SMTP).
 */
final readonly class EmailMessageHandler
{
    /** Processed message ids are remembered this long to drop redelivered duplicates. */
    private const int DEDUPLICATION_TTL_SECONDS = 7 * 24 * 3600;

    public function __construct(
        private Mailer $mailer,
        private Cache $cache,
    ) {
    }

    /**
     * @param string|null $messageId The AMQP message_id property, used when the body has none
     *
     * @return bool true if the e-mail was sent; false if the message was a duplicate
     *
     * @throws InvalidMessage
     */
    public function handle(string $body, ?string $messageId = null): bool
    {
        $data = $this->decode($body);
        $messageId = $this->messageId($data, $messageId);
        $email = $this->email($data);

        $key = 'messages:processed:' . $messageId;

        if ($this->cache->has($key)) {
            return false;
        }

        $this->mailer->to($email['to'])->send(new NotificationEmail($email['subject'], $email['body']));

        // Remembered only after a successful send, so a failed attempt can be processed again.
        $this->cache->put($key, true, self::DEDUPLICATION_TTL_SECONDS);

        return true;
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidMessage('The message body is not valid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (! is_array($data)) {
            throw new InvalidMessage('The message body must be a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    private function messageId(array $data, ?string $fallback): string
    {
        $messageId = $data['message_id'] ?? $fallback;

        if (! is_string($messageId) || trim($messageId) === '') {
            throw new InvalidMessage('The message has no message_id.');
        }

        return $messageId;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{to: string, subject: string, body: string}
     */
    private function email(array $data): array
    {
        $email = $data['email'] ?? null;

        if (! is_array($email)) {
            throw new InvalidMessage('The message has no "email" section.');
        }

        $to = $email['to'] ?? null;
        $subject = $email['subject'] ?? null;
        $body = $email['body'] ?? null;

        if (! is_string($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidMessage('"email.to" must be a valid e-mail address.');
        }

        if (! is_string($subject) || trim($subject) === '') {
            throw new InvalidMessage('"email.subject" must be a non-empty string.');
        }

        if (! is_string($body) || trim($body) === '') {
            throw new InvalidMessage('"email.body" must be a non-empty string.');
        }

        return ['to' => $to, 'subject' => $subject, 'body' => $body];
    }
}
