<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Http\Resources\StoredFileResource;
use App\Services\Files\FileSettings;
use App\Services\Files\FileUploadService;
use App\Support\Bytes;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UploadController extends Controller
{
    public function create(FileSettings $settings): View
    {
        $accept = array_merge(
            array_map(static fn (string $extension): string => '.' . $extension, $settings->allowedExtensions),
            $settings->allowedMimeTypes(),
        );

        return view('upload', [
            'maxSizeBytes' => $settings->maxSizeBytes(),
            'maxSizeHuman' => Bytes::format($settings->maxSizeBytes()),
            'extensions' => $settings->allowedExtensions,
            'accept' => implode(',', $accept),
            'ttlHuman' => CarbonInterval::minutes($settings->ttlMinutes)->cascade()->forHumans(),
        ]);
    }

    public function store(UploadFileRequest $request, FileUploadService $uploader): JsonResponse
    {
        $file = $uploader->store($request->uploadedFile());

        return StoredFileResource::make($file)
            ->additional(['message' => 'File uploaded.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
