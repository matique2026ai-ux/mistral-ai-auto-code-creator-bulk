<?php
/**
 * AutoCoder V4 — AI Model Router
 * Abstraction multi-fournisseur : Mistral → OpenAI → Claude → Gemini
 * Fallback automatique entre providers, rotation de clés, retry.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class AIModel {
    private PDO $db;
    private string $provider = 'mistral';
    private string $model = 'devstral-2512';
    private string $currentKey = '';
    private int $currentKeyId = 0;

    private const PROVIDERS_CFG = null; // loaded from config

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Appelle l'IA avec fallback automatique entre providers.
     * Si le provider principal échoue, tente le suivant dans l'ordre défini.
     */
    public function call(array $messages, int $maxTokens = 4000, bool $jsonMode = true, string $step = '', string $preferredProvider = ''): array {
        $agentMap = json_decode(AC4_AGENT_MODEL_MAP, true);
        $stepCfg = $agentMap[$step] ?? ['provider' => 'mistral', 'model' => 'devstral-2512', 'max_tokens' => 16000];

        $provider = $preferredProvider ?: $stepCfg['provider'];
        $model = $stepCfg['model'];
        $tokens = max($maxTokens, $stepCfg['max_tokens']);

        $fallbackOrder = explode(',', AC4_PROVIDER_FALLBACK_ORDER);
        // Move preferred provider to front
        if ($provider && ($idx = array_search($provider, $fallbackOrder)) !== false) {
            unset($fallbackOrder[$idx]);
            array_unshift($fallbackOrder, $provider);
        }

        $lastError = '';
        foreach ($fallbackOrder as $provName) {
            $provName = trim($provName);
            if (!$provName) continue;

            $provCfg = $this->getProviderConfig($provName);
            if (!$provCfg) continue;

            // Get an API key for this provider
            $key = $this->getProviderKey($provName);
            if (!$key) {
                $this->log("warn", "Provider $provName: no active key, skipping");
                continue;
            }

            // Determine model for this provider
            $useModel = ($provName === $provider) ? $model : $provCfg['default_model'];

            // For Google Gemini, the model is in the URL
            $url = $provCfg['base_url'];
            if ($provName === 'google') {
                $url = str_replace('{model}', $useModel, $url) . '?key=' . $key['key_val'];
            }

            try {
                $result = $this->callProvider($provName, $url, $provCfg['headers'], $key['key_val'], $useModel, $messages, $tokens, $jsonMode);
                // Record token usage
                $tokensUsed = $result['tokens'] ?? 0;
                recordTokens($this->db, $key['id'], $tokensUsed, 0, $step);
                $this->log("ok", "{$provName}/{$useModel} — {$tokensUsed} tokens");
                return $result;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->log("warn", "{$provName}/{$useModel} failed: {$e->getMessage()}, fallback...");
                markKeyError($this->db, $key['id']);
                continue;
            }
        }

        throw new Exception("All AI providers failed. Last error: $lastError");
    }

    private function getProviderConfig(string $name): ?array {
        $providers = json_decode(AC4_PROVIDERS, true);
        return $providers[$name] ?? null;
    }

    private function getProviderKey(string $provider): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM api_keys WHERE provider = ? AND is_active = 1 AND error_count < " . AC4_MAX_KEY_ERRORS . "
             ORDER BY last_used ASC NULLS FIRST LIMIT 1"
        );
        $stmt->execute([$provider]);
        $key = $stmt->fetch();
        if ($key) {
            $this->db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$key['id']]);
        }
        return $key ?: null;
    }

    private function callProvider(string $provider, string $url, array $headers, string $keyVal, string $model, array $messages, int $maxTokens, bool $jsonMode): array {
        $payload = match ($provider) {
            'anthropic' => $this->buildAnthropicPayload($model, $messages, $maxTokens),
            'google' => $this->buildGooglePayload($model, $messages, $maxTokens, $jsonMode),
            'openai', 'mistral' => $this->buildOpenAICompatiblePayload($model, $messages, $maxTokens, $jsonMode),
            default => $this->buildOpenAICompatiblePayload($model, $messages, $maxTokens, $jsonMode),
        };

        $ch = curl_init($url);
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = str_replace('{key}', $keyVal, $k) . ': ' . str_replace('{key}', $keyVal, $v);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$resp) {
            throw new Exception("HTTP $code" . ($error ? " — $error" : ''));
        }

        return $this->parseProviderResponse($provider, $resp);
    }

    private function buildOpenAICompatiblePayload(string $model, array $messages, int $maxTokens, bool $jsonMode): array {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];
        return $payload;
    }

    private function buildAnthropicPayload(string $model, array $messages, int $maxTokens, bool $jsonMode): array {
        // Anthropic uses a different message format
        $system = '';
        $filtered = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system .= $msg['content'] . "\n";
            } else {
                $filtered[] = $msg;
            }
        }
        $payload = [
            'model' => $model,
            'messages' => $filtered,
            'max_tokens' => $maxTokens,
        ];
        if ($system) $payload['system'] = trim($system);
        if ($jsonMode) {
            $payload['thinking'] = ['type' => 'enabled', 'budget_tokens' => min(2000, intval($maxTokens * 0.3))];
        }
        return $payload;
    }

    private function buildGooglePayload(string $model, array $messages, int $maxTokens, bool $jsonMode): array {
        // Convert OpenAI-style messages to Gemini format
        $contents = [];
        $system = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system .= $msg['content'] . "\n";
            } else {
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => 0.7,
            ],
        ];
        if ($system) {
            $payload['systemInstruction'] = ['parts' => [['text' => trim($system)]]];
        }
        return $payload;
    }

    private function parseProviderResponse(string $provider, string $raw): array {
        $data = json_decode($raw, true);
        if (!$data) throw new Exception("Invalid JSON response from $provider");

        return match ($provider) {
            'anthropic' => [
                'content' => $data['content'][0]['text'] ?? '',
                'tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            'google' => [
                'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
                'tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ],
            default => [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        };
    }

    private function log(string $level, string $message): void {
        echo json_encode(['type' => 'log', 'level' => $level, 'step' => 'ai_model', 'message' => $message]) . "\n";
        if (ob_get_level()) ob_flush(); flush();
    }
}
