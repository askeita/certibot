<?php

namespace App\Tests\Repository;

use App\Document\User;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * UserRepositoryTest
 *
 * Unit tests for the UserRepository class.
 */
class UserRepositoryTest extends KernelTestCase
{
    /**
     * @var DocumentManager|null
     */
    private $documentManager;
    /**
     * @var UserRepository
     */
    private $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->documentManager = static::getContainer()->get(DocumentManager::class);
        $this->userRepository = $this->documentManager->getRepository(User::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Clear the UnitOfWork and the database
        $this->documentManager->clear();

        // Delete all users from the collection to ensure clean state
        try {
            $this->documentManager->getDocumentCollection(User::class)->deleteMany([]);
        } catch (\Exception $e) {
            // Si la collection n'existe pas encore, ce n'est pas grave
        }
    }

    public function testFindOneByUsername(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(true);

        $this->documentManager->persist($user);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $foundUser = $this->userRepository->findOneBy(['username' => 'testuser']);

        $this->assertNotNull($foundUser);
        $this->assertEquals('testuser', $foundUser->getUsername());
        $this->assertEquals('test@example.com', $foundUser->getEmail());
    }

    public function testFindOneByEmail(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(true);

        $this->documentManager->persist($user);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $foundUser = $this->userRepository->findOneBy(['email' => 'test@example.com']);

        $this->assertNotNull($foundUser);
        $this->assertEquals('testuser', $foundUser->getUsername());
        $this->assertEquals('test@example.com', $foundUser->getEmail());
    }

    public function testFindOneByVerificationToken(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setVerificationToken('token_123');
        $user->setIsVerified(false);

        $this->documentManager->persist($user);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $foundUser = $this->userRepository->findOneBy(['verificationToken' => 'token_123']);

        $this->assertNotNull($foundUser);
        $this->assertEquals('token_123', $foundUser->getVerificationToken());
        $this->assertFalse($foundUser->isVerified());
    }

    public function testUpgradePassword(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'oldpassword'));
        $user->setIsVerified(true);

        $this->documentManager->persist($user);
        $this->documentManager->flush();

        $userId = $user->getId();
        $newHashedPassword = $this->passwordHasher->hashPassword($user, 'newpassword');
        $this->userRepository->upgradePassword($user, $newHashedPassword);

        $this->assertEquals($newHashedPassword, $user->getPassword());

        // Recharger depuis la base de données en utilisant clear() puis findOneBy()
        $this->documentManager->clear();
        $reloadedUser = $this->userRepository->findOneBy(['id' => $userId]);
        $this->assertNotNull($reloadedUser);
        $this->assertEquals($newHashedPassword, $reloadedUser->getPassword());
    }

    public function testUpgradePasswordWithUnsupportedUser(): void
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\UnsupportedUserException::class);

        // Create an anonymous class that implements the interface but is not a User
        $unsupportedUser = new class implements \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return 'somepassword';
            }
        };

        $this->userRepository->upgradePassword($unsupportedUser, 'newhash');
    }

    public function testFindAllUsers(): void
    {
        // Create multiple users
        $user1 = new User();
        $user1->setUsername('user1');
        $user1->setEmail('user1@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
        $user1->setIsVerified(true);

        $user2 = new User();
        $user2->setUsername('user2');
        $user2->setEmail('user2@example.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
        $user2->setIsVerified(false);

        $this->documentManager->persist($user1);
        $this->documentManager->persist($user2);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $allUsers = $this->userRepository->findAll();

        $this->assertCount(2, $allUsers);
        $usernames = array_map(fn($u) => $u->getUsername(), $allUsers);
        $this->assertContains('user1', $usernames);
        $this->assertContains('user2', $usernames);
    }

    public function testFindByVerificationStatus(): void
    {
        // Create verified and unverified users
        $verifiedUser = new User();
        $verifiedUser->setUsername('verified');
        $verifiedUser->setEmail('verified@example.com');
        $verifiedUser->setPassword($this->passwordHasher->hashPassword($verifiedUser, 'password123'));
        $verifiedUser->setIsVerified(true);

        $unverifiedUser = new User();
        $unverifiedUser->setUsername('unverified');
        $unverifiedUser->setEmail('unverified@example.com');
        $unverifiedUser->setPassword($this->passwordHasher->hashPassword($unverifiedUser, 'password123'));
        $unverifiedUser->setIsVerified(false);

        $this->documentManager->persist($verifiedUser);
        $this->documentManager->persist($unverifiedUser);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $verifiedUsers = $this->userRepository->findBy(['isVerified' => true]);
        $unverifiedUsers = $this->userRepository->findBy(['isVerified' => false]);

        $this->assertCount(1, $verifiedUsers);
        $this->assertCount(1, $unverifiedUsers);
        $this->assertEquals('verified', $verifiedUsers[0]->getUsername());
        $this->assertEquals('unverified', $unverifiedUsers[0]->getUsername());
    }

    public function testUserNotFound(): void
    {
        $foundUser = $this->userRepository->findOneBy(['username' => 'nonexistent']);
        $this->assertNull($foundUser);

        $foundUser = $this->userRepository->findOneBy(['email' => 'nonexistent@example.com']);
        $this->assertNull($foundUser);

        $foundUser = $this->userRepository->findOneBy(['verificationToken' => 'invalid_token']);
        $this->assertNull($foundUser);
    }

    public function testCreateNewUser(): void
    {
        $user = new User();
        $user->setUsername('newuser');
        $user->setEmail('newuser@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(false);
        $user->setVerificationToken('new_token');

        $this->documentManager->persist($user);
        $this->documentManager->flush();
        $this->documentManager->clear(); // Clear pour forcer le rechargement depuis MongoDB

        $savedUser = $this->userRepository->findOneBy(['username' => 'newuser']);

        $this->assertNotNull($savedUser);
        $this->assertEquals('newuser', $savedUser->getUsername());
        $this->assertEquals('newuser@example.com', $savedUser->getEmail());
        $this->assertFalse($savedUser->isVerified());
        $this->assertEquals('new_token', $savedUser->getVerificationToken());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->documentManager) {
            $this->documentManager->close();
        }
        $this->documentManager = null;

        // Ensure kernel is properly shut down after each test
        static::ensureKernelShutdown();
    }
}
