<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * QuizType class.
 *
 * This class is responsible for creating the form used in the quiz.
 */
class QuizType extends AbstractType
{
    /**
     * Builds the form.
     *
     * @param FormBuilderInterface $builder     form builder
     * @param array $options                    options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('selectChoices', ChoiceType::class, [
                'choices' => $options['choices'],
                'expanded' => true,
                'multiple' => false,
                'label' => false,
                'choice_attr' => function() {
                    return ['class' => 'form-check-input choice-checkbox'];
                },
            ])
        ;
    }

    /**
     * Configures the options for this form type.
     *
     * @param OptionsResolver $resolver     resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [],
            'attr' => ['id' => 'quizForm'],
        ]);
    }
}
