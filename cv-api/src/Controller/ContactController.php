<?php

namespace App\Controller;

use App\Dto\ContactMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactController
{
    public function __construct(
        private MailerInterface $mailer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.contact')]
        private RateLimiterFactoryInterface $limiter,
        #[Autowire('%env(CONTACT_FROM)%')]
        private string $from,
        #[Autowire('%env(CONTACT_TO)%')]
        private string $to,
    ) {
    }

    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $limit = $this->limiter->create(hash('sha256', $request->getClientIp() ?? 'unknown'))->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['message' => 'Trop de tentatives. Réessayez dans une heure.'],
                429,
                ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
            );
        }

        if ($request->getContentTypeFormat() !== 'json') {
            return new JsonResponse(['message' => 'Le formulaire doit être envoyé au format JSON.'], 415);
        }
        if (strlen($request->getContent()) > 32768) {
            return new JsonResponse(['message' => 'Le formulaire est trop volumineux.'], 413);
        }

        try {
            $data = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Le formulaire contient des données JSON invalides.'], 400);
        }
        if (!$data instanceof \stdClass) {
            return new JsonResponse(['message' => 'Le formulaire doit contenir un objet JSON.'], 400);
        }
        $data = get_object_vars($data);
        if (array_diff(array_keys($data), ['name', 'email', 'message', 'website'])) {
            return new JsonResponse(['message' => 'Le formulaire contient des champs inconnus.'], 422);
        }
        foreach (['name', 'email', 'message', 'website'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                return new JsonResponse(['message' => 'Les champs du formulaire doivent être des textes.'], 422);
            }
        }
        if (trim($data['website'] ?? '') !== '') {
            return new JsonResponse(['message' => 'Le formulaire a été refusé par la protection antispam.'], 422);
        }

        $contact = new ContactMessage(
            trim($data['name'] ?? ''),
            trim($data['email'] ?? ''),
            trim($data['message'] ?? ''),
        );
        $errors = [];
        foreach ($this->validator->validate($contact) as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        if ($errors) {
            return new JsonResponse(['message' => 'Vérifiez les champs du formulaire.', 'errors' => $errors], 422);
        }

        if ($this->from === '' || $this->to === '') {
            $this->logger->error('Contact mail configuration is missing.');

            return new JsonResponse(['message' => 'Le service de contact est momentanément indisponible. Réessayez plus tard.'], 503);
        }
        $email = new Email()
            ->from($this->from)
            ->to($this->to)
            ->replyTo($contact->email)
            ->subject('Nouveau message depuis le site CV')
            ->text("Nom : {$contact->name}\n\n{$contact->message}");

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            // SMTP exceptions can contain credentials or the visitor's message.
            $this->logger->error('Contact mail transport failed.', ['exception_type' => $exception::class]);

            return new JsonResponse(['message' => 'Votre message n’a pas pu être envoyé. Réessayez plus tard.'], 503);
        }

        return new JsonResponse(['message' => 'Votre message a été transmis. Merci pour votre prise de contact.']);
    }
}
