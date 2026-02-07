<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;


/**
 * UserChecker checks the user's account status before authentication.
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Check the user's account status before authentication.
     * If the user has an isVerified method, and it returns false, an exception is thrown.
     *
     * @param UserInterface $user
     * @return void
     * @throws CustomUserMessageAccountStatusException if the user's email is not verified
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (method_exists($user, 'isVerified') && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email');
        }
    }

    /**
     * Check the user's account status after authentication.
     * In this case, no additional checks are performed.
     *
     * @param UserInterface $user
     * @return void
     */
    public function checkPostAuth(UserInterface $user): void
    {
        // No additional checks after authentication
    }
}
