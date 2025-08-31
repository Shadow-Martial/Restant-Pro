<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use App\Services\GrafanaCloudService;
use Illuminate\Support\Facades\Queue;

class GrafanaCloudLogHandler extends AbstractProcessingHandler
{
    private GrafanaCloudService $grafanaService;
    private array $buffer = [];
    private int $batchSize;

    public function __construct(GrafanaCloudService $grafanaService, $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->grafanaService = $grafanaService;
        $this->batchSize = config('monitoring.grafana.logs.batch_size', 50);
    }

    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(LogRecord $record): void
    {
        // Only send logs if Grafana integration is enabled
        if (!config('monitoring.grafana.logs.enabled', true)) {
            return;
        }

        // Check if this log level should be sent to Grafana
        $allowedLevels = config('monitoring.grafana.logs.levels', ['error', 'warning', 'info']);
        if (!in_array(strtolower($record->level->name), $allowedLevels)) {
            return;
        }

        $logEntry = [
            'timestamp' => $record->datetime->getTimestamp(),
            'level' => strtolower($record->level->name),
            'message' => $record->formatted,
            'labels' => [
                'channel' => $record->channel,
                'level' => strtolower($record->level->name),
                'app' => config('app.name'),
                'environment' => config('app.env'),
            ]
        ];

        // Add context as labels if available
        if (!empty($record->context)) {
            $logEntry['labels'] = array_merge($logEntry['labels'], $this->formatContext($record->context));
        }

        // Add to buffer
        $this->buffer[] = $logEntry;

        // Send batch if buffer is full
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Format context data for Grafana labels
     */
    private function formatContext(array $context): array
    {
        $labels = [];
        
        foreach ($context as $key => $value) {
            // Only include scalar values as labels
            if (is_scalar($value)) {
                $labels['context_' . $key] = (string)$value;
            }
        }

        return $labels;
    }

    /**
     * Flush buffered logs to Grafana Cloud
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        // Send logs asynchronously to avoid blocking the request
        Queue::push(function () {
            $this->grafanaService->sendLogs($this->buffer);
        });

        $this->buffer = [];
    }

    /**
     * Close the handler and flush remaining logs
     */
    public function close(): void
    {
        $this->flush();
        parent::close();
    }

    /**
     * Destructor to ensure logs are flushed
     */
    public function __destruct()
    {
        $this->flush();
    }
}