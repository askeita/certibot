<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * LoginFormType class to define the login form
 */
class LoginFormType extends AbstractType
{
    /**
     * Build the form
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', TextType::class, [
                'label' => 'Username or Email',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Enter your username or email',
                    'autofocus' => true,
                ],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Enter your password',
                ],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => 'Remember Me',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    /**
     * Configure options for the form
     *
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'authenticate',
            'csrf_field_name' => '_csrf_token',
            'data_class' => null,
        ]);
    }

    /**
     * Get block prefix. Return empty string to avoid form name prefixing
     *
     * @return string
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
