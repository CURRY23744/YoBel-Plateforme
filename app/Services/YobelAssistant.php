<?php

namespace App\Services;

use App\Exceptions\YobelAssistantException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;

class YobelAssistant
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{answer: string, suggestions: array<int, array{type: string, title: string, content: ?string, payload: array}>, input_tokens: int, output_tokens: int, model: string}
     */
    public function reply(array $messages): array
    {
        $baseUrl = rtrim((string) config('services.yobel_ai.ollama_url'), '/');
        $model = (string) config('services.yobel_ai.model');

        if ($baseUrl === '' || $model === '') {
            throw new YobelAssistantException('Le moteur local de l’assistant n’est pas configuré.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.yobel_ai.timeout', 60))
                ->post($baseUrl.'/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'format' => 'json',
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $exception) {
            throw new YobelAssistantException('Le moteur Ollama local est indisponible.', previous: $exception);
        }

        if ($response->failed()) {
            try {
                $response->throw();
            } catch (RequestException $exception) {
                throw new YobelAssistantException('Le moteur Ollama local a refusé la requête.', previous: $exception);
            }
        }

        $content = $response->json('message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new YobelAssistantException('Le moteur Ollama a renvoyé une réponse vide ou invalide.');
        }

        try {
            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new YobelAssistantException('La réponse de Yobel n’a pas le format attendu.', previous: $exception);
        }

        if (! is_array($result)
            || ! is_string($result['answer'] ?? null)
            || trim($result['answer']) === ''
            || mb_strlen($result['answer']) > 20000
            || ! is_array($result['suggestions'] ?? null)
            || count($result['suggestions']) > 3) {
            throw new YobelAssistantException('La réponse de Yobel est incomplète ou invalide.');
        }

        $allowedTypes = ['task', 'decision', 'summary', 'priority', 'reply_draft', 'alert'];
        $suggestions = [];
        foreach ($result['suggestions'] as $suggestion) {
            if (! is_array($suggestion)
                || ! in_array($suggestion['type'] ?? null, $allowedTypes, true)
                || ! is_string($suggestion['title'] ?? null)
                || trim($suggestion['title']) === ''
                || mb_strlen($suggestion['title']) > 255
                || (isset($suggestion['content']) && ! is_string($suggestion['content']))
                || (isset($suggestion['content']) && mb_strlen($suggestion['content']) > 2000)
                || (isset($suggestion['payload']) && ! is_array($suggestion['payload']))) {
                throw new YobelAssistantException('Yobel a renvoyé une suggestion invalide.');
            }

            $suggestions[] = [
                'type' => $suggestion['type'],
                'title' => trim($suggestion['title']),
                'content' => $suggestion['content'] ?? null,
                'payload' => $suggestion['payload'] ?? [],
            ];
        }

        return [
            'answer' => trim($result['answer']),
            'suggestions' => $suggestions,
            'input_tokens' => max(0, (int) $response->json('prompt_eval_count', 0)),
            'output_tokens' => max(0, (int) $response->json('eval_count', 0)),
            'model' => (string) ($response->json('model') ?: $model),
        ];
    }
}
