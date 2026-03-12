<?php

declare(strict_types=1);

namespace Monitaroo\Transport;

class HttpTransport implements TransportInterface
{
    private string $apiKey;
    private string $endpoint;
    private int $timeout;
    private int $maxRetries;
    private array $retryDelays;

    public function __construct(
        string $apiKey,
        string $endpoint = 'https://api.monitaroo.com',
        int $timeout = 5,
        int $maxRetries = 3
    ) {
        $this->apiKey = $apiKey;
        $this->endpoint = rtrim($endpoint, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelays = [100, 500, 1000]; // ms
    }

    /**
     * @inheritDoc
     */
    public function sendLogs(array $logs): void
    {
        if (empty($logs)) {
            return;
        }

        $this->send('/api/dev/v1/logs', ['logs' => $logs]);
    }

    /**
     * @inheritDoc
     */
    public function sendMetrics(array $metrics): void
    {
        if (empty($metrics)) {
            return;
        }

        $this->send('/api/dev/v1/metrics', ['metrics' => $metrics]);
    }

    /**
     * Send data to the API with retry logic.
     *
     * @param string $path API path
     * @param array $data Data to send
     * @throws \RuntimeException If all retries fail
     */
    private function send(string $path, array $data): void
    {
        $url = $this->endpoint . $path;
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->doRequest($url, $payload);
                return; // Success
            } catch (\RuntimeException $e) {
                $lastException = $e;

                // Don't retry on client errors (4xx)
                if (str_contains($e->getMessage(), 'HTTP 4')) {
                    throw $e;
                }

                // Wait before retry (with exponential backoff)
                if ($attempt < $this->maxRetries) {
                    $delayMs = $this->retryDelays[$attempt] ?? 1000;
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Failed to send data to Monitaroo');
    }

    /**
     * Perform the actual HTTP request using cURL.
     *
     * @param string $url
     * @param string $payload JSON payload
     * @throws \RuntimeException On HTTP error
     */
    private function doRequest(string $url, string $payload): void
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'User-Agent: monitaroo-php/1.0',
                ],
                // Don't verify SSL in dev (remove in production)
                // CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($response === false) {
                throw new \RuntimeException('cURL error: ' . $error);
            }

            // 2xx = success, 202 = accepted (async processing)
            if ($httpCode >= 200 && $httpCode < 300) {
                return;
            }

            // Parse error response
            $errorMessage = 'HTTP ' . $httpCode;
            $responseData = json_decode($response, true);
            if (isset($responseData['message'])) {
                $errorMessage .= ': ' . $responseData['message'];
            } elseif (isset($responseData['error'])) {
                $errorMessage .= ': ' . $responseData['error'];
            }

            throw new \RuntimeException($errorMessage);
        } finally {
            curl_close($ch);
        }
    }
}
