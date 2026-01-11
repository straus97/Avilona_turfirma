@extends('cabinet.layouts.app')

@section('title', 'Мои документы')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Мои документы</h1>
    <p class="page-subtitle">Храните паспорта, визы и другие документы в одном месте</p>
    <div class="page-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-upload"></i> Загрузить документ
        </button>
    </div>
</div>

<!-- Типы документов -->
<div class="row mb-4">
    <div class="col-md-2">
        <a href="?type=all" class="btn btn-outline-primary w-100 {{ !request('type') || request('type') == 'all' ? 'active' : '' }}">
            Все
        </a>
    </div>
    <div class="col-md-2">
        <a href="?type=passport" class="btn btn-outline-primary w-100 {{ request('type') == 'passport' ? 'active' : '' }}">
            Паспорт
        </a>
    </div>
    <div class="col-md-2">
        <a href="?type=foreign_passport" class="btn btn-outline-primary w-100 {{ request('type') == 'foreign_passport' ? 'active' : '' }}">
            Загранпаспорт
        </a>
    </div>
    <div class="col-md-2">
        <a href="?type=visa" class="btn btn-outline-primary w-100 {{ request('type') == 'visa' ? 'active' : '' }}">
            Виза
        </a>
    </div>
    <div class="col-md-2">
        <a href="?type=birth_certificate" class="btn btn-outline-primary w-100 {{ request('type') == 'birth_certificate' ? 'active' : '' }}">
            Свидетельство
        </a>
    </div>
    <div class="col-md-2">
        <a href="?type=other" class="btn btn-outline-primary w-100 {{ request('type') == 'other' ? 'active' : '' }}">
            Другое
        </a>
    </div>
</div>

<!-- Список документов -->
@if($documents->count() > 0)
    <div class="row">
        @foreach($documents as $document)
            <div class="col-md-4 mb-4">
                <div class="card-custom">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width: 60px; height: 60px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark-pdf" style="font-size: 1.75rem; color: var(--primary-color);"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h6 class="mb-1" style="font-weight: 600;">{{ $document->name }}</h6>
                            <div style="font-size: 0.75rem; color: #9ca3af;">
                                {{ strtoupper($document->file_type ?? 'Документ') }}
                            </div>
                            <div style="font-size: 0.75rem; color: #9ca3af;">
                                @if($document->file_size)
                                    {{ number_format($document->file_size / 1024, 0) }} КБ
                                @endif
                            </div>
                            <div style="font-size: 0.75rem; color: #9ca3af;">
                                {{ $document->created_at->format('d.m.Y H:i') }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="bi bi-eye"></i> Просмотр
                        </a>
                        <a href="{{ Storage::url($document->file_path) }}" download class="btn btn-sm btn-outline-secondary flex-fill">
                            <i class="bi bi-download"></i> Скачать
                        </a>
                        <form action="{{ route('cabinet.documents.personal.delete', $document->id) }}" method="POST" onsubmit="return confirm('Удалить документ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-file-earmark-plus',
        'title' => 'Документов пока нет',
        'description' => 'Загрузите паспорта, визы и другие документы для быстрого доступа'
    ])
@endif

<!-- Модальное окно загрузки -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Загрузить документ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('cabinet.documents.personal.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название документа <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Например: Паспорт Иванов И.И." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Файл <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar" required>
                        <small class="text-muted">Максимум 10 МБ. Форматы: PDF, DOC, DOCX, JPG, PNG, ZIP, RAR</small>
                    </div>
                    
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    
                    @if(session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i> Загрузить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
