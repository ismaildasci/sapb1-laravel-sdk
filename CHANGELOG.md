# Changelog

All notable changes to `laravel-sapb1` will be documented in this file.

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
