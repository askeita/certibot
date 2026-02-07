<?php

namespace App\Tests\Security;

use App\Document\User;
use App\Repository\UserRepository;
use App\Security\UserProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserProviderTest
 *
 * Unit tests for the UserProvider class.
 */
class UserProviderTest extends TestCase
{
    private UserProvider $userProvider;
    private MockObject|UserRepository $userRepository;


    /**
     * Set up the test environment before each test.
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userProvider = new UserProvider($this->userRepository);
    }

    /**
     * Test loading a user by identifier using the username.
     */
    public function testLoadUserByIdentifierWithUsername(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn($user);

        $loadedUser = $this->userProvider->loadUserByIdentifier('testuser');

        $this->assertSame($user, $loadedUser);
        $this->assertEquals('testuser', $loadedUser->getUsername());
    }

    /**
     * Test loading a user by identifier using the email.
     */
    public function testLoadUserByIdentifierWithEmail(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        // First call for username returns null
        // Second call for email returns user
        $this->userRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($user) {
                if (isset($criteria['username'])) {
                    return null; // User not found by username
                }
                if (isset($criteria['email'])) {
                    return $user; // User found by email
                }
                return null;
            });

        $loadedUser = $this->userProvider->loadUserByIdentifier('test@example.com');

        $this->assertSame($user, $loadedUser);
        $this->assertEquals('test@example.com', $loadedUser->getEmail());
    }

    /**
     * Test loading a user by identifier when the user is not found.
     */
    public function testLoadUserByIdentifierUserNotFound(): void
    {
        $this->userRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User with identifier "nonexistent" not found.');

        $this->userProvider->loadUserByIdentifier('nonexistent');
    }

    /**
     * Test refreshing a user successfully.
     */
    public function testRefreshUserSuccess(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn($user);

        $refreshedUser = $this->userProvider->refreshUser($user);

        $this->assertSame($user, $refreshedUser);
    }

    /**
     * Test refreshing a user when the user is not found.
     */
    public function testRefreshUserNotFound(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->userRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User with identifier "testuser" not found.');

        $this->userProvider->refreshUser($user);
    }

    /**
     * Test supportsClass method with the User class.
     */
    public function testSupportsClassWithUserClass(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(User::class));
    }

    /**
     * Test supportsClass method with unsupported classes.
     */
    public function testSupportsClassWithOtherClass(): void
    {
        $this->assertFalse($this->userProvider->supportsClass('SomeOtherClass'));
        $this->assertFalse($this->userProvider->supportsClass(\stdClass::class));
    }

    /**
     * Test that loadUserByUsername calls loadUserByIdentifier for backward compatibility.
     */
    public function testLoadUserByUsernameCallsLoadUserByIdentifier(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn($user);

        // Test the deprecated method if it exists
        if (method_exists($this->userProvider, 'loadUserByUsername')) {
            $loadedUser = $this->userProvider->loadUserByUsername('testuser');
            $this->assertSame($user, $loadedUser);
        } else {
            $this->markTestSkipped('loadUserByUsername method does not exist'); // This is expected in Symfony 5.3+ where loadUserByUsername is deprecated and removed in Symfony 6.
        }
    }

    /**
     * Test loading a user that is not verified.
     */
    public function testUserProviderWithUnverifiedUser(): void
    {
        $user = new User();
        $user->setUsername('unverifieduser');
        $user->setEmail('unverified@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(false); // User is not verified

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'unverifieduser'])
            ->willReturn($user);

        $loadedUser = $this->userProvider->loadUserByIdentifier('unverifieduser');

        $this->assertSame($user, $loadedUser);
        $this->assertFalse($loadedUser->isVerified());
    }

    /**
     * Test supportsClass method with an actual User object.
     */
    public function testUserProviderWithUserObject(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        // Test with actual User object
        $this->assertTrue($this->userProvider->supportsClass(get_class($user)));
    }

    /**
     * Test refreshing a user with an unsupported user type.
     */
    public function testRefreshUserWithWrongUserType(): void
    {
        $wrongUser = $this->createMock(UserInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Instances of "'.get_class($wrongUser).'" are not suppported.');

        $this->userProvider->refreshUser($wrongUser);
    }
}
