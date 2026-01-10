@extends('layouts.profile')

@section('title', 'Мои документы - Авилона')

@section('content')
<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-file-alt text-primary"></i> Мои документы
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.index') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Личный кабинет</a></li>
                    <li class="breadcrumb-item active">Документы</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">

        <!-- Форма загрузки -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-upload"></i> Загрузить документ
                        </h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('profile.upload-document') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="custom-file">
                                        <input type="file" 
                                               class="custom-file-input @error('document') is-invalid @enderror" 
                                               id="documentInput" 
                                               name="document" 
                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                               required>
                                        <label class="custom-file-label" for="documentInput">Выберите файл...</label>
                                        @error('document')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Допустимые форматы: PDF, DOC, DOCX, JPG, PNG. Максимальный размер: 10 МБ
                                    </small>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-upload"></i> Загрузить
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(session('status') === 'document-uploaded')
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-check-circle"></i> Документ успешно загружен!
            </div>
        @endif

        <!-- Список документов -->
        @if(count($documents) > 0)
            <div class="row">
                @foreach($documents as $document)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card card-document">
                            <div class="card-body">
                                <div class="document-icon text-center mb-3">
                                    @php
                                        $extension = strtolower(pathinfo($document['name'], PATHINFO_EXTENSION));
                                        $iconClass = 'fa-file';
                                        $iconColor = 'text-secondary';
                                        
                                        if (in_array($extension, ['pdf'])) {
                                            $iconClass = 'fa-file-pdf';
                                            $iconColor = 'text-danger';
                                        } elseif (in_array($extension, ['doc', 'docx'])) {
                                            $iconClass = 'fa-file-word';
                                            $iconColor = 'text-primary';
                                        } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                                            $iconClass = 'fa-file-image';
                                            $iconColor = 'text-success';
                                        }
                                    @endphp
                                    <i class="fas {{ $iconClass }} {{ $iconColor }} fa-5x"></i>
                                </div>
                                <h5 class="card-title text-center">
                                    {{ Str::limit($document['name'], 30) }}
                                </h5>
                                <div class="document-info text-center text-muted">
                                    <small>
                                        <i class="fas fa-calendar"></i>
                                        {{ \Carbon\Carbon::createFromTimestamp($document['last_modified'])->format('d.m.Y H:i') }}
                                    </small>
                                    <br>
                                    <small>
                                        <i class="fas fa-hdd"></i>
                                        {{ number_format($document['size'] / 1024, 2) }} КБ
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-6">
                                        <a href="{{ $document['url'] }}" 
                                           target="_blank" 
                                           class="btn btn-info btn-sm btn-block">
                                            <i class="fas fa-eye"></i> Просмотр
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="{{ $document['url'] }}" 
                                           download 
                                           class="btn btn-success btn-sm btn-block">
                                            <i class="fas fa-download"></i> Скачать
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-folder-open fa-5x text-muted mb-4"></i>
                            <h4 class="text-muted">У вас пока нет документов</h4>
                            <p class="text-muted">Загрузите свои документы (паспорт, визы и т.д.) для быстрого оформления</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Информация -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info border-left-info">
                    <h5><i class="fas fa-info-circle"></i> Информация</h5>
                    <ul class="mb-0">
                        <li>Загружайте сканы паспортов, виз и других документов для ускорения процесса оформления</li>
                        <li>Все документы хранятся в защищенном виде и доступны только вам и вашему менеджеру</li>
                        <li>Рекомендуем загружать документы в формате PDF для лучшего качества</li>
                        <li>При возникновении проблем с загрузкой, обратитесь к менеджеру через чат</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.card-document {
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,.1);
    transition: all .3s;
    border: none;
}

.card-document:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
}

.document-icon {
    padding: 20px;
    background: #f8f9fc;
    border-radius: 10px;
}

.border-left-info {
    border-left: 4px solid #36b9cc;
}

.custom-file-label::after {
    content: "Обзор";
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Показ имени выбранного файла
    $('#documentInput').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Выберите файл...');
    });
});
</script>
@endpush
