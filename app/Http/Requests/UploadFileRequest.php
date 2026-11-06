<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\AllowedFileType;
use App\Services\Files\FileSettings;
use App\Support\Bytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class UploadFileRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        $settings = $this->settings();

        return [
            'file' => ['bail', 'required', 'file', 'max:' . $settings->maxSizeKb, new AllowedFileType($settings)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to upload.',
            'file.file' => 'The file failed to upload.',
            'file.max' => sprintf('The file must not be larger than %s.', Bytes::format($this->settings()->maxSizeBytes())),
        ];
    }

    /**
     * The validated uploaded file.
     */
    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The request has no validated file.');
        }

        return $file;
    }

    private function settings(): FileSettings
    {
        return $this->container->make(FileSettings::class);
    }
}
