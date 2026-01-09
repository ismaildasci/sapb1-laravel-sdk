# Changelog

All notable changes to `laravel-sapb1` will be documented in this file.

## 1.8.0 - 2026-01-09

### Added

#### Request Middleware Pipeline
- **Middleware System**: Extensible request/response pipeline
  - `MiddlewareInterface` contract for custom middleware
  - `MiddlewarePipeline` for chaining middleware
  - `SapB1Client::pushMiddleware()` / `prependMiddleware()` / `removeMiddleware()`
  - Built-in middleware:
    - `LoggingMiddleware`: Request/response logging with sensitive data masking
    - `RetryMiddleware`: Configurable retries with exponential backoff
    - `TenantMiddleware`: Multi-tenant header injection

#### Metadata & Schema Discovery
- **MetadataManager**: SAP B1 schema introspection
  - `entities()`: List all available entity names
  - `entity('Name')`: Get EntitySchema with fields, UDFs, navigation properties
  - `hasEntity()` / `hasField()`: Quick existence checks
  - `udos()`: List User Defined Objects
  - `udts()`: List User Defined Tables
  - `udfs('TableName')`: Get User Defined Fields for entity
  - Automatic caching with configurable TTL
  - OData v3 (XML) and v4 (JSON) metadata parsing

#### SAP Error Code Database
- **ErrorCodeDatabase**: Human-readable SAP error messages
  - 50+ common SAP B1 error codes with descriptions
  - Actionable suggestions for each error
  - Error categories (authentication, validation, business_logic, etc.)
  - `isRetryable()` flag for automatic retry decisions
- **Enhanced ServiceLayerException**:
  - `getHumanMessage()`: User-friendly error message
  - `getSuggestion()`: How to fix the error
  - `getCategory()`: Error classification
  - `isRetryable()`: Whether retry might succeed

#### Change Detection Service
- **ChangeDetector**: Polling-based entity change tracking (webhook alternative)
  - `watch('Entity')`: Start watching entity for changes
  - `poll()`: Detect created/updated/deleted records
  - Callback system: `onCreated()`, `onUpdated()`, `onDeleted()`
  - Configurable track fields and filters
  - State caching for efficient change detection
- **EntityWatcher**: Configure what to watch
  - `keyField()`: Set primary key field
  - `track()`: Specify fields to monitor
  - `where()`: Filter watched records
  - `limit()`: Max records to track
- **ChangeSet**: Detected changes container

#### Audit Trail API
- **AuditService**: Access SAP B1 change/access logs
  - `entity('BusinessPartners')->key('C001')->get()`: Query change history
  - `accessLog()->user('manager')->getAccessLog()`: Query access log
  - Date filters: `since()`, `until()`, `between()`
  - Maps to SAP history tables (ACRD, AITM, etc.)

#### Alert Management API
- **AlertService**: SAP B1 internal messaging
  - `send()` / `sendMessage()`: Send internal messages
  - `configurations()`: List alert rules
  - `createRule()` / `updateRule()` / `deleteRule()`: Manage alerts
  - `pending()`: Get unread alerts
  - `markRead()`: Mark alert as read

#### Company Info API
- **CompanyService**: Company and system information
  - `info()`: Full company information
  - `name()` / `localCurrency()` / `country()` / `version()`
  - `adminInfo()`: Administrative settings
  - `serviceLayerInfo()`: Service Layer configuration
  - `isHana()` / `isMultiBranch()`: System capabilities

#### Connection Diagnostics
- **ConnectionDiagnostics**: Comprehensive connection health
  - `run()`: Full diagnostic report
  - `testConnectivity()`: DNS, TCP, HTTP, Auth tests
  - `measureLatency()`: Latency sampling with P95
  - `getHealthStatus()`: Quick health check
  - `getSessionStatus()`: Session information
  - `getPerformanceMetrics()`: Profiler integration

#### Multi-Tenant Session Isolation
- **TenantManager**: Multi-tenant SAP B1 support
  - `setTenant()` / `getTenant()`: Tenant context
  - `setResolver()`: Custom tenant resolution
  - `getConfig()`: Tenant-specific configuration
  - `forTenant()`: Execute in tenant context
