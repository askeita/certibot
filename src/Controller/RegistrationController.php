<?php

namespace App\Controller;

use App\Document\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * RegistrationController
 */
class RegistrationController extends AbstractController
{
    private DocumentManager $dm;
    private UserPasswordHasherInterface $passwordHasher;
    private EmailVerificationService $emailVerificationService;


    /**
     * Constructor
     */
    public function __construct(
        DocumentManager $dm,
        UserPasswordHasherInterface $passwordHasher,
        EmailVerificationService $emailVerificationService
    ) {
        $this->dm = $dm;
        $this->passwordHasher = $passwordHasher;
        $this->emailVerificationService = $emailVerificationService;
    }

    /**
     * Register
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UserRepository $userRepository */
            $userRepository = $this->dm->getRepository(User::class);

            // Check if username already exists
            if ($userRepository->findOneBy(['username' => $form->get('username')->getData()])) {
                $this->addFlash('error', 'This username is already taken.');
                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            // Check if email already exists
            if ($userRepository->findOneBy(['email' => $form->get('email')->getData()])) {
                $this->addFlash('error', 'This email is already registered.');
                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            // Hash the password
            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Generate verification token
            $verificationToken = $this->emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            // Save user
            $this->dm->persist($user);
            $this->dm->flush();

            // Generate verification URL
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email
            try {
                $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
                $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Registration successful! However, we could not send the verification email. Please contact support.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Verify Email
     */
    #[Route('/verify/email/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token): Response
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->dm->getRepository(User::class);
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Invalid verification token.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        // Verify the user
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->dm->persist($user);
        $this->dm->flush();

        $this->addFlash('success', 'Your email has been verified successfully! You can now log in.');
        return $this->redirectToRoute('app_login');
    }
}
