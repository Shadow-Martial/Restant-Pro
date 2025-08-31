<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentRollback
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $environment;
    public string $reason;
    public ?string $previousCommit;
    public array $details;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $environment,
        string $reason,
        ?string $previousCommit = null,
        array $details = []
    ) {
        $this->environment = $environment;
        $this->reason = $reason;
        $this->previousCommit = $previousCommit;
        $this->details = $details;
    }
}