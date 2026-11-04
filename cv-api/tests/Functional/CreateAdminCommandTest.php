<?php

namespace App\Tests\Functional;

use App\Command\CreateAdminCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(User::class)]);
        $this->tester = new CommandTester(static::getContainer()->get(CreateAdminCommand::class));
    }

    public function testCreatesAdminWithHashedPasswordAndRejectsDuplicate(): void
    {
        $this->tester->setInputs(['test-admin-password', 'test-admin-password']);
        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'admin@example.test'], ['interactive' => true]));
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.test']);
        self::assertNotNull($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotSame('test-admin-password', $user->getPassword());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'test-admin-password'));

        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'admin@example.test']));
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
    }

    public function testRejectsInvalidEmailAndNonInteractiveCreation(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['email' => 'invalid']));
        self::assertSame(Command::INVALID, $this->tester->execute(['email' => 'admin@example.test'], ['interactive' => false]));
        self::assertSame(0, static::getContainer()->get(UserRepository::class)->count([]));
    }

    public function testRejectsMismatchedPasswords(): void
    {
        $this->tester->setInputs(['test-admin-password', 'different-password']);
        self::assertSame(Command::INVALID, $this->tester->execute(['email' => 'admin@example.test'], ['interactive' => true]));
        self::assertSame(0, static::getContainer()->get(UserRepository::class)->count([]));
    }

    public function testRepromptsForPasswordRejectedByEntityValidation(): void
    {
        $this->tester->setInputs(['short', 'valid-admin-password', 'valid-admin-password']);
        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'admin@example.test'], ['interactive' => true]));
        self::assertStringContainsString('at least 12 characters', $this->tester->getDisplay());
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.test']);
        self::assertNotNull($user);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'valid-admin-password'));
        self::assertNull($user->getPlainPassword());
    }
}
