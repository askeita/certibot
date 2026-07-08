<?php

namespace App\Core\Mcq\Port;

/**
 * Output port for MCQ generation.
 *
 * This interface decouples the quiz pipeline from the concrete AI provider.
 * Swapping OpenAI for another LLM (e.g. a self-hosted model) only requires
 * providing a new adapter implementing this interface — no command code changes.
 */
interface McqGeneratorInterface
{
    /**
     * Generates a multiple-choice question from a text excerpt.
     *
     * @param string $text The source text to reformulate into a question.
     * @param string $link The documentation URL the text was extracted from.
     *
     * @return array{link: string, question: string, choices: string, answer: string}|null
     *         Returns null if generation fails or the response is invalid.
     */
    public function generateFromText(string $text, string $link): ?array;
}

