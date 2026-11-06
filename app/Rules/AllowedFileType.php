<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Files\FileSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Accepts a file only when both its extension and its real (content-based) MIME type are allowed
 * and belong to each other: a renamed executable or a DOCX renamed to .pdf is rejected.
 */
final readonly class AllowedFileType implements ValidationRule
{
    public function __construct(private FileSettings $settings)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, $this->settings->allowedExtensions, true)) {
            $fail(sprintf('Only %s files are allowed.', $this->allowedList()));

            return;
        }

        if (! $this->settings->isAllowed($extension, (string) $value->getMimeType())) {
            $fail(sprintf('The file content does not match the .%s extension.', $extension));
        }
    }

    private function allowedList(): string
    {
        return implode(', ', array_map(strtoupper(...), $this->settings->allowedExtensions));
    }
}
