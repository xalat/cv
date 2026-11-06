<?php

namespace App\Tests\Functional;

use App\Controller\ContactController;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactTest extends WebTestCase
{
    private KernelBrowser $client;
    private const array FORM = ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'A project to discuss.', 'website' => ''];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->get('cache.contact_limiter')->clear();
    }

    public function testPublicFormSendsSynchronouslyWithFixedAddresses(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function (Email $email): bool {
            self::assertSame('site@example.com', $email->getFrom()[0]->getAddress());
            self::assertSame('recipient@example.com', $email->getTo()[0]->getAddress());
            self::assertSame('visitor@example.com', $email->getReplyTo()[0]->getAddress());
            self::assertSame('Nouveau message depuis le site CV', $email->getSubject());
            self::assertSame("Nom : Visitor\n\nA project to discuss.", $email->getTextBody());
            self::assertNull($email->getHtmlBody());

            return true;
        }));
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->submit(array_map(fn (string $value) => '  '.$value.'  ', self::FORM));
        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['message' => 'Votre message a été transmis. Merci pour votre prise de contact.'],
            $this->responseData(),
        );
        self::assertStringNotContainsString('recipient@example.com', $this->client->getResponse()->getContent());
    }

    #[DataProvider('invalidForms')]
    public function testInvalidFormsNeverSendMail(array $changes, ?string $field): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->submit(array_replace(self::FORM, $changes));
        self::assertResponseStatusCodeSame(422);
        if ($field !== null) {
            self::assertArrayHasKey($field, $this->responseData()['errors']);
        }
    }

    public static function invalidForms(): iterable
    {
        yield 'blank name' => [['name' => '   '], 'name'];
        yield 'long name' => [['name' => str_repeat('é', 101)], 'name'];
        yield 'invalid email' => [['email' => 'not-an-email'], 'email'];
        yield 'long email' => [['email' => str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.example'], 'email'];
        yield 'header injection' => [['email' => "visitor@example.com\r\nBcc: victim@example.com"], 'email'];
        yield 'blank message' => [['message' => "\n\t"], 'message'];
        yield 'long message' => [['message' => str_repeat('x', 5001)], 'message'];
        yield 'wrong type' => [['message' => ['text']], null];
        yield 'null value' => [['name' => null], null];
        yield 'recipient override' => [['to' => 'victim@example.com'], null];
        yield 'honeypot' => [['website' => 'https://spam.example.com'], null];
    }

    public function testMissingFieldsAreReported(): void
    {
        $this->submit([]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['name', 'email', 'message'], array_keys($this->responseData()['errors']));
    }

    public function testLimitsAcceptExactlyMaximumLengths(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->submit(array_replace(self::FORM, [
            'name' => str_repeat('é', 100),
            'email' => str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.museum',
            'message' => str_repeat('é', 5000),
        ]));
        self::assertResponseStatusCodeSame(200);
    }

    public function testMalformedAndNonObjectJsonAreRejected(): void
    {
        foreach (['{', '[]', 'null', '"text"'] as $json) {
            $this->client->request('POST', '/api/contact', server: ['CONTENT_TYPE' => 'application/json'], content: $json);
            self::assertResponseStatusCodeSame(400);
        }
    }

    public function testWrongContentTypeIsRejected(): void
    {
        $this->client->request('POST', '/api/contact', content: 'name=Visitor');
        self::assertResponseStatusCodeSame(415);
    }

    public function testOversizedPayloadIsRejected(): void
    {
        $this->submit(array_replace(self::FORM, ['message' => str_repeat('x', 32768)]));
        self::assertResponseStatusCodeSame(413);
    }

    public function testTransportFailureIsNotReportedAsSuccess(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('Sensitive SMTP transcript'));
        static::getContainer()->set(MailerInterface::class, $mailer);
        $this->submit(self::FORM);
        self::assertResponseStatusCodeSame(503);
        self::assertStringContainsString('n’a pas pu être envoyé', $this->responseData()['message']);
        self::assertStringNotContainsString('Sensitive SMTP transcript', $this->client->getResponse()->getContent());
    }

    public function testMissingConfigurationDisablesSending(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Contact mail configuration is missing.');
        $controller = new ContactController(
            $mailer,
            static::getContainer()->get(ValidatorInterface::class),
            $logger,
            static::getContainer()->get('limiter.contact'),
            'site@example.com',
            '',
        );
        $response = $controller(Request::create(
            '/api/contact', 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::FORM, JSON_THROW_ON_ERROR),
        ));
        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('indisponible', $response->getContent());
    }

    public function testRejectedFormsAlsoConsumeTheRateLimit(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->submit(array_replace(self::FORM, ['website' => 'spam']));
            self::assertResponseStatusCodeSame(422);
        }
        $this->submit(self::FORM);
        self::assertResponseStatusCodeSame(429);
    }

    public function testSixthAttemptIsThrottledButOtherAddressesAreIndependent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(6))->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->submit(self::FORM);
            self::assertResponseStatusCodeSame(200);
        }
        $this->submit(self::FORM);
        self::assertResponseStatusCodeSame(429);
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(3601, $retryAfter);
        $this->submit(self::FORM, '192.0.2.2');
        self::assertResponseStatusCodeSame(200);
    }

    public function testMessagesCannotBeReadPublicly(): void
    {
        $this->client->request('GET', '/api/contact');
        self::assertResponseStatusCodeSame(405);
    }

    private function submit(array $form, string $ip = '192.0.2.1'): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $ip],
            content: json_encode((object) $form, JSON_THROW_ON_ERROR),
        );
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
