@extends('cabinet.layouts.app')

@section('title', 'Мои документы')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
@php
    $docTypes = [
        'all' => 'Все',
        'passport' => 'Паспорт',
        'foreign_passport' => 'Загранпаспорт',
        'visa' => 'Виза',
        'birth_certificate' => 'Свидетельство',
        'other' => 'Другое',
    ];
    $activeType = request('type', 'all') ?: 'all';
@endphp

<div class="page-header">
    <h1 class="page-title">Мои документы</h1>
    <p class="page-subtitle">Храните паспорта, визы и другие документы в одном месте</p>
    <div class="page-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-upload" aria-hidden="true"></i> Загрузить документ
        </button>
    </div>
</div>

@if($hasAnyDocuments)
    <div class="tc-chip-filter" role="group" aria-label="Фильтр по типу документа">
        @foreach($docTypes as $value => $label)
            <a href="{{ route('cabinet.documents.personal', $value === 'all' ? [] : ['type' => $value]) }}"
               @class(['btn', 'btn-sm', 'btn-primary' => $activeType === $value, 'btn-outline-secondary' => $activeType !== $value])
               @if($activeType === $value) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </div>
@endif

@if($documents->count() > 0)
    <div class="tc-doc-grid">
        @foreach($documents as $document)
            <div class="tc-doc">
                <div class="tc-doc__head">
                    <div class="tc-doc__icon" aria-hidden="true">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <h2 class="tc-doc__name" style="font-size: 0.95rem;">{{ $document->name }}</h2>
                        <div class="tc-doc__meta">
                            <span>{{ strtoupper($document->file_type ?? 'файл') }}</span>
                            @if($document->file_size)
                                <span>{{ number_format($document->file_size / 1024, 0, '.', ' ') }} КБ</span>
                            @endif
                            <span>{{ $document->created_at->format('d.m.Y') }}</span>
                        </div>
                    </div>
                </div>
                <div class="tc-doc__actions">
                    <a href="{{ route('cabinet.documents.personal.download', $document) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye" aria-hidden="true"></i> Просмотр
                    </a>
                    <a href="{{ route('cabinet.documents.personal.download', $document) }}" download class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download" aria-hidden="true"></i> Скачать
                    </a>
                    <form action="{{ route('cabinet.documents.personal.delete', $document->id) }}" method="POST"
                          onsubmit="return confirm('Удалить документ? Это действие нельзя отменить.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Удалить документ «{{ $document->name }}»">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@elseif($hasAnyDocuments)
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-funnel',
        'title' => 'Документов этого типа нет',
        'description' => 'Выберите другой тип или загрузите новый документ.',
        'actionUrl' => route('cabinet.documents.personal'),
        'actionText' => 'Показать все',
    ])
@else
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-file-earmark-plus',
        'title' => 'Документов пока нет',
        'description' => 'Загрузите паспорта, визы и другие документы — они пригодятся при оформлении тура.',
    ])
@endif

<!-- Модальное окно загрузки -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="uploadModalTitle">Загрузить документ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <form action="{{ route('cabinet.documents.personal.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="doc-type" class="form-label">Тип документа</label>
                        <select name="document_type" id="doc-type" class="form-select @error('document_type') is-invalid @enderror">
                            <option value="">— Выберите тип —</option>
                            <option value="passport">Паспорт</option>
                            <option value="foreign_passport">Загранпаспорт</option>
                            <option value="visa">Виза</option>
                            <option value="birth_certificate">Свидетельство о рождении</option>
                            <option value="other">Другое</option>
                        </select>
                        @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="doc-name" class="form-label">Название документа <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="doc-name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="Например: Загранпаспорт Иванов И.И." required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="doc-file" class="form-label">Файл <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="doc-file" class="form-control @error('file') is-invalid @enderror"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">Максимум 10 МБ. Форматы: PDF, DOC, DOCX, JPG, PNG.</div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload" aria-hidden="true"></i> Загрузить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->any() || old('name'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('uploadModal');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    </script>
    @endpush
@endif
@endsection