- **TenantResolverInterface**: Implement custom resolution
- **DatabaseTenantResolver**: Example database-based resolver
- **TenantMiddleware**: Automatic tenant header injection

#### OpenTelemetry Integration
- **TelemetryService**: Distributed tracing support (optional)
  - `enable()` / `disable()`: Toggle telemetry
  - `recordMetric()`: Custom metrics
  - `recordRequest()` / `recordDuration()` / `recordError()`
  - Auto-enables via config when available
- **OpenTelemetryMiddleware**: Automatic span creation
  - Request/response attributes
  - Error recording with stack traces
  - Requires: `open-telemetry/sdk`, `open-telemetry/api`

#### New SapB1Client Methods
- `metadata()`: Access MetadataManager
- `audit()`: Access AuditService
- `alerts()`: Access AlertService
- `company()`: Access CompanyService
- `changes()`: Access ChangeDetector
- `diagnostics()`: Access ConnectionDiagnostics

### Changed

- ServiceProvider registers TenantManager and TelemetryService
- PendingRequest integrates middleware pipeline
- ServiceLayerException enhanced with ErrorCodeDatabase integration
- Version bumped to 1.8.0

---

## 1.7.0 - 2026-01-03

### Added

#### Session Pool
- **Session Pool**: High-concurrency session management for heavy load scenarios
  - `SessionPool` class implementing `SessionPoolInterface`
  - Acquire/release pattern for session usage
  - `PooledSession` value object wrapping SessionData with pool metadata
  - `PoolConfiguration` value object with validation

- **Pool Storage Drivers**:
  - `DatabasePoolStore`: Database-based pool storage with atomic operations
  - `RedisPoolStore`: Redis-based pool storage using Hash and Sets
  - Migration: `create_sapb1_session_pool_table` for database driver

- **Distribution Algorithms**:
  - `round_robin`: Evenly distributes across sessions (oldest released first)
  - `least_connections`: Selects least used session
  - `lifo`: Last-In-First-Out for cache locality

- **Pool Configuration**:
  - `min_size` / `max_size`: Pool size boundaries
  - `idle_timeout` / `wait_timeout`: Timeout settings
  - `warmup_on_boot`: Auto pre-create sessions on app boot
  - `validation_on_acquire`: Validate session before returning

- **New Events**:
  - `SessionAcquired`: Fired when session acquired from pool
  - `SessionReleased`: Fired when session released to pool
  - `PoolWarmedUp`: Fired when pool warmup completes
  - `PoolSessionExpired`: Fired when pooled session expires

- **New Exceptions**:
  - `PoolExhaustedException`: No session available within timeout
  - `PoolConfigurationException`: Invalid pool configuration

- **New Artisan Command**: `sap-b1:pool`
  - `status`: Show pool statistics and health
  - `warmup`: Pre-create sessions (`--count=N` to specify)
  - `drain`: Close and remove all sessions
  - `cleanup`: Remove expired sessions
  - `sessions`: List session summary by status

- **SapB1Client Pool Integration**:
  - `releaseSession()`: Release acquired session back to pool
  - `isUsingPool()`: Check if pool is active
  - `getPoolStats()`: Get pool statistics

### Changed

- `SapB1Client` now accepts optional `SessionPoolInterface` for pool usage
- `SessionManager` exposes `createNewSession()` for pool session creation
- ServiceProvider version updated to 1.7.0

### Tests

- Added 43 new unit tests for pool components
- Total tests: 164 (297 assertions)

---

## 1.6.0 - 2026-01-03

### Added

#### Circuit Breaker Pattern
- **Circuit Breaker**: Prevent cascading failures with automatic circuit breaking
  - `CircuitBreaker` class with CLOSED, OPEN, HALF_OPEN states
  - `CircuitBreakerInterface` contract for custom implementations
  - `CircuitBreakerOpenException` for circuit open scenarios
  - `CircuitBreakerStateChanged` event for monitoring state transitions
  - Configurable: `failure_threshold`, `open_duration`, `half_open_max_attempts`
  - Per-endpoint or global tracking via `scope` config
  - `withCircuitBreaker()` / `withoutCircuitBreaker()` methods on PendingRequest
  - Laravel Cache-based state storage
  - **Only real errors count as failures**: Connection timeouts and 5xx status codes
  - **Slow but successful responses are SUCCESS**, not failures

