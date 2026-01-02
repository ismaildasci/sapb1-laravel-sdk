# Changelog

All notable changes to `laravel-sapb1` will be documented in this file.

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
