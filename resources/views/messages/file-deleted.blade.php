{{-- Plain-text e-mail body. Escaping is not needed in plain text, so raw output is used. --}}
Hello,

The file "{!! $file->original_name !!}" has been deleted ({{ $file->deletion_reason?->label() }}).

File:        {!! $file->original_name !!}
Type:        {{ strtoupper($file->extension) }}
Size:        {{ $size }}
Uploaded at: {{ $file->created_at->utc()->format('Y-m-d H:i:s') }} UTC
Deleted at:  {{ $file->deleted_at?->utc()->format('Y-m-d H:i:s') }} UTC

This is an automatic message from {!! $applicationName !!}.