#### Request ID Enhancement
- **Auto Request ID in createRequest()**: Request IDs now properly applied
  - `withRequestId()` chained in `SapB1Client::createRequest()` when auto is enabled
  - Ensures request ID propagates through all request methods

### Fixed

- Fixed auto request ID not being applied via `SapB1Client.createRequest()`

### Tests

- Added comprehensive unit tests for CircuitBreaker (14 tests)
- Added CircuitBreakerOpenException tests

---

## 1.5.0 - 2026-01-03

### Added

#### OData v4 Support
- **Dual OData Version Support**: Both v1 (OData v3) and v2 (OData v4)
  - `odata_version` config option per connection (default: 'v1')
  - `useODataV4()` / `useODataV3()` / `withODataVersion()` fluent methods
  - Backward compatible - v1 remains default
  - SAP deprecated OData v3 in FP 2405

#### Error Handling & Resilience
- **429 Rate Limit Handling**: Automatic retry with Retry-After header
  - `RateLimitException` for rate limit errors
  - `parseRetryAfter()` for intelligent delay based on header
  - Added 429 to default retry status codes
- **502 Proxy Error Recovery**: Enhanced handling for proxy errors
  - `ProxyException` for proxy-related errors
  - Configurable longer delays for proxy errors (`proxy_error_delay`)
  - Separate max retry count for 502 errors (`proxy_error_max_attempts`)

#### Session Management
- **Preemptive Session Renewal**: Proactive session refresh
  - `getRemainingTtl()` method on SessionData
  - Automatic refresh before timeout based on threshold
  - Reduces latency caused by expired sessions

#### Performance
- **Request Compression**: Gzip compression for large payloads
  - `withCompression()` / `withoutCompression()` methods
  - Configurable minimum size threshold
  - Automatic Content-Encoding header

#### Observability
- **Request ID Tracking**: X-Request-ID header support
  - `withRequestId()` method for manual ID
  - `auto` config for automatic ID generation
  - Included in logs for correlation
  - `getRequestId()` on Response class

### Changed

- Updated retry status codes to include 429 (rate limit)
- Enhanced `sleepWithResponse()` for status-aware delays
- Added `shouldRetry()` special handling for 502 errors

---

## 1.4.0 - 2026-01-03

### Added

#### Session & Security Improvements
- **Session Auto-Refresh**: Automatic session refresh on 401 errors
  - `invalidateAndRefresh()` method in SessionManager
  - `isSessionError()` for detecting session-related errors
  - `withAutoRefresh()` / `withoutAutoRefresh()` on SapB1Client
  - Configurable via `session.auto_refresh` config option
- **Session Pool Foundation**: Interface and config for high-concurrency session pooling
  - `SessionPoolInterface` with acquire/release/stats methods
  - Pool configuration: min_size, max_size, idle_timeout, wait_timeout
  - Distribution algorithms: round_robin, least_connections, lifo

#### Error Handling
- **JsonDecodeException**: Dedicated exception for JSON parsing errors
  - `fromLastError()` static factory method
  - Includes body preview and error context
  - Used in Response::decodeBody() and SessionData::fromJson()

#### Security & Validation
- **OData Filter Sanitization**: Protection against OData injection
  - Field name validation (alphanumeric, underscore, dot, slash)
  - Operator whitelist (eq, ne, gt, ge, lt, le)
  - `strictMode()` / `withoutStrictMode()` for control

### Fixed

- **Pagination nextLink Parsing**: Correct URL parsing for OData pagination
  - Fixed regex to properly extract endpoint from /b1s/v1/ paths
  - Added fallback for unusual URL formats
