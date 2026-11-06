<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            // Public identifier used in URLs instead of the sequential id.
            $table->uuid('uuid')->unique();
            $table->string('original_name');
            $table->string('disk', 64);
            $table->string('path', 512)->unique();
            $table->string('extension', 16);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->dateTime('expires_at');
            // Set together with deletion_reason when the file is deleted; the row is kept for history.
            $table->dateTime('deleted_at')->nullable();
            $table->string('deletion_reason', 16)->nullable();
            $table->timestamps();

            // Serves both "active files" (list) and "expired, not yet deleted" (purge) queries.
            $table->index(['deleted_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
