<?php

namespace App\Tests\Form;

use App\Document\User;
use App\Form\RegistrationFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * RegistrationFormTypeTest
 *
 * Unit tests for the RegistrationFormType form class.
 */
class RegistrationFormTypeTest extends TestCase
{
    private $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une factory de formulaires avec extension Validator
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        // Check that data is properly set
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testFormHasCorrectFields(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());

        $this->assertTrue($form->has('username'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('plainPassword'));
    }

    public function testUsernameFieldConfiguration(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());
        $usernameField = $form->get('username');

        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\TextType',
                          get_class($usernameField->getConfig()->getType()->getInnerType()));
    }

    public function testEmailFieldConfiguration(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());
        $emailField = $form->get('email');

        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\EmailType',
                          get_class($emailField->getConfig()->getType()->getInnerType()));
    }

    public function testPlainPasswordFieldConfiguration(): void
    {
        $form = $this->factory->create(RegistrationFormType::class, new User());
        $passwordField = $form->get('plainPassword');

        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\RepeatedType',
                          get_class($passwordField->getConfig()->getType()->getInnerType()));

        // Check that it has first and second fields
        $this->assertTrue($passwordField->has('first'));
        $this->assertTrue($passwordField->has('second'));
    }

    public function testFormWithEmptyUsername(): void
    {
        $formData = [
            'username' => '',
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        // NotBlank et Length peuvent tous deux échouer, donc au moins 1 erreur
        $this->assertGreaterThanOrEqual(1, count($form->get('username')->getErrors(true)));
    }

    public function testFormWithShortUsername(): void
    {
        $formData = [
            'username' => 'ab', // Too short
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('username')->getErrors(true)));
    }

    public function testFormWithInvalidEmail(): void
    {
        $formData = [
            'username' => 'testuser',
            'email' => 'invalid-email',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('email')->getErrors(true)));
    }

    public function testFormWithShortPassword(): void
    {
        $formData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => '123', // Too short
                'second' => '123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('plainPassword')->getErrors(true)));
    }

    public function testFormWithMismatchedPasswords(): void
    {
        $formData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'different456', // Different password
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('plainPassword')->getErrors(true)));
    }

    public function testFormWithLongUsername(): void
    {
        $formData = [
            'username' => str_repeat('a', 51), // Too long (max 50)
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('username')->getErrors(true)));
    }
}
