@extends('layouts.app')

@section('title', 'Files')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Uploaded files</h1>
        <a href="{{ route('upload.create') }}" class="btn btn-primary btn-sm">Upload a file</a>
    </div>

    <div id="files-alert" class="alert d-none" role="alert"></div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="files-table">
                <thead class="table-light">
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Type</th>
                    <th scope="col" class="text-end">Size</th>
                    <th scope="col">Uploaded</th>
                    <th scope="col">Deleted in</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($files as $file)
                    <tr data-file-row data-expires-at="{{ $file->expires_at->toIso8601String() }}">
                        <td class="text-break">{{ $file->original_name }}</td>
                        <td><span class="badge text-bg-secondary">{{ strtoupper($file->extension) }}</span></td>
                        <td class="text-end text-nowrap">{{ \App\Support\Bytes::format($file->size) }}</td>
                        <td class="text-nowrap" data-local-time="{{ $file->created_at->toIso8601String() }}">
                            {{ $file->created_at->format('Y-m-d H:i') }} UTC
                        </td>
                        <td class="text-nowrap" data-remaining title="{{ $file->expires_at->format('Y-m-d H:i:s') }} UTC">
                            {{ \App\Support\Duration::remaining($now, $file->expires_at) }}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('files.download', $file) }}" class="btn btn-outline-primary btn-sm" data-action>
                                Download
                            </a>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-action
                                    data-delete-url="{{ route('files.destroy', $file) }}"
                                    data-file-name="{{ $file->original_name }}">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr data-empty-row>
                        <td colspan="6" class="text-center text-body-secondary py-4">
                            No files yet. <a href="{{ route('upload.create') }}">Upload one</a>.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $files->links() }}
    </div>

    <div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="delete-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="delete-modal-title">Delete file?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    The file <strong class="text-break" data-modal-file-name></strong> will be deleted permanently.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="delete-confirm">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <span>Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/files.js') }}"></script>
@endpush
