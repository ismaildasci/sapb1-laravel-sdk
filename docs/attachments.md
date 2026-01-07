# Attachments

Upload and download files through SAP B1's Attachments API.

## Upload Files

```php
use SapB1\Facades\SapB1;

$attachment = SapB1::attachments()->upload(
    filePath: '/path/to/document.pdf',
    fileName: 'invoice.pdf'
);

$absoluteEntry = $attachment['AbsoluteEntry'];
```

## Download Files

```php
$content = SapB1::attachments()->download($absoluteEntry);

// Save to disk
file_put_contents('downloaded.pdf', $content);
```

## List Attachments

```php
$attachments = SapB1::attachments()->list($absoluteEntry);

foreach ($attachments as $file) {
    echo $file['FileName'];
}
```

## Get Metadata

```php
$metadata = SapB1::attachments()->metadata($absoluteEntry);
```

## Delete Attachments

```php
SapB1::attachments()->delete($absoluteEntry);
```

## Attach to Documents

```php
// Create order with attachment
$order = SapB1::create('Orders', [
    'CardCode' => 'C001',
    'AttachmentEntry' => $absoluteEntry,
    'DocumentLines' => [...]
]);
```

## Configuration

```php
// config/sap-b1.php
'attachments' => [
    'max_size' => 10 * 1024 * 1024, // 10MB
    'allowed_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'jpg', 'jpeg', 'png', 'gif',
        'zip', 'rar', '7z',
    ],
    'temp_path' => storage_path('app/sap-b1-attachments'),
],
```

## Error Handling

```php
use SapB1\Exceptions\AttachmentException;

try {
    $attachment = SapB1::attachments()->upload($path);
} catch (AttachmentException $e) {
    // File too large, invalid extension, etc.
}
```

Next: [SQL Queries](sql-queries.md)
