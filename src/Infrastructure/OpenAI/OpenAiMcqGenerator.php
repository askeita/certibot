<?php

namespace App\Infrastructure\OpenAI;

use App\Core\Mcq\Port\McqGeneratorInterface;
use OpenAI;
use Psr\Log\LoggerInterface;

/**
 * OpenAI adapter implementing McqGeneratorInterface.
 *
 * This is the infrastructure adapter for the MCQ generation port.
 * All OpenAI-specific code lives here. To swap the AI provider,
 * implement McqGeneratorInterface in a new adapter and update services.yaml.
 */
class OpenAiMcqGenerator implements McqGeneratorInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private mixed $topP;
    private mixed $nValue;

    public function __construct(
        private readonly LoggerInterface $logger,
        array $openAIConfig
    ) {
        $this->apiKey = $openAIConfig['api_key'] ?? '';
        $this->model = $openAIConfig['model'] ?? 'gpt-4o';
        $this->maxTokens = (int) ($openAIConfig['max_tokens'] ?? 400);
        $this->temperature = (float) ($openAIConfig['temperature'] ?? 0.2);
        $this->topP = $openAIConfig['top_p'] ?? 0.1;
        $this->nValue = $openAIConfig['n'] ?? 1;
    }

    /**
     * {@inheritdoc}
     */
    public function generateFromText(string $text, string $link): ?array
    {
        if (empty($this->apiKey)) {
            $this->logger->error('OpenAI API key is not configured.');

            return null;
        }

        $client = OpenAI::client($this->apiKey);

        try {
            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    [
                        'role' => 'user',
                        'content' => "Reformulate the following text into a multiple-choice
                                            question:\n\n$text. Give the correct answer to that multiple-choice question.
                                            In the content of the response message, only return a JSON array with three
                                            keys: 'question', 'choices' and 'answer.' The 'question' key should contain the
                                            question. The 'choices' key should contain the choices starting by A), B), C)
                                            or D), and separated by a question mark. The 'answer' key should contain the
                                            correct answer. The answer must be in the format: Correct Answer: <answer>.
                                            For example, Correct Answer: A.",
                    ],
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'top_p' => $this->topP,
                'n' => $this->nValue,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("OpenAI API call failed for link $link: " . $e->getMessage());

            return null;
        }

        $this->logger->debug('Response received from OpenAI API: ' . json_encode($response));
        $content = $response->choices[0]->message->content;

        $jsonContent = $content;
        if (!str_starts_with(ltrim($jsonContent), '{')) {
            preg_match('/({[\s\S]*})/m', $content, $matches);
            if (!empty($matches[1])) {
                $jsonContent = $matches[1];
            } else {
                $this->logger->error("Invalid OpenAI response format for link: $link");

                return null;
            }
        }

        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error("JSON decode error for link $link: " . $e->getMessage());

            return null;
        }

        if (!isset($data['choices'])) {
            $this->logger->error("No choices in OpenAI response for link: $link");

            return null;
        }

        if (!isset($data['answer'])) {
            $this->logger->error("No answer in OpenAI response for link: $link");

            return null;
        }

        $answerParts = explode('Correct Answer:', $data['answer']);
        if (count($answerParts) < 2) {
            $this->logger->error("Unexpected answer format for link: $link");

            return null;
        }

        return [
            'link'     => $link,
            'question' => $data['question'],
            'choices'  => $data['choices'],
            'answer'   => trim($answerParts[1]),
        ];
    }
}

