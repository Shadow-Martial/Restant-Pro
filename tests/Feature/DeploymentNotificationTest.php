<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DeploymentNotificationService;
use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeploymentNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock external services
        Mail::fake();
        Http::fake();
        Event::fake();
    }

    public function test_deployment_started_notification()
    {
        $service = app(DeploymentNotificationService::class);
        
        $service->notifyDeploymentStarted('staging', 'main', 'abc123');
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function test_deployment_success_notification()
    {
        $service = app(DeploymentNotificationService::class);
        
        $service->notifyDeploymentSuccess('production', 'main', 'abc123', [
            'duration' => '2m 30s',
            'migrations_run' => 3
        ]);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function test_deployment_failure_notification()
    {
        $service = app(DeploymentNotificationService::class);
        
        $service->notifyDeploymentFailure('staging', 'main', 'abc123', 'Migration failed', [
            'step' => 'database_migration'
        ]);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function test_deployment_rollback_notification()
    {
        $service = app(DeploymentNotificationService::class);
        
        $service->notifyRollback('production', 'Health check failed', 'def456', [
            'health_check_url' => '/health'
        ]);
        
        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    public function test_deployment_events_are_fired()
    {
        event(new DeploymentStarted('staging', 'main', 'abc123'));
        event(new DeploymentCompleted('staging', 'main', 'abc123', true));
        event(new DeploymentRollback('staging', 'Health check failed'));

        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentCompleted::class);
        Event::assertDispatched(DeploymentRollback::class);
    }

    public function test_deployment_notify_command_started()
    {
        $this->artisan('deployment:notify', [
            'type' => 'started',
            'environment' => 'staging',
            '--branch' => 'main',
            '--commit' => 'abc123'
        ])->assertExitCode(0);
    }

    public function test_deployment_notify_command_success()
    {
        $this->artisan('deployment:notify', [
            'type' => 'success',
            'environment' => 'production',
            '--branch' => 'main',
            '--commit' => 'abc123',
            '--details' => ['duration=2m30s', 'migrations=3']
        ])->assertExitCode(0);
    }

    public function test_deployment_notify_command_failure()
    {
        $this->artisan('deployment:notify', [
            'type' => 'failure',
            'environment' => 'staging',
            '--branch' => 'main',
            '--commit' => 'abc123',
            '--error' => 'Migration failed'
        ])->assertExitCode(0);
    }

    public function test_deployment_notify_command_rollback()
    {
        $this->artisan('deployment:notify', [
            'type' => 'rollback',
            'environment' => 'production',
            '--reason' => 'Health check failed',
            '--previous-commit' => 'def456'
        ])->assertExitCode(0);
    }

    public function test_test_notification_command()
    {
        $this->artisan('deployment:test-notification', [
            'type' => 'started',
            '--environment' => 'staging'
        ])->assertExitCode(0);
    }
}