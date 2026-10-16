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
    /**
     * Start an OpenAI Responses background request. The API performs the long
     * generation independently: the WordPress PHP request returns quickly.
     * This deliberately does not use WP-Cron or a blocking 90-second request.
     */
    public function startBackgroundText($instructions, $input, $max_output_tokens = 3000) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API anahtarı ayarlanmamış.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $this->model,
                'instructions' => (string) $instructions,
                'input' => (string) $input,
                'max_output_tokens' => max(1, (int) $max_output_tokens),
                'background' => true,
                // Background results stay available temporarily for polling.
                // Do not persist responses beyond that window.
                'store' => true,
            ]),
            'timeout' => 25,
        ]);
        return $this->parseBackgroundResponse($response);
    }

    /**
     * Poll one OpenAI background response. The response ID is server-stored
     * and never accepted directly from an untrusted browser parameter.
     */
    public function pollBackgroundText($response_id) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API anahtarı ayarlanmamış.');
        }
        if (!is_string($response_id) || !preg_match('/^resp_[A-Za-z0-9_-]{5,200}$/', $response_id)) {
            return new \WP_Error('invalid_response_id', 'AI işlem kimliği geçersiz.');
        }
        $response = wp_remote_get(
            'https://api.openai.com/v1/responses/' . rawurlencode($response_id),
            [
                'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
                'timeout' => 20,
            ]
        );
        return $this->parseBackgroundResponse($response);
    }

    /**
     * @return array{status:string,response_id:string,text?:string}|\WP_Error
     */
    private function parseBackgroundResponse($response) {
        if (is_wp_error($response)) {
            return new \WP_Error('openai_transport_error',
                'OpenAI bağlantısı kurulamadı: ' . $response->get_error_message());
        }

        $http = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($http < 200 || $http >= 300) {
            $reason = is_array($body) && isset($body['error']['message'])
                && is_string($body['error']['message'])
                ? substr($body['error']['message'], 0, 300)
                : 'Bilinmeyen API hatası.';
            return new \WP_Error('openai_background_http_error',
                sprintf('OpenAI HTTP %d: %s', $http, $reason));
        }
        if (!is_array($body) || empty($body['id']) || !is_string($body['id'])) {
            return new \WP_Error('openai_background_parse_error', 'OpenAI geçerli bir işlem kimliği döndürmedi.');
        }
        $id = $body['id'];
        if (!preg_match('/^resp_[A-Za-z0-9_-]{5,200}$/', $id)) {
            return new \WP_Error('openai_background_parse_error', 'OpenAI işlem kimliği beklenen biçimde değil.');
        }
        $status = $body['status'] ?? null;
        if ($status === 'queued' || $status === 'in_progress') {
            return ['status' => 'running', 'response_id' => $id];
        }
        if ($status === 'completed') {
            $text = $this->extractOutputText($body);
            if ($text === '') {
                return new \WP_Error('openai_empty_text', 'OpenAI işlemi tamamladı ancak öneri metni döndürmedi.');
            }
            return ['status' => 'completed', 'response_id' => $id, 'text' => $text];
        }

        $reason = isset($body['error']['message']) && is_string($body['error']['message'])
            ? $body['error']['message']
            : ($body['incomplete_details']['reason'] ?? (is_string($status) ? $status : 'bilinmeyen durum'));
        if (!is_string($reason)) $reason = 'bilinmeyen durum';
        return new \WP_Error('openai_background_failed',
            'OpenAI üretimi tamamlanamadı: ' . substr($reason, 0, 300));
    }

}
