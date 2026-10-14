<?php
namespace KG_Core\Services;

/**
 * Small OpenAI Responses API client shared by KG Core AI features.
 *
 * Keeps transport, model migration and output parsing in one place so
 * individual services do not need to know OpenAI response internals.
 */
class OpenAIClient {
    const DEFAULT_MODEL = 'gpt-6.1-sol';

    const LEGACY_OPENAI_MODELS = [
        'gpt-3.5-turbo',
        'gpt-4',
        'gpt-4-turbo',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4.1',
        'gpt-4.1-mini',
    ];

    private $api_key;
    private $model;

    public function __construct($api_key = null, $model = null) {
        $this->api_key = $api_key ?: (get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', ''));
        $this->model = self::resolveModel($model);
    }

    /**
     * Migrate empty / legacy OpenAI model values to the current KG default.
     */
    public static function resolveModel($model) {
        $model = is_string($model) ? trim($model) : '';

        if ($model === '' || in_array($model, self::LEGACY_OPENAI_MODELS, true)) {
            return self::DEFAULT_MODEL;
        }

        return $model;
    }

    public function getModel() {
        return $this->model;
    }

    /**
     * Generate plain text through the OpenAI Responses API.
     *
     * @return string|\WP_Error
     */
    public function generateText($instructions, $input, $max_output_tokens = 2000) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API anahtarı ayarlanmamış.');
        }

        $payload = [
            'model' => $this->model,
            'instructions' => (string) $instructions,
            'input' => (string) $input,
            'max_output_tokens' => max(1, (int) $max_output_tokens),
            'store' => false,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = isset($body['error']['message'])
                ? $body['error']['message']
                : 'Bilinmeyen hata';

            return new \WP_Error(
                'openai_error',
                sprintf('OpenAI API hatası (%d): %s', $status_code, $message)
            );
        }

        if (!is_array($body)) {
            return new \WP_Error('openai_parse_error', 'OpenAI yanıtı JSON olarak ayrıştırılamadı.');
        }

        $text = $this->extractOutputText($body);

        if ($text === '') {
            return new \WP_Error('openai_parse_error', 'OpenAI yanıtında metin çıktısı bulunamadı.');
        }

        return $text;
    }

    /**
     * Supports both the raw Responses API output array and output_text when
     * provided by an SDK/proxy layer.
     */
    private function extractOutputText(array $body) {
        if (isset($body['output_text']) && is_string($body['output_text'])) {
            return trim($body['output_text']);
        }

        if (empty($body['output']) || !is_array($body['output'])) {
            return '';
        }

        $parts = [];

        foreach ($body['output'] as $item) {
            if (empty($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (
                    isset($content['type'], $content['text']) &&
                    $content['type'] === 'output_text' &&
                    is_string($content['text'])
                ) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
