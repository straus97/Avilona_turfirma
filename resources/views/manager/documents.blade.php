@extends('cabinet.layouts.app')

@section('title', 'Мои документы')

@section('sidebar')
    @include('cabinet.components.sidebar.manager')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мои документы</h1>
    <p class="page-subtitle">Загрузка и управление документами менеджера</p>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card-custom mb-4">
    <div class="card-header-custom">
        <div class="card-title-custom">Загрузка документа</div>
    </div>
    <form method="POST" action="{{ route('cabinet.manager.documents.upload') }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Название</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Тип документа</label>
                <select name="document_type" class="form-select">
                    <option value="other">Другое</option>
                    <option value="passport">Паспорт РФ</option>
                    <option value="foreign_passport">Загранпаспорт</option>
                    <option value="visa">Виза</option>
                    <option value="birth_certificate">Свидетельство о рождении</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Файл</label>
            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
            @error('file')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">PDF, DOC, JPG, PNG, ZIP до 10MB</small>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload"></i> Загрузить
        </button>
    </form>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Список документов</div>
    </div>

    @if($documents->count() > 0)
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Тип</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $typeLabels = [
                            'passport' => 'Паспорт РФ',
                            'foreign_passport' => 'Загранпаспорт',
                            'visa' => 'Виза',
                            'birth_certificate' => 'Свидетельство о рождении',
                            'other' => 'Другое',
                        ];
                    @endphp
                    @foreach($documents as $document)
                        <tr>
                            <td>{{ $document->name }}</td>
                            <td>{{ $typeLabels[$document->document_type] ?? $document->document_type }}</td>
                            <td>{{ $document->created_at->format('d.m.Y') }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ Storage::url($document->file_path) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ Storage::url($document->file_path) }}" class="btn btn-sm btn-outline-secondary" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form method="POST" action="{{ route('cabinet.manager.documents.delete', $document) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить документ?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        @include('cabinet.components.empty-state', [
            'icon' => 'bi-folder',
            'title' => 'Документов пока нет',
            'description' => 'Загрузите первый документ выше',
        ])
    @endif
</div>
@endsection
