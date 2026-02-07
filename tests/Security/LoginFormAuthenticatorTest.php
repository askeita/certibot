<?php

namespace App\Tests\Security;

use App\Security\LoginFormAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * LoginFormAuthenticatorTest
 *
 * Unit tests for the LoginFormAuthenticator class.
 */
class LoginFormAuthenticatorTest extends TestCase
{
    private LoginFormAuthenticator $authenticator;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->authenticator = new LoginFormAuthenticator($this->urlGenerator);
    }

    public function testSupportsLoginRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'app_login');
        $request->setMethod('POST');

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testDoesNotSupportNonLoginRoute(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'other_route');
        $request->setMethod('POST');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testDoesNotSupportGetMethod(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'app_login');
        $request->setMethod('GET');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testDoesNotSupportNullRoute(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticate(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('set')
            ->with(SecurityRequestAttributes::LAST_USERNAME, 'testuser');

        $request = new Request();
        $request->request->set('_username', 'testuser');
        $request->request->set('_password', 'password123');
        $request->request->set('_csrf_token', 'csrf_token_value');
        $request->setSession($session);

        $passport = $this->authenticator->authenticate($request);

        // Test UserBadge
        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertEquals('testuser', $userBadge->getUserIdentifier());

        // Test PasswordCredentials
        $passwordCredentials = $passport->getBadge(PasswordCredentials::class);
        $this->assertInstanceOf(PasswordCredentials::class, $passwordCredentials);

        // Test CsrfTokenBadge
        $csrfBadge = $passport->getBadge(CsrfTokenBadge::class);
        $this->assertInstanceOf(CsrfTokenBadge::class, $csrfBadge);

        // Test RememberMeBadge
        $rememberMeBadge = $passport->getBadge(RememberMeBadge::class);
        $this->assertInstanceOf(RememberMeBadge::class, $rememberMeBadge);
    }

    public function testAuthenticateWithEmptyCredentials(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('set')
            ->with(SecurityRequestAttributes::LAST_USERNAME, '');

        $request = new Request();
        $request->setSession($session);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals('', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateWithSpecialCharacters(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $specialUsername = 'user@domain.com';
        $session->expects($this->once())
            ->method('set')
            ->with(SecurityRequestAttributes::LAST_USERNAME, $specialUsername);

        $request = new Request();
        $request->request->set('_username', $specialUsername);
        $request->request->set('_password', 'password!@#$%');
        $request->request->set('_csrf_token', 'csrf_token');
        $request->setSession($session);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($specialUsername, $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationSuccessWithTargetPath(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('get')
            ->with('_security.main.target_path')
            ->willReturn('/admin/dashboard');

        // getTargetPath peut faire le remove automatiquement
        $session->expects($this->atMost(1))
            ->method('remove')
            ->with('_security.main.target_path');

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/admin/dashboard', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithoutTargetPath(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('get')
            ->with('_security.main.target_path')
            ->willReturn(null);

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_index')
            ->willReturn('/');

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessWithEmptyTargetPath(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('get')
            ->with('_security.main.target_path')
            ->willReturn('');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_index')
            ->willReturn('/');

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureWithSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('set')
            ->with(SecurityRequestAttributes::AUTHENTICATION_ERROR, $this->isInstanceOf(AuthenticationException::class));

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login')
            ->willReturn('/login');

        $request = new Request();
        $request->setSession($session);

        $exception = new AuthenticationException('Invalid credentials');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/login', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureWithoutSession(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login')
            ->willReturn('/login');

        $request = new Request();
        // No session set

        $exception = new AuthenticationException('Invalid credentials');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/login', $response->getTargetUrl());
    }

    public function testLoginRouteConstant(): void
    {
        $this->assertEquals('app_login', LoginFormAuthenticator::LOGIN_ROUTE);
    }

    public function testSupportsWithDifferentHttpMethods(): void
    {
        $methods = ['PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $request = new Request();
            $request->attributes->set('_route', 'app_login');
            $request->setMethod($method);

            $this->assertFalse($this->authenticator->supports($request), "Method {$method} should not be supported");
        }
    }

    public function testAuthenticateStoresLastUsernameInSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $username = 'test.user@example.com';

        $session->expects($this->once())
            ->method('set')
            ->with(SecurityRequestAttributes::LAST_USERNAME, $username);

        $request = new Request();
        $request->request->set('_username', $username);
        $request->request->set('_password', 'password');
        $request->setSession($session);

        $this->authenticator->authenticate($request);
    }

    public function testOnAuthenticationSuccessWithDifferentFirewalls(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('get')
            ->with('_security.admin.target_path')
            ->willReturn('/admin/home');

        $session->expects($this->atMost(1))
            ->method('remove')
            ->with('_security.admin.target_path');

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'admin');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/admin/home', $response->getTargetUrl());
    }
}
