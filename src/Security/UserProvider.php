<?php

namespace App\Security;

use App\Document\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;


/**
 * UserProvider class to load users by username or email
 *
 * @implements UserProviderInterface
 */
class UserProvider implements UserProviderInterface
{
    private UserRepository $userRepository;


    /**
     * Constructor
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Load user by identifier (username or email)
     *
     * @param string $identifier
     * @return UserInterface
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try to find by username first
        $user = $this->userRepository->findOneByUsername($identifier);

        // If not found, try to find by email
        if (!$user) {
            $user = $this->userRepository->findOneByemail($identifier);
        }

        // If still not found, throw exception
        if (!$user) {
            throw new UserNotFoundException(sprintf('User with identifier "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * Refresh the user
     *
     * @param UserInterface $user
     * @return UserInterface
     * @throws \InvalidArgumentException
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not suppported.', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Check if the provider supports the given class
     *
     * @param string $class
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
