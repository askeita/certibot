<?php

namespace App\Tests\Form;

use App\Form\LoginFormType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * LoginFormTypeTest
 *
 * Unit tests for the LoginFormType form class.
 */
class LoginFormTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            '_username' => 'testuser',
            '_password' => 'password123',
            '_remember_me' => true,
        ];

        $form = $this->factory->create(LoginFormType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        // Check that form fields exist and have correct data
        $this->assertTrue($form->has('_username'));
        $this->assertTrue($form->has('_password'));
        $this->assertTrue($form->has('_remember_me'));

        $this->assertEquals('testuser', $form->get('_username')->getData());
        $this->assertEquals('password123', $form->get('_password')->getData());
        $this->assertTrue($form->get('_remember_me')->getData());
    }

    public function testSubmitWithoutRememberMe(): void
    {
        $formData = [
            '_username' => 'testuser',
            '_password' => 'password123',
            '_remember_me' => false,
        ];

        $form = $this->factory->create(LoginFormType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertFalse($form->get('_remember_me')->getData());
    }

    public function testFormHasCorrectFields(): void
    {
        $form = $this->factory->create(LoginFormType::class);

        $this->assertTrue($form->has('_username'));
        $this->assertTrue($form->has('_password'));
        $this->assertTrue($form->has('_remember_me'));
    }

    public function testUsernameFieldConfiguration(): void
    {
        $form = $this->factory->create(LoginFormType::class);
        $usernameField = $form->get('_username');

        // Test that the field is not mapped
        $this->assertFalse($usernameField->getConfig()->getMapped());

        // Test field type
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\TextType',
                          get_class($usernameField->getConfig()->getType()->getInnerType()));
    }

    public function testPasswordFieldConfiguration(): void
    {
        $form = $this->factory->create(LoginFormType::class);
        $passwordField = $form->get('_password');

        // Test that the field is not mapped
        $this->assertFalse($passwordField->getConfig()->getMapped());

        // Test field type
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\PasswordType',
                          get_class($passwordField->getConfig()->getType()->getInnerType()));
    }

    public function testRememberMeFieldConfiguration(): void
    {
        $form = $this->factory->create(LoginFormType::class);
        $rememberMeField = $form->get('_remember_me');

        // Test that the field is not mapped
        $this->assertFalse($rememberMeField->getConfig()->getMapped());

        // Test field type
        $this->assertEquals('Symfony\Component\Form\Extension\Core\Type\CheckboxType',
                          get_class($rememberMeField->getConfig()->getType()->getInnerType()));
    }

    public function testFormWithEmptyData(): void
    {
        $form = $this->factory->create(LoginFormType::class);
        $form->submit([]);

        $this->assertTrue($form->isSynchronized());

        // Empty data should still be valid since validation happens elsewhere
        $this->assertTrue($form->isValid());
    }

    public function testFormWithPartialData(): void
    {
        $formData = [
            '_username' => 'testuser',
            // Missing password and remember me
        ];

        $form = $this->factory->create(LoginFormType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertEquals('testuser', $form->get('_username')->getData());
        $this->assertNull($form->get('_password')->getData());
        $this->assertFalse($form->get('_remember_me')->getData());
    }
}
