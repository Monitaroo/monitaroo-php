# Monitaroo PHP SDK

Official PHP SDK for [Monitaroo](https://monitaroo.com) - Logs, Metrics & Monitoring.

[![Latest Version](https://img.shields.io/packagist/v/monitaroo/monitaroo.svg)](https://packagist.org/packages/monitaroo/monitaroo)
[![PHP Version](https://img.shields.io/packagist/php-v/monitaroo/monitaroo.svg)](https://packagist.org/packages/monitaroo/monitaroo)
[![License](https://img.shields.io/packagist/l/monitaroo/monitaroo.svg)](https://packagist.org/packages/monitaroo/monitaroo)

## Installation

```bash
composer require monitaroo/monitaroo
```

**Requirements:** PHP 7.2+ (v1.x) or PHP 8.0+ (v2.x)

## Quick Start

```php
use Monitaroo\Monitaroo;

// Initialize once (e.g., in bootstrap)
Monitaroo::init([
    'apiKey' => 'mk_your_api_key',
    'service' => 'my-app',
    'environment' => 'production',
]);

// Log messages (buffered, auto-flushed at end of request)
Monitaroo::info('User logged in', ['user_id' => 123]);
Monitaroo::error('Payment failed', ['order_id' => 456, 'tags' => ['type' => 'payment']]);

// Record metrics
Monitaroo::increment('orders.completed');
Monitaroo::gauge('queue.size', 42);
Monitaroo::timing('api.response_time', 145.5);

// Timer helper
$stop = Monitaroo::startTimer('db.query');
$result = $db->query('SELECT ...');
$elapsed = $stop(); // Automatically records the metric
```

## Configuration

```php
Monitaroo::init([
    // Required
    'apiKey' => 'mk_xxx',           // Your API key from Monitaroo dashboard
    
    // Optional - Default context for all logs
    'service' => 'my-app',          // Service name
    'environment' => 'production',  // Environment (production, staging, dev)
    'host' => 'server-01',          // Host name (auto-detected if not set)
    
    // Optional - Performance tuning
    'endpoint' => 'https://api.monitaroo.com',  // API endpoint
    'batchSize' => 100,             // Flush after N items (default: 100)
    'autoFlush' => true,            // Auto-flush on shutdown (default: true)
]);
```

## Logging

### Log Levels

```php
Monitaroo::trace('Detailed trace info');
Monitaroo::debug('Debug information');
Monitaroo::info('General information');
Monitaroo::warn('Warning message');
Monitaroo::error('Error occurred');
Monitaroo::fatal('Fatal error');
```

### Context & Tags

```php
// Context becomes attributes (searchable metadata)
Monitaroo::info('Order placed', [
    'order_id' => 123,
    'amount' => 99.99,
    'customer' => 'john@example.com',
]);

// Tags are indexed for fast filtering
Monitaroo::info('Order placed', [
    'order_id' => 123,
    'tags' => [
        'type' => 'order',
        'country' => 'FR',
    ],
]);
```

### Exception Logging

```php
try {
    // ...
} catch (\Exception $e) {
    Monitaroo::error('Operation failed', [
        'exception' => $e,  // Automatically extracts class, message, trace
    ]);
}
```

### PSR-3 Logger

```php
use Monitaroo\Monitaroo;

// Get PSR-3 compatible logger
$logger = Monitaroo::logger();

// Use with any PSR-3 compatible library
$logger->info('Hello {name}', ['name' => 'World']);
```

## Metrics

### Counter (increments)

```php
// Simple increment
Monitaroo::increment('api.requests');

// Increment by value
Monitaroo::increment('items.sold', 5);

// With tags
Monitaroo::increment('api.requests', 1, [
    'endpoint' => '/users',
    'method' => 'GET',
]);
```

### Gauge (current value)

```php
Monitaroo::gauge('queue.size', 142);
Monitaroo::gauge('memory.used_mb', memory_get_usage(true) / 1024 / 1024);
Monitaroo::gauge('connections.active', $pool->getActiveCount(), [
    'pool' => 'database',
]);
```

### Timer (durations)

```php
// Manual timing (milliseconds)
$start = microtime(true);
$result = doSomething();
$elapsed = (microtime(true) - $start) * 1000;
Monitaroo::timing('operation.duration', $elapsed);

// Using helper
$stop = Monitaroo::startTimer('db.query', ['table' => 'users']);
$users = $db->query('SELECT * FROM users');
$stop(); // Records metric automatically
```

### Histogram (distributions)

```php
Monitaroo::histogram('order.amount', $order->total);
Monitaroo::histogram('file.size_kb', filesize($path) / 1024);
```

## Flushing

By default, logs and metrics are buffered and automatically flushed:
- When batch size is reached (default: 100)
- At the end of the request (`register_shutdown_function`)
- Using `fastcgi_finish_request()` if available (response sent first)

### Manual Flush

```php
// Force immediate flush
Monitaroo::flush();
```

### Disable Auto-Flush

```php
Monitaroo::init([
    'apiKey' => 'mk_xxx',
    'autoFlush' => false,  // Manual flush only
]);

// Must call flush manually
Monitaroo::flush();
```

## Framework Integration

### Laravel

Use the dedicated Laravel package for seamless integration:

```bash
composer require monitaroo/monitaroo-laravel
```

See [monitaroo/monitaroo-laravel](https://github.com/Monitaroo/monitaroo-laravel) for details.

### Symfony

Use the dedicated Symfony bundle:

```bash
composer require monitaroo/monitaroo-symfony
```

See [monitaroo/monitaroo-symfony](https://github.com/Monitaroo/monitaroo-symfony) for details.

## Advanced Usage

### Custom Transport

```php
use Monitaroo\Client;
use Monitaroo\Transport\TransportInterface;

class MyTransport implements TransportInterface
{
    public function sendLogs(array $logs): void
    {
        // Custom implementation
    }
    
    public function sendMetrics(array $metrics): void
    {
        // Custom implementation
    }
}

$client = new Client([
    'apiKey' => 'mk_xxx',
    'transport' => new MyTransport(),
]);
```

### Multiple Clients

```php
use Monitaroo\Client;

$clientA = new Client(['apiKey' => 'mk_app_a', 'service' => 'app-a']);
$clientB = new Client(['apiKey' => 'mk_app_b', 'service' => 'app-b']);

$clientA->info('From app A');
$clientB->info('From app B');
```

## License

MIT License. See [LICENSE](LICENSE) for details.
