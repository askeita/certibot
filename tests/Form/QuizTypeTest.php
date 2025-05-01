<?php

namespace App\Tests\Form;

use App\Form\QuizType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;


/**
 * QuizTypeTest
 *
 * Unit tests for the QuizType class.
 */
class QuizTypeTest extends TypeTestCase
{
    /**
     * Tests the buildForm method
     *
     * @return array
     */
    public function getExtensions(): array
    {
        return [
            new PreloadedExtension([], []),
        ];
    }

    /**
     * Tests the buildForm method
     *
     * @return void
     */
    public function testBuildForm(): void
    {
        $form = $this->factory->create(QuizType::class);
        $form->submit([
            'question' => 'What is Symfony?',
            'choices' => ['A. A PHP framework', 'B. A JavaScript library', 'C. A CSS framework', 'D. A database'],
            'answer' => ['A. A PHP framework'],
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
    }

    /**
     * Tests the submit method with valid data
     *
     * @return void
     */
    public function testSubmitValidData(): void
    {
        $formData = [
            "title" => "Quiz Title",
            "duration" => 60,
            "questions" => [
                [
                    "question" => "What is Symfony?",
                    "choices" => ["A. A PHP framework", "B. A JavaScript library", "C. A CSS framework", "D. A database"],
                    "answer" => "A",
                ],
            ],
        ];

        $form = $this->factory->create(QuizType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());

        $view = $form->createView();
        $children = $view->children;
        $this->assertArrayHasKey('selectChoices', $children);
    }

    /**
     * Tests the configureOptions method
     *
     * @return void
     */
    public function testConfigureOptions(): void
    {
        $form = $this->factory->create(QuizType::class);
        $options = $form->getConfig()->getOptions();

        $this->assertArrayHasKey('data_class', $options);
    }

    /**
     * Tests the getName method
     *
     * @return void
     */
    public function testGetName(): void
    {
        $form = $this->factory->create(QuizType::class);
        $name = $form->getName();

        $this->assertEquals('quiz', $name);
    }

}
