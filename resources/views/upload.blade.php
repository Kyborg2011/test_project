@extends('layouts.app')

@section('title', 'Upload')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h4 mb-3">Upload a file</h1>

                    <form id="upload-form"
                          action="{{ route('files.store') }}"
                          method="post"
                          enctype="multipart/form-data"
                          novalidate
                          data-max-size="{{ $maxSizeBytes }}"
                          data-max-size-human="{{ $maxSizeHuman }}"
                          data-extensions="{{ implode(',', $extensions) }}">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label visually-hidden">File</label>
                            <input class="form-control" type="file" name="file" id="file" accept="{{ $accept }}" required>
                            <div class="form-text">
                                Allowed types: {{ strtoupper(implode(', ', $extensions)) }}.
                                Maximum size: {{ $maxSizeHuman }}.
                                Files are deleted automatically after {{ $ttlHuman }}.
                            </div>
                        </div>

                        <div class="progress mb-3 d-none" id="upload-progress" role="progressbar"
                             aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0">0%</div>
                        </div>

                        <div id="upload-alert" class="alert d-none" role="alert"></div>

                        <button type="submit" class="btn btn-primary" id="upload-button">
                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                            <span>Upload</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm d-none" id="uploaded-card">
                <div class="card-header">Uploaded during this visit</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Size</th>
                            <th scope="col">Will be deleted at</th>
                        </tr>
                        </thead>
                        <tbody id="uploaded-rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/upload.js') }}"></script>
@endpush
