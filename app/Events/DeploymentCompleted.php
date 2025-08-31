<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $environment;
    public string $branch;
    public string $commit;
    public bool $success;
    public ?string $error;
    public array $details;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $environment,
        string $branch,
        string $commit,
        bool $success,
        ?string $error = null,
        array $details = []
    ) {
        $this->environment = $environment;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->success = $success;
        $this->error = $error;
        $this->details = $details;
    }
}