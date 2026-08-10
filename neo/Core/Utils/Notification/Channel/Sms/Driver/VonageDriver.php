<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Sms\Driver;

class VonageDriver implements DriverInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config
    ) {
    }

    public function send(string $to, string $body): void
    {
        $ch = curl_init('https://rest.nexmo.com/sms/json');
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => http_build_query([
                'api_key' => $this->config['api_key'],
                'api_secret' => $this->config['api_secret'],
                'from' => $this->config['from'],
                'to' => $to,
                'text' => $body,
            ]),
        ]);

        $response = curl_exec($ch);
        $data = json_decode((string)$response, true);

        if (($data['messages'][0]['status'] ?? null) !== '0') {
            throw new \RuntimeException("Vonage error: " . ($data['messages'][0]['error-text'] ?? 'Unknown'));
        }
    }
}