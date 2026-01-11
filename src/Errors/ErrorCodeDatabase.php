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
        -6004 => [
            'message' => 'No pending approval for user',
            'suggestion' => 'Document is not pending approval from this user',
            'category' => 'workflow',
        ],
        -6005 => [
            'message' => 'Approval template not found',
            'suggestion' => 'Configure approval templates in Administration',
            'category' => 'workflow',
        ],

        // Production/Manufacturing Errors
        -7001 => [
            'message' => 'Production order cannot be released',
            'suggestion' => 'Check BOM and resource availability',
            'category' => 'production',
        ],
        -7002 => [
            'message' => 'Bill of materials not found',
            'suggestion' => 'Create BOM for the item before production',
            'category' => 'production',
        ],
        -7003 => [
            'message' => 'Resource not available',
            'suggestion' => 'Check resource calendar and capacity',
            'category' => 'production',
        ],
        -7004 => [
            'message' => 'Production order already closed',
            'suggestion' => 'Cannot modify closed production order',
            'category' => 'production',
        ],
        -7005 => [
            'message' => 'Issue component quantity exceeds planned',
            'suggestion' => 'Adjust planned quantity or enable over-issue',
            'category' => 'production',
        ],
        -7006 => [
            'message' => 'Receipt quantity exceeds planned',
            'suggestion' => 'Adjust planned quantity or enable over-receipt',
            'category' => 'production',
        ],

        // Pricing Errors
        -8001 => [
            'message' => 'Price list not found',
            'suggestion' => 'Verify price list exists and is assigned',
            'category' => 'pricing',
        ],
        -8002 => [
            'message' => 'No price defined for item',
            'suggestion' => 'Add price for item in the relevant price list',
            'category' => 'pricing',
        ],
        -8003 => [
            'message' => 'Special price expired',
            'suggestion' => 'Update special price validity dates',
            'category' => 'pricing',
        ],
        -8004 => [
            'message' => 'Discount exceeds maximum allowed',
            'suggestion' => 'Reduce discount or update user authorization',
            'category' => 'pricing',
        ],
        -8005 => [
            'message' => 'Price below minimum',
            'suggestion' => 'Increase price or get approval for below-minimum pricing',
            'category' => 'pricing',
        ],

        // Tax Errors
        -9001 => [
            'message' => 'Tax code not found',
            'suggestion' => 'Verify tax code exists in tax definitions',
            'category' => 'tax',
        ],
        -9002 => [
            'message' => 'Tax group not assigned',
            'suggestion' => 'Assign tax group to business partner or item',
            'category' => 'tax',
        ],
        -9003 => [
            'message' => 'Withholding tax code required',
            'suggestion' => 'Specify withholding tax code for this transaction',
            'category' => 'tax',
        ],
        -9004 => [
            'message' => 'Tax exemption certificate expired',
            'suggestion' => 'Update tax exemption certificate for business partner',
            'category' => 'tax',
        ],
        -9005 => [
            'message' => 'Invalid tax jurisdiction',
            'suggestion' => 'Check tax jurisdiction configuration',
            'category' => 'tax',
        ],

        // Payment Errors
        -10001 => [
            'message' => 'Payment means not allowed',
            'suggestion' => 'Payment type not permitted for this business partner',
            'category' => 'payment',
        ],
        -10002 => [
            'message' => 'Bank account not found',
            'suggestion' => 'Verify house bank account exists',
            'category' => 'payment',
        ],
        -10003 => [
            'message' => 'Payment already reconciled',
            'suggestion' => 'Cannot modify reconciled payments',
            'category' => 'payment',
        ],
        -10004 => [
            'message' => 'Check number already used',
            'suggestion' => 'Use different check number',
            'category' => 'payment',
        ],
        -10005 => [
            'message' => 'Insufficient payment amount',
            'suggestion' => 'Payment amount is less than document total',
            'category' => 'payment',
        ],
        -10006 => [
            'message' => 'Overpayment not allowed',
            'suggestion' => 'Payment amount exceeds document balance',
            'category' => 'payment',
        ],

        // Inventory Transfer Errors
        -11001 => [
            'message' => 'Source and target warehouse cannot be same',
            'suggestion' => 'Select different source or target warehouse',
            'category' => 'inventory',
        ],
        -11002 => [
            'message' => 'Transfer request not found',
            'suggestion' => 'Create transfer request first',
            'category' => 'inventory',
        ],
        -11003 => [
            'message' => 'Quantity exceeds request quantity',
            'suggestion' => 'Adjust transfer quantity to match request',
            'category' => 'inventory',
        ],

        // Project Errors
        -12001 => [
            'message' => 'Project not found',
            'suggestion' => 'Verify project code exists',
            'category' => 'project',
        ],
        -12002 => [
            'message' => 'Project is not active',
            'suggestion' => 'Activate project or use different one',
            'category' => 'project',
        ],
        -12003 => [
            'message' => 'Budget exceeded',
            'suggestion' => 'Increase project budget or get approval',
            'category' => 'project',
        ],

        // Bank Statement Errors
        -13001 => [
            'message' => 'Bank statement already imported',
            'suggestion' => 'This statement has already been imported',
            'category' => 'banking',
        ],
        -13002 => [
            'message' => 'Statement format not recognized',
            'suggestion' => 'Check bank statement file format',
            'category' => 'banking',
        ],
        -13003 => [
            'message' => 'Reconciliation mismatch',
            'suggestion' => 'Amounts do not match. Check reconciliation',
            'category' => 'banking',
        ],

        // UDF Errors
        -14001 => [
            'message' => 'User-defined field not found',
            'suggestion' => 'Verify UDF exists for this entity',
            'category' => 'configuration',
        ],
        -14002 => [
            'message' => 'Invalid UDF value',
            'suggestion' => 'Value does not match UDF type or valid values',
            'category' => 'configuration',
        ],
        -14003 => [
            'message' => 'Mandatory UDF missing',
            'suggestion' => 'Provide value for required user-defined field',
            'category' => 'configuration',
        ],

        // Service Layer Specific
        -200 => [
            'message' => 'Entity set not found',
            'suggestion' => 'Check endpoint name. Use correct Service Layer entity',
            'category' => 'request',
        ],
        -201 => [
            'message' => 'Invalid key field',
            'suggestion' => 'Check primary key field name and value',
            'category' => 'request',
        ],
        -202 => [
            'message' => 'Action not supported',
            'suggestion' => 'This entity does not support the requested action',
            'category' => 'request',
        ],
        -203 => [
            'message' => 'Navigation property not found',
            'suggestion' => 'Check $expand parameter for valid navigation properties',
            'category' => 'request',
        ],

        // Additional Common Errors
        -50 => [
            'message' => 'Object locked by another user',
            'suggestion' => 'Wait for other user to release the object',
            'category' => 'concurrency',
        ],
        -51 => [
            'message' => 'Transaction timeout',
            'suggestion' => 'Operation took too long. Try again or reduce batch size',
            'category' => 'system',
        ],
        -52 => [
            'message' => 'Deadlock detected',
            'suggestion' => 'Concurrent modification detected. Retry the operation',
            'category' => 'concurrency',
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
        '405' => [
            'message' => 'Method not allowed',
            'suggestion' => 'HTTP method not supported for this endpoint',
            'category' => 'request',
        ],
        '406' => [
            'message' => 'Not acceptable',
            'suggestion' => 'Check Accept header and content type',
            'category' => 'request',
        ],
        '408' => [
            'message' => 'Request timeout',
            'suggestion' => 'Operation took too long. Increase timeout or optimize query',
            'category' => 'system',
        ],
        '412' => [
            'message' => 'Precondition failed',
            'suggestion' => 'ETag mismatch. Resource was modified. Refresh and retry',
            'category' => 'concurrency',
        ],
        '413' => [
            'message' => 'Payload too large',
            'suggestion' => 'Request body exceeds size limit. Split into smaller requests',
            'category' => 'request',
        ],
        '415' => [
            'message' => 'Unsupported media type',
            'suggestion' => 'Check Content-Type header. Use application/json',
            'category' => 'request',
        ],
        '422' => [
            'message' => 'Unprocessable entity',
            'suggestion' => 'Request syntax correct but semantic errors. Check field values',
            'category' => 'validation',
        ],
        '429' => [
            'message' => 'Too many requests',
            'suggestion' => 'Rate limit exceeded. Wait before retrying',
            'category' => 'rate_limit',
        ],
        '502' => [
            'message' => 'Bad gateway',
            'suggestion' => 'Proxy error. Check Service Layer connectivity',
            'category' => 'system',
        ],
        '503' => [
            'message' => 'Service unavailable',
            'suggestion' => 'Service Layer is overloaded or under maintenance',
            'category' => 'system',
        ],
        '504' => [
            'message' => 'Gateway timeout',
            'suggestion' => 'Upstream server timed out. Try again later',
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
