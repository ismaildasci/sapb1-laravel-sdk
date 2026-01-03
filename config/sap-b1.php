<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The default SAP B1 connection to use. You can define multiple
    | connections and switch between them as needed.
    |
    */
    'default' => env('SAP_B1_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | SAP B1 Connections
    |--------------------------------------------------------------------------
    |
    | Here you can define multiple SAP B1 Service Layer connections.
    | Each connection requires the Service Layer URL, company database,
    | and authentication credentials.
    |
    */
    'connections' => [
        'default' => [
            'base_url' => env('SAP_B1_URL'),
            'company_db' => env('SAP_B1_COMPANY_DB'),
            'username' => env('SAP_B1_USERNAME'),
            'password' => env('SAP_B1_PASSWORD'),
            'language' => env('SAP_B1_LANGUAGE', 23), // 23 = Turkish

            // OData version: 'v1' (OData v3) or 'v2' (OData v4)
            // SAP deprecated OData v3 in FP 2405, but v1 remains widely used
            'odata_version' => env('SAP_B1_ODATA_VERSION', 'v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | SAP B1 Service Layer uses session-based authentication. Sessions
    | expire after 30 minutes of inactivity. This configuration controls
    | how sessions are stored and refreshed.
    |
    */
    'session' => [
        // Session storage driver: redis, file, database
        'driver' => env('SAP_B1_SESSION_DRIVER', 'file'),

        // Redis connection name (when using redis driver)
        'redis_connection' => env('SAP_B1_REDIS_CONNECTION', 'default'),

        // Database connection name (when using database driver)
        'database_connection' => env('SAP_B1_DATABASE_CONNECTION'),

        // Key prefix for session storage
        'prefix' => 'sap_b1_session:',

        // Session TTL in seconds (SAP B1 default is 30 minutes)
        'ttl' => 1680, // 28 minutes (safety margin)

        // Refresh session when remaining time is less than this (seconds)
        'refresh_threshold' => 300, // 5 minutes

        // Lock configuration for concurrent session refresh
        'lock' => [
            'enabled' => true,
            'timeout' => 10,
        ],

        // Auto-refresh on 401 errors
        'auto_refresh' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure session pooling for high-concurrency scenarios.
    | When enabled, multiple sessions are maintained and distributed
    | across concurrent requests to prevent bottlenecks.
    |
    | Note: Session pooling is an advanced feature for high-load scenarios.
    | For most use cases, the default single-session approach is sufficient.
    |
    */
    'pool' => [
        'enabled' => env('SAP_B1_POOL_ENABLED', false),

        // Per-connection pool settings
        'connections' => [
            'default' => [
                // Minimum idle sessions to maintain
                'min_size' => 2,

                // Maximum total sessions
                'max_size' => 10,

                // Idle session timeout in seconds (sessions unused for this duration will be closed)
                'idle_timeout' => 600,

                // Maximum time to wait for a session in seconds
                'wait_timeout' => 30,
            ],
        ],

        // Session distribution algorithm: 'round_robin', 'least_connections', 'lifo'
        'algorithm' => 'round_robin',

        // Cleanup interval for expired sessions (in seconds)
        'cleanup_interval' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the underlying HTTP client (Guzzle) behavior for
    | requests to the SAP B1 Service Layer.
    |
    */
    'http' => [
        // Request timeout in seconds
        'timeout' => 30,

        // Connection timeout in seconds
        'connect_timeout' => 10,

        // SSL certificate verification
        'verify' => env('SAP_B1_VERIFY_SSL', true),

        // Retry configuration for failed requests
        'retry' => [
            'times' => 3,
            'sleep' => 1000, // milliseconds (base delay)
            'when' => [429, 500, 502, 503, 504], // HTTP status codes to retry

            // Exponential backoff configuration
            'exponential_backoff' => true,
            'max_delay' => 30000, // maximum delay in milliseconds
            'jitter' => 0.1, // random jitter factor (0-1)

            // Special handling for 502 proxy errors
            'proxy_error_delay' => 5000, // milliseconds per attempt
            'proxy_error_max_attempts' => 5, // separate max for 502 errors
        ],

        // Request compression (gzip)
        'compression' => [
            'enabled' => env('SAP_B1_COMPRESSION', false),
            'min_size' => 1024, // minimum body size in bytes to compress
        ],

        // Request ID tracking for correlation
        'request_id' => [
            'auto' => env('SAP_B1_AUTO_REQUEST_ID', false), // auto-generate for all requests
        ],

        // Endpoint-specific timeout overrides
        'timeouts' => [
            // 'Reports/*' => 120,
            // 'ProductionOrders' => 60,
        ],

        // Circuit Breaker configuration
        // Prevents cascading failures by blocking requests when too many errors occur
        'circuit_breaker' => [
            // Enable circuit breaker (disabled by default)
            'enabled' => env('SAP_B1_CIRCUIT_BREAKER', false),

            // Number of consecutive failures before opening the circuit
            'failure_threshold' => 5,

            // Seconds to wait before trying half-open state
            'open_duration' => 30,

            // Number of successful requests in half-open state to close the circuit
            'half_open_max_attempts' => 3,

            // Tracking scope: 'global' (single circuit) or 'endpoint' (per-endpoint)
            'scope' => 'global',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching for query results to improve performance.
    | When enabled, GET requests can be cached based on the endpoint
    | and query parameters.
    |
    */
    'cache' => [
        'enabled' => env('SAP_B1_CACHE_ENABLED', false),
        'driver' => env('SAP_B1_CACHE_DRIVER', 'file'),
        'ttl' => 300, // 5 minutes default
        'prefix' => 'sap_b1_cache:',

        // Endpoints to cache (glob patterns)
        'include' => [
            // 'Items',
            // 'BusinessPartners',
        ],

        // Endpoints to never cache
        'exclude' => [
            'Login',
            'Logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the attachments API for file uploads and downloads.
    |
    */
    'attachments' => [
        // Maximum file size in bytes (default: 10MB)
        'max_size' => 10 * 1024 * 1024,

        // Allowed file extensions
        'allowed_extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv',
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'zip', 'rar', '7z',
        ],

        // Temporary storage path for downloads
        'temp_path' => storage_path('app/sap-b1-attachments'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Profiling Configuration
    |--------------------------------------------------------------------------
    |
    | Enable query profiling to track performance of SAP B1 requests.
    | Useful for debugging and optimization.
    |
    */
    'profiling' => [
        'enabled' => env('SAP_B1_PROFILING', false),

        // Log slow queries (in milliseconds)
        'slow_query_threshold' => 1000,

        // Store profiling data
        'store' => 'log', // 'log', 'database', 'redis'
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Enable logging for debugging SAP B1 API requests and responses.
    | When enabled, all requests will be logged to the specified channel.
    |
    */
    'logging' => [
        'enabled' => env('SAP_B1_LOGGING', false),
        'channel' => env('SAP_B1_LOG_CHANNEL'),

        // Log levels for different events
        'levels' => [
            'request' => 'debug',
            'response' => 'debug',
            'error' => 'error',
            'retry' => 'warning',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, additional information about requests
    | and responses will be available for troubleshooting.
    |
    */
    'debug' => env('SAP_B1_DEBUG', false),
];
