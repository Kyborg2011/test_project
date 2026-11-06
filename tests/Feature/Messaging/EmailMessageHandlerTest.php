<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Mail\NotificationEmail;
use App\Messaging\Exceptions\InvalidMessage;
use App\Messaging\Handlers\EmailMessageHandler;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class EmailMessageHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_sends_email_from_message(): void
    {
        $sent = $this->handler()->handle($this->body());

        $this->assertTrue($sent);
        Mail::assertSent(NotificationEmail::class, function (NotificationEmail $mail): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->messageSubject === 'File "a.pdf" has been deleted'
                && $mail->messageBody === "Hello,\nthe file was deleted.\n";
        });
    }

    public function test_duplicate_message_is_sent_once(): void
    {
        $this->assertTrue($this->handler()->handle($this->body()));
        $this->assertFalse($this->handler()->handle($this->body()));

        Mail::assertSentCount(1);
    }

    public function test_messages_with_different_ids_are_both_sent(): void
    {
        $this->handler()->handle($this->body(['message_id' => 'id-1']));
        $this->handler()->handle($this->body(['message_id' => 'id-2']));

        Mail::assertSentCount(2);
    }

    public function test_uses_amqp_message_id_when_body_has_none(): void
    {
        $body = $this->body(['message_id' => null]);

        $this->assertTrue($this->handler()->handle($body, 'amqp-id'));
        $this->assertFalse($this->handler()->handle($body, 'amqp-id'));
    }

    public function test_failed_send_is_not_remembered_as_processed(): void
    {
        /** @var Mailer&MockInterface $brokenMailer */
        $brokenMailer = Mockery::mock(Mailer::class);
        $brokenMailer->shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP is down'));
        $cache = $this->app->make(Cache::class);

        try {
            (new EmailMessageHandler($brokenMailer, $cache))->handle($this->body());
            $this->fail('The exception was not rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP is down', $exception->getMessage());
        }

        $this->assertTrue($this->handler()->handle($this->body()));
        Mail::assertSentCount(1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBodiesProvider(): iterable
    {
        yield 'not json' => ['{oops', 'not valid JSON'];
        yield 'json scalar' => ['"text"', 'must be a JSON object'];
        yield 'no message id' => [self::encode(['message_id' => null]), 'has no message_id'];
        yield 'no email section' => [self::encode(['email' => null]), 'has no "email" section'];
        yield 'bad recipient' => [self::encode(['email' => ['to' => 'nobody', 'subject' => 's', 'body' => 'b']]), '"email.to" must be a valid'];
        yield 'empty subject' => [self::encode(['email' => ['to' => 'a@b.cd', 'subject' => ' ', 'body' => 'b']]), '"email.subject" must be'];
        yield 'missing body' => [self::encode(['email' => ['to' => 'a@b.cd', 'subject' => 's']]), '"email.body" must be'];
    }

    #[DataProvider('invalidBodiesProvider')]
    public function test_rejects_invalid_message(string $body, string $expectedError): void
    {
        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessage($expectedError);

        try {
            $this->handler()->handle($body);
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_email_is_plain_text_with_given_subject_and_body(): void
    {
        $mail = new NotificationEmail('Subject <b>', "Line 1 <b>\nLine 2\n");

        $mail->assertHasSubject('Subject <b>');
        $mail->assertSeeInText("Line 1 <b>\nLine 2");
    }

    private function handler(): EmailMessageHandler
    {
        return $this->app->make(EmailMessageHandler::class);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function body(array $override = []): string
    {
        return self::encode($override);
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function encode(array $override = []): string
    {
        return json_encode(array_replace([
            'message_id' => '01a1b251-ca22-738f-abbe-7d7ad5a64e9a',
            'type' => 'file.deleted',
            'email' => [
                'to' => 'ops@example.com',
                'subject' => 'File "a.pdf" has been deleted',
                'body' => "Hello,\nthe file was deleted.\n",
            ],
        ], $override), JSON_THROW_ON_ERROR);
    }
}
