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
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'response_format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful AI that returns only JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $json = $response->choices[0]->message->content;

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
