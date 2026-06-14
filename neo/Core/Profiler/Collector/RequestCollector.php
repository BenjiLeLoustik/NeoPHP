<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Http\Request;

class RequestCollector implements CollectorInterface
{
    public function __construct(private readonly Request $request) {}

    public function getName(): string
    {
        return 'request';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'method' => $this->request->getMethod(),
            'path' => $this->request->getPath(),
            'query' => $this->request->allQuery(),
            'body' => $this->request->allBody(),
            'headers' => $this->request->headers(),
            'ip' => $this->request->getIp(),
            'user_agent' => $this->request->getUserAgent(),
        ];
    }
}