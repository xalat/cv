<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserValidationTest extends KernelTestCase
{
    public function testPasswordValidationOnEntity(): void
    {
        self::bootKernel();
        $validator = static::getContainer()->get(ValidatorInterface::class);
        $user = new User();

        foreach ([null, '', 'short', str_repeat('a', 11), str_repeat(' ', 12)] as $password) {
            $user->setPlainPassword($password);
            $violations = $validator->validate($user);
            self::assertGreaterThan(0, count($violations));
            self::assertSame('plainPassword', $violations[0]->getPropertyPath());
        }

        foreach ([str_repeat('a', 12), str_repeat('é', 12)] as $password) {
            $user->setPlainPassword($password);
            self::assertCount(0, $validator->validate($user));
        }

        $user->setPassword('stored-hash')->eraseCredentials();
        self::assertNull($user->getPlainPassword());
        self::assertCount(0, $validator->validate($user));

        $user->setPlainPassword('short');
        self::assertGreaterThan(0, count($validator->validate($user)));
    }

    public function testPlainPasswordIsNotSerialized(): void
    {
        $user = new User()->setPassword('stored-hash')->setPlainPassword('secret-not-for-storage');
        self::assertStringNotContainsString('secret-not-for-storage', serialize($user));
    }
}
