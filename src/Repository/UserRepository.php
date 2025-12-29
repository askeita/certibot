<?php

namespace App\Repository;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Throwable;


/**
 * UserRepository
 */
class UserRepository extends DocumentRepository implements PasswordUpgraderInterface
{
    /**
     * Constructor
     *
     * @param DocumentManager $dm
     */
    public function __construct(DocumentManager $dm)
    {
        $uow = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(User::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     * @throws \Exception
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->getDocumentManager()->persist($user);
        try {
            $this->getDocumentManager()->flush();
        } catch (MongoDBException|Throwable $e) {
            throw new \Exception('Could not upgrade password: ' . $e->getMessage());
        }
    }

    /**
     * Find one user by username
     *
     * @param string $username
     * @return User|null
     */
    public function findOneByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Find one user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Save user
     *
     * @param User $user
     * @throws MongoDBException|Throwable
     * @return void
     */
    public function save(User $user): void
    {
        $this->getDocumentManager()->persist($user);
        $this->getDocumentManager()->flush();
    }

    /**
     * Find one user by verification token
     *
     * @param string $token
     * @return User|null
     */
    public function findOneByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['verificationToken' => $token]);
    }
}
