<?php

declare(strict_types=1);

namespace SapB1\Errors;

class ErrorCodeDatabase
{
    /**
     * SAP B1 error codes with human-readable messages and suggestions.
     *
     * @var array<string|int, array{message: string, suggestion: string, category: string}>
     */
    protected static array $errors = [
        // Authentication & Session Errors
        -100 => [
            'message' => 'Invalid login credentials',
            'suggestion' => 'Verify username and password in your configuration',
            'category' => 'authentication',
        ],
        -101 => [
            'message' => 'Session timed out',
            'suggestion' => 'Session has expired. The SDK will automatically refresh the session',
            'category' => 'session',
        ],
        -102 => [
            'message' => 'User locked out',
            'suggestion' => 'User account is locked. Contact SAP administrator',
            'category' => 'authentication',
        ],
        -103 => [
            'message' => 'License not found for user',
            'suggestion' => 'Assign appropriate SAP B1 license to this user',
            'category' => 'license',
        ],
        -104 => [
            'message' => 'Maximum concurrent users reached',
            'suggestion' => 'Wait for other users to log out or increase license count',
            'category' => 'license',
        ],

        // Document Errors
        -5001 => [
            'message' => 'Document cannot be added',
            'suggestion' => 'Check required fields and data integrity',
            'category' => 'document',
        ],
        -5002 => [
            'message' => 'Insufficient stock quantity',
            'suggestion' => 'Check item availability in warehouse or adjust quantity',
            'category' => 'inventory',
        ],
        -5003 => [
            'message' => 'Document cannot be updated',
            'suggestion' => 'Document may be closed, cancelled, or locked by another user',
            'category' => 'document',
        ],
        -5004 => [
            'message' => 'Document cannot be cancelled',
            'suggestion' => 'Check if document has related transactions that need to be cancelled first',
            'category' => 'document',
        ],
        -5005 => [
            'message' => 'Duplicate document number',
            'suggestion' => 'Document number already exists. Let system auto-generate or use unique number',
            'category' => 'document',
        ],
        -5006 => [
            'message' => 'Exchange rate not defined',
            'suggestion' => 'Define exchange rate for the document currency and date',
            'category' => 'finance',
        ],

        // Business Partner Errors
        -1001 => [
            'message' => 'Business partner not found',
            'suggestion' => 'Verify CardCode exists or create the business partner first',
            'category' => 'master_data',
        ],
        -1002 => [
            'message' => 'Business partner is inactive',
            'suggestion' => 'Activate the business partner or use a different one',
            'category' => 'master_data',
        ],
        -1003 => [
            'message' => 'Credit limit exceeded',
            'suggestion' => 'Request credit limit increase or collect outstanding payments',
            'category' => 'finance',
        ],
        -1004 => [
            'message' => 'Duplicate CardCode',
            'suggestion' => 'Business partner code already exists. Use a unique code',
            'category' => 'master_data',
        ],
        -1005 => [
            'message' => 'Business partner on hold',
            'suggestion' => 'Business partner is blocked. Contact finance department',
            'category' => 'master_data',
        ],

        // Item Errors
        -2001 => [
            'message' => 'Item not found',
            'suggestion' => 'Verify ItemCode exists or create the item first',
            'category' => 'master_data',
        ],
        -2002 => [
            'message' => 'Item is inactive',
            'suggestion' => 'Activate the item or use a different one',
            'category' => 'master_data',
        ],
        -2003 => [
            'message' => 'Invalid warehouse for item',
            'suggestion' => 'Item is not assigned to specified warehouse. Update item warehouse settings',
            'category' => 'inventory',
        ],
        -2004 => [
            'message' => 'Batch number required',
            'suggestion' => 'Item is batch-managed. Specify batch number',
            'category' => 'inventory',
        ],
        -2005 => [
            'message' => 'Serial number required',
            'suggestion' => 'Item is serial-managed. Specify serial number',
            'category' => 'inventory',
        ],
        -2006 => [
            'message' => 'Invalid unit of measure',
            'suggestion' => 'Unit of measure not defined for item. Update item UoM settings',
            'category' => 'master_data',
        ],

        // Warehouse Errors
        -3001 => [
            'message' => 'Warehouse not found',
            'suggestion' => 'Verify warehouse code exists',
            'category' => 'inventory',
        ],
        -3002 => [
            'message' => 'Bin location required',
            'suggestion' => 'Warehouse is bin-enabled. Specify bin location',
            'category' => 'inventory',
        ],
        -3003 => [
            'message' => 'Negative stock not allowed',
            'suggestion' => 'Enable negative stock or ensure sufficient quantity',
            'category' => 'inventory',
        ],

        // Financial Errors
        -4001 => [
            'message' => 'Account not found',
            'suggestion' => 'Verify G/L account code exists in chart of accounts',
            'category' => 'finance',
        ],
        -4002 => [
            'message' => 'Period is closed',
            'suggestion' => 'Posting date is in a closed period. Change date or reopen period',
            'category' => 'finance',
        ],
        -4003 => [
            'message' => 'Journal entry not balanced',
            'suggestion' => 'Debit and credit amounts must be equal',
            'category' => 'finance',
        ],
        -4004 => [
            'message' => 'Cost center required',
            'suggestion' => 'Account requires cost center distribution. Specify cost center',
            'category' => 'finance',
        ],

        // Approval Errors
        -6001 => [
            'message' => 'Document requires approval',
            'suggestion' => 'Document triggered approval procedure. Wait for approval or check draft',
            'category' => 'workflow',
        ],
        -6002 => [
            'message' => 'Cannot approve own document',
            'suggestion' => 'Different user must approve this document',
            'category' => 'workflow',
        ],
        -6003 => [
            'message' => 'Approval already processed',
            'suggestion' => 'Document has already been approved or rejected',
            'category' => 'workflow',
        ],

        // System Errors
        -10 => [
            'message' => 'Database connection error',
            'suggestion' => 'Check database server connectivity',
            'category' => 'system',
        ],
        -20 => [
            'message' => 'Internal server error',
            'suggestion' => 'Contact SAP administrator. Check Service Layer logs',
            'category' => 'system',
        ],

        // Common OData Errors
        '400' => [
            'message' => 'Bad request',
            'suggestion' => 'Check request syntax and required fields',
            'category' => 'request',
        ],
        '401' => [
            'message' => 'Unauthorized',
            'suggestion' => 'Session expired or invalid credentials',
            'category' => 'authentication',
        ],
        '403' => [
            'message' => 'Forbidden',
            'suggestion' => 'User does not have permission for this operation',
            'category' => 'authorization',
        ],
        '404' => [
            'message' => 'Resource not found',
            'suggestion' => 'Entity or endpoint does not exist',
            'category' => 'request',
        ],
        '409' => [
            'message' => 'Conflict',
            'suggestion' => 'Resource was modified by another user. Refresh and retry',
            'category' => 'concurrency',
        ],
        '500' => [
            'message' => 'Internal server error',
            'suggestion' => 'Server error occurred. Check Service Layer status',
            'category' => 'system',
        ],
    ];

