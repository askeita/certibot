<?php

namespace App\Tests\Document;

use App\Document\User;
use PHPUnit\Framework\TestCase;

/**
 * UserTest
 *
 * Unit tests for the User document class.
 */
class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testUserCreation(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
        $this->assertNull($this->user->getId());
        $this->assertNull($this->user->getUsername());
        $this->assertNull($this->user->getEmail());
        $this->assertNull($this->user->getPassword());
        $this->assertEquals(['ROLE_USER'], $this->user->getRoles());
        $this->assertFalse($this->user->isVerified());
        $this->assertNull($this->user->getVerificationToken());
    }

    public function testSetAndGetUsername(): void
    {
        $username = 'testuser';
        $this->user->setUsername($username);
        $this->assertEquals($username, $this->user->getUsername());
        $this->assertEquals($username, $this->user->getUserIdentifier());
    }

    public function testSetAndGetEmail(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);
        $this->assertEquals($email, $this->user->getEmail());
    }

    public function testSetAndGetPassword(): void
    {
        $password = 'hashedpassword';
        $this->user->setPassword($password);
        $this->assertEquals($password, $this->user->getPassword());
    }

    public function testSetAndGetRoles(): void
    {
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $this->user->setRoles($roles);
        $this->assertEquals($roles, $this->user->getRoles());
    }

    public function testDefaultRole(): void
    {
        // Test that ROLE_USER is always included
        $this->user->setRoles(['ROLE_ADMIN']);
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testSetAndGetIsVerified(): void
    {
        $this->user->setIsVerified(true);
        $this->assertTrue($this->user->isVerified());

        $this->user->setIsVerified(false);
        $this->assertFalse($this->user->isVerified());
    }

    public function testSetAndGetVerificationToken(): void
    {
        $token = 'verification_token_123';
        $this->user->setVerificationToken($token);
        $this->assertEquals($token, $this->user->getVerificationToken());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTime();
        $this->user->setCreatedAt($date);
        $this->assertEquals($date, $this->user->getCreatedAt());
    }

    public function testUserSerialize(): void
    {
        $this->user->setUsername('testuser');
        $this->user->setEmail('test@example.com');

        $serialized = serialize($this->user);
        $this->assertIsString($serialized);

        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(User::class, $unserialized);
        $this->assertEquals('testuser', $unserialized->getUsername());
        $this->assertEquals('test@example.com', $unserialized->getEmail());
    }

    public function testEraseCredentials(): void
    {
        // This method should be implemented if needed for security
        $this->user->eraseCredentials();
        // Test should pass without errors
        $this->assertTrue(true);
    }
}
