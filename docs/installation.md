# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 11.x or 12.x
- SAP Business One with Service Layer enabled

## Install via Composer

```bash
composer require ismaildasci/laravel-sapb1
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="sap-b1-config"
```

## Database Sessions (Optional)

If you plan to use the database session driver:

```bash
php artisan vendor:publish --tag="sap-b1-migrations"
php artisan migrate
```

## Session Pool (Optional)

For high-concurrency scenarios with session pooling:

```bash
php artisan vendor:publish --tag="sap-b1-pool-migrations"
php artisan migrate
```

## Environment Variables

Add to your `.env` file:

```env
SAP_B1_URL=https://your-sap-server:50000
SAP_B1_COMPANY_DB=YOUR_COMPANY_DB
SAP_B1_USERNAME=manager
SAP_B1_PASSWORD=your_password
SAP_B1_SESSION_DRIVER=file
```

## Verify Installation

```bash
php artisan sap-b1:status --test
```

Next: [Configuration](configuration.md)
