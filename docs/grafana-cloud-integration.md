# Grafana Cloud Integration

This document describes the Grafana Cloud integration for monitoring application performance, infrastructure metrics, and log aggregation.

## Overview

The Grafana Cloud integration provides comprehensive monitoring capabilities including:

- **Application Performance Monitoring (APM)**: HTTP request tracking, database query monitoring, and performance metrics
- **Infrastructure Monitoring**: Memory usage, disk usage, database connections, and cache metrics
- **Log Aggregation**: Centralized logging with structured data sent to Grafana Cloud Loki
- **Custom Metrics**: Business-specific metrics and KPIs

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# Grafana Cloud Configuration
GRAFANA_ENABLED=true
GRAFANA_CLOUD_INSTANCE_ID=your-instance-id
GRAFANA_CLOUD_API_KEY=your-api-key

# Optional: Customize endpoints (defaults provided)
GRAFANA_METRICS_ENDPOINT=https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push
GRAFANA_LOGS_ENDPOINT=https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push

# Performance Monitoring
GRAFANA_METRICS_ENABLED=true
GRAFANA_LOGS_ENABLED=true
GRAFANA_INFRASTRUCTURE_ENABLED=true
GRAFANA_APM_ENABLED=true

# Thresholds
GRAFANA_SLOW_REQUEST_THRESHOLD=1.0
GRAFANA_HIGH_QUERY_THRESHOLD=10
```

### Configuration Files

The integration uses several configuration files:

- `config/monitoring.php`: Main monitoring configuration
- `config/deployment.php`: Deployment-specific monitoring settings
- `config/logging.php`: Log channel configuration (includes Grafana channel)

## Components

### 1. GrafanaCloudService

The main service class that handles all interactions with Grafana Cloud APIs.

**Key Methods:**
- `sendMetric()`: Send custom metrics
- `sendPerformanceMetrics()`: Send batch performance metrics
- `trackHttpRequest()`: Track HTTP request metrics
- `trackDatabaseQuery()`: Track database query metrics
- `sendLogs()`: Send logs to Loki
- `trackInfrastructureMetrics()`: Collect and send infrastructure metrics

### 2. GrafanaPerformanceMiddleware

Middleware that automatically tracks HTTP request performance:

- Request duration
- Database query count
- Memory usage
- Error rates
- Slow request detection

### 3. Log Integration

Custom log handler that sends logs to Grafana Cloud Loki:

- Structured logging with labels
- Batch processing for efficiency
- Asynchronous sending to avoid blocking requests
- Configurable log levels

### 4. Infrastructure Monitoring

Automated collection of infrastructure metrics:

- Memory usage (current and peak)
- Disk usage
- Database connection status
- Cache performance (Redis metrics)
- Queue metrics

## Usage

### Sending Custom Metrics

```php
use App\Facades\GrafanaCloud;

// Send a simple metric
GrafanaCloud::sendMetric('user_registrations', 1, [
    'source' => 'web',
    'plan' => 'premium'
]);

// Send multiple performance metrics
$metrics = [
    [
        'name' => 'order_processing_time',
        'value' => 2.5,
        'labels' => ['restaurant_id' => '123']
    ],
    [
        'name' => 'payment_success_rate',
        'value' => 0.98,
        'labels' => ['gateway' => 'stripe']
    ]
];

GrafanaCloud::sendPerformanceMetrics($metrics);
```

### Manual Infrastructure Metrics

```php
// Collect and send infrastructure metrics manually
GrafanaCloud::trackInfrastructureMetrics();

// Or use the Artisan command
php artisan grafana:collect-metrics
```

### Health Checks

The integration includes health check endpoints:

- `GET /api/health`: Simple health check
- `GET /api/health/detailed`: Comprehensive health check including Grafana connectivity

## Scheduled Tasks

Add the following to your `app/Console/Kernel.php` schedule:

```php
protected function schedule(Schedule $schedule)
{
    // Collect infrastructure metrics every 5 minutes
    $schedule->command('grafana:collect-metrics')
             ->everyFiveMinutes()
             ->withoutOverlapping();
}
```

## Metrics Reference

### HTTP Request Metrics

- `laravel_http_requests_total`: Total HTTP requests
- `laravel_http_request_duration_seconds`: Request duration
- `laravel_slow_requests_total`: Slow requests (>1s by default)
- `laravel_error_responses_total`: Error responses (4xx, 5xx)

### Database Metrics

- `laravel_database_queries_total`: Total database queries
- `laravel_database_query_duration_seconds`: Query duration
- `laravel_database_connection_status`: Database connectivity
- `laravel_database_active_connections`: Active database connections

### Infrastructure Metrics

- `laravel_memory_usage_bytes`: Current memory usage
- `laravel_memory_peak_bytes`: Peak memory usage
- `laravel_disk_usage_bytes`: Disk usage
- `laravel_cache_memory_usage_bytes`: Cache memory usage (Redis)
- `laravel_cache_hit_rate`: Cache hit rate

### Health Check Metrics

- `laravel_service_health`: Service health status (0/1)
- `laravel_service_response_time_ms`: Service response time

## Labels

All metrics include default labels:

- `app`: Application name
- `environment`: Environment (production, staging, etc.)
- `version`: Application version
- `server`: Server hostname

Additional labels can be added per metric for better filtering and grouping.

## Alerting

Configure alerts in Grafana Cloud based on the collected metrics:

### Recommended Alerts

1. **High Error Rate**: `laravel_error_responses_total` > 5% of total requests
2. **Slow Responses**: `laravel_http_request_duration_seconds` P95 > 2 seconds
3. **High Memory Usage**: `laravel_memory_usage_bytes` > 80% of available memory
4. **Database Issues**: `laravel_database_connection_status` = 0
5. **Service Down**: `laravel_service_health` = 0

## Troubleshooting

### Common Issues

1. **Metrics not appearing**: Check API key and instance ID configuration
2. **High memory usage**: Adjust batch sizes in configuration
3. **Missing logs**: Verify log levels in `monitoring.grafana.logs.levels`
4. **Performance impact**: Reduce sample rates or disable specific tracking

### Debug Mode

Enable debug logging to troubleshoot issues:

```env
LOG_LEVEL=debug
```

Check logs for Grafana-related errors:

```bash
tail -f storage/logs/laravel.log | grep -i grafana
```

### Health Check

Use the health check endpoint to verify connectivity:

```bash
curl http://your-app.com/api/health/detailed
```

## Performance Considerations

- Metrics are sent asynchronously to avoid blocking requests
- Logs are batched and sent in background jobs
- Infrastructure metrics are collected on a schedule
- Failed requests are logged but don't block application functionality
- Consider adjusting sample rates for high-traffic applications

## Security

- API keys are stored as environment variables
- All communication uses HTTPS
- Sensitive data is not included in metrics or logs
- Labels are sanitized to prevent injection attacks