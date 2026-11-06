<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactMessage
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre nom.')]
        #[Assert\Length(max: 100, maxMessage: 'Le nom est limité à {{ limit }} caractères.')]
        public string $name,
        #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail.')]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT, message: 'Indiquez une adresse e-mail valide.')]
        #[Assert\Regex(pattern: '/^[^\r\n]+$/D', message: 'Indiquez une adresse e-mail valide.')]
        #[Assert\Length(max: 254, maxMessage: 'L’adresse e-mail est limitée à {{ limit }} caractères.')]
        public string $email,
        #[Assert\NotBlank(message: 'Écrivez votre message.')]
        #[Assert\Length(max: 5000, maxMessage: 'Le message est limité à {{ limit }} caractères.')]
        public string $message,
    ) {
    }
}