    /**
     * Get error info by SAP code.
     *
     * @return array{message: string, suggestion: string, category: string}|null
     */
    public static function get(string|int $code): ?array
    {
        return self::$errors[$code] ?? self::$errors[(string) $code] ?? null;
    }

    /**
     * Get human-readable message for error code.
     */
    public static function getMessage(string|int $code): ?string
    {
        return self::get($code)['message'] ?? null;
    }

    /**
     * Get suggestion for error code.
     */
    public static function getSuggestion(string|int $code): ?string
    {
        return self::get($code)['suggestion'] ?? null;
    }

    /**
     * Get category for error code.
     */
    public static function getCategory(string|int $code): ?string
    {
        return self::get($code)['category'] ?? null;
    }

    /**
     * Check if error code is authentication related.
     */
    public static function isAuthError(string|int $code): bool
    {
        $category = self::getCategory($code);

        return in_array($category, ['authentication', 'session', 'authorization'], true);
    }

    /**
     * Check if error is retryable.
     */
    public static function isRetryable(string|int $code): bool
    {
        $category = self::getCategory($code);

        return in_array($category, ['session', 'concurrency', 'system'], true);
    }

    /**
     * Get all errors for a category.
     *
     * @return array<string|int, array{message: string, suggestion: string, category: string}>
     */
    public static function getByCategory(string $category): array
    {
        return array_filter(
            self::$errors,
            fn (array $error) => $error['category'] === $category
        );
    }

    /**
     * Register a custom error code.
     */
    public static function register(string|int $code, string $message, string $suggestion, string $category = 'custom'): void
    {
        self::$errors[$code] = [
            'message' => $message,
            'suggestion' => $suggestion,
            'category' => $category,
        ];
    }

    /**
     * Parse error message to extract SAP error code.
     */
    public static function extractCode(string $message): ?int
    {
        // Pattern: "Error -XXXXX" or just "-XXXXX"
        if (preg_match('/-(\d+)/', $message, $matches)) {
            return (int) ('-'.$matches[1]);
        }

        return null;
    }
}
