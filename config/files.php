<?php

declare(strict_types=1);

/*
 * Uploaded files settings. Values come from .env and are validated by App\Services\Files\FileSettings.
 */
return [
    // Filesystem disk (config/filesystems.php) and directory inside it where uploads are stored.
    'disk' => env('FILES_DISK', 'local'),
    'directory' => env('FILES_DIRECTORY', 'uploads'),

    // Maximum size of one uploaded file, in kilobytes.
    'max_size_kb' => env('FILES_MAX_SIZE_KB', 10240),

    // Comma-separated list of allowed extensions. Each one must be described in 'mime_types' below.
    'allowed_extensions' => env('FILES_ALLOWED_EXTENSIONS', 'pdf,docx'),

    // How long a file lives after upload before it is deleted automatically, in minutes (1440 = 24 hours).
    'ttl_minutes' => env('FILES_TTL_MINUTES', 1440),

    // Files per page on the management page.
    'per_page' => env('FILES_PER_PAGE', 20),

    // How many expired files the purge command processes per database query.
    'purge_batch_size' => env('FILES_PURGE_BATCH_SIZE', 100),

    // MIME types (detected from file contents) accepted for each supported extension.
    // DOCX is a ZIP container, so some libmagic versions report it as a generic ZIP.
    'mime_types' => [
        'pdf' => [
            'application/pdf',
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
    ],
];
