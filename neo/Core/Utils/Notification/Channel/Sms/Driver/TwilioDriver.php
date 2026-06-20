<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Sms\Driver;

use RuntimeException;

class TwilioDriver implements DriverInterface
{
    /** @var array<string, mixed> $config */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $to, string $body): void
    {
        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->config['account_sid']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => http_build_query([
                'From' => $this->config['from'],
                'To' => $to,
                'Body' => $body,
            ]),
            \CURLOPT_USERPWD => $this->config['account_sid'] . ':' . $this->config['auth_token'],
            \CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        if (curl_error($ch) !== '') {
            throw new RuntimeException("Twilio cURL error: " . curl_error($ch));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Twilio API Error: {$response}");
        }
    }
}