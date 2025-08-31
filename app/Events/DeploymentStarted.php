<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $environment;
    public string $branch;
    public string $commit;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(string $environment, string $branch, string $commit, array $metadata = [])
    {
        $this->environment = $environment;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->metadata = $metadata;
    }
}