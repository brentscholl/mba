<?php

namespace App\Services;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;

class OpenAIService
{
    protected ClientContract $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }

    public function ask(string $prompt): array
    {
        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant. Always respond with raw JSON. No code fences, no explanations.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $content = $response->choices[0]->message->content;
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content); // remove ```json
            $content = preg_replace('/```$/', '', $content);       // remove closing ```

            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        } catch (\JsonException $e) {
            \Log::error('AI JSON decode error', [
                'message' => $e->getMessage(),
                'content' => $content ?? '[No content]',
            ]);
            throw $e;
        }
    }
}