- **Race Condition in File Lock**: Replaced TOCTOU-vulnerable code with flock()
  - Atomic lock acquisition using LOCK_EX | LOCK_NB
  - Proper file handle management for lock release

### Changed

- Updated SapB1Client CRUD methods to use auto-refresh wrapper
- Enhanced FileSessionDriver with proper file locking mechanism

---

## 1.3.0 - 2025-01-03

### Added

#### Performance (v1.1.0 features)
- **Batch Operations**: Execute multiple requests in a single HTTP call with changeset support
  - `BatchRequest` and `BatchResponse` classes
  - Atomic changeset transactions with `beginChangeset()` / `endChangeset()`
  - Support for GET, POST, PATCH, PUT, DELETE in batches
- **Exponential Backoff**: Smart retry logic with jitter to prevent thundering herd
- **Connection Pooling**: Shared Guzzle client with keep-alive for better performance
- **Request/Response Logging**: Debug logging with sensitive data masking (passwords, tokens)
- **Timeout Configuration**: Configurable request and connection timeouts

#### Files & Cache (v1.2.0 features)
- **Attachments API**: File upload/download support via `AttachmentsManager`
  - `upload()`, `download()`, `list()`, `delete()`, `metadata()`
  - File validation (size limits, allowed extensions)
- **Query Caching**: Cache GET request results with `QueryCache`
  - Pattern-based include/exclude rules
  - Configurable TTL
- **Cache Invalidation**: Smart cache invalidation with `CacheInvalidator`
  - Relation-based invalidation (e.g., Order change invalidates BusinessPartners cache)

#### Advanced Queries (v1.3.0 features)
- **SQL Queries**: Execute stored SQL queries via `SqlQueryBuilder`
  - `sql('QueryName')->param('key', 'value')->execute()`
  - Pagination with `top()` and `skip()`
- **Semantic Layer**: Query semantic layer views via `SemanticLayerClient`
  - `semantic('ViewName')->dimensions(...)->measures(...)->execute()`
- **Cross-Company Queries**: Query across company databases
  - `query()->crossCompany('*')` or `crossCompany('CompanyDB')`
- **Query Profiling**: Performance monitoring with `QueryProfiler`
  - Track slow queries, get statistics, analyze by endpoint

### Changed
- Updated `SapB1ServiceProvider` to register new services (QueryCache, CacheInvalidator, QueryProfiler)
- Version bumped to 1.3.0 in about command

---

## 1.0.0 - 2025-01-02

### Added

- **Core Client**: Full SAP B1 Service Layer API client with CRUD operations
- **Session Management**: Automatic session handling with multiple drivers (file, redis, database)
- **OData Query Builder**: Fluent query builder for complex OData queries
  - `select()`, `filter()`, `where()`, `whereIn()`, `whereContains()`
  - `whereStartsWith()`, `whereNull()`, `whereBetween()`
  - `orderBy()`, `orderByDesc()`, `top()`, `skip()`, `page()`
  - `expand()`, `inlineCount()`
- **Multiple Connections**: Support for multiple SAP B1 server connections
- **Response Handling**: Rich response object with OData metadata support
- **Health Checks**: Connection health monitoring with `SapB1HealthCheck` service
- **Artisan Commands**:
  - `sap-b1:status` - Check connection status
  - `sap-b1:session` - Manage sessions (login, logout, refresh, clear)
  - `sap-b1:health` - Health check for connections
- **Testing Utilities**:
  - `SapB1Fake` trait for mocking HTTP requests
  - `FakeResponse` class for building mock responses
  - Entity factories (BusinessPartner, Item, Order)
- **Events**: Request lifecycle events for monitoring and logging
  - `SessionCreated`, `SessionExpired`
  - `RequestSending`, `RequestSent`, `RequestFailed`
- **Exceptions**: Typed exceptions for different error scenarios
  - `AuthenticationException`, `ConnectionException`
  - `ServiceLayerException`, `SessionExpiredException`
- **Facade & Helper**: `SapB1` facade and `sap_b1()` helper function
- **Laravel Integration**: Full integration with Laravel 11.x and 12.x
