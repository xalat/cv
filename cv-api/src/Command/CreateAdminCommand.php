<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create an administrator account.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Administrator email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        if (!is_string($email) || strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('A valid email address (maximum 180 characters) is required.');

            return Command::INVALID;
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $io->error('An account already exists for this email.');

            return Command::FAILURE;
        }

        if (!$input->isInteractive()) {
            $io->error('Run this command interactively to enter the password securely.');

            return Command::INVALID;
        }

        $user = new User()->setEmail($email)->setRoles(['ROLE_ADMIN']);
        $password = $io->askHidden('Password', function (?string $value) use ($user): string {
            $user->setPlainPassword($value);
            $violations = $this->validator->validate($user);
            if (count($violations) > 0) {
                throw new \RuntimeException((string) $violations);
            }

            return $value;
        });
        if ($io->askHidden('Confirm password') !== $password) {
            $io->error('Passwords do not match.');

            return Command::INVALID;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->eraseCredentials();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success('Administrator created.');

        return Command::SUCCESS;
    }
}
