<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StoredFile;
use App\Support\Bytes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property StoredFile $resource
 */
final class StoredFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file = $this->resource;

        return [
            'id' => $file->uuid,
            'name' => $file->original_name,
            'extension' => $file->extension,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'size_human' => Bytes::format($file->size),
            'uploaded_at' => $file->created_at->toIso8601String(),
            'expires_at' => $file->expires_at->toIso8601String(),
        ];
    }
}
