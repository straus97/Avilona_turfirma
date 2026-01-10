@extends('layouts.profile')

@section('content')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Мои документы</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('error') }}
                </div>
            @endif

            @if($bookings->count() > 0)
                @foreach($bookings as $booking)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-bookmark"></i>
                                Заявка #{{ $booking->id }} - {{ $booking->tour_name ?? 'Без названия' }}
                            </h3>
                            <div class="card-tools">
                                <span class="badge {{ $booking->status === 'completed' ? 'badge-success' : 'badge-info' }}">
                                    {{ $booking->status === 'completed' ? 'Завершена' : 'Активна' }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Направление:</strong> {{ $booking->destination ?? 'Не указано' }}</p>
                                    <p><strong>Дата поездки:</strong> 
                                        @if($booking->start_date && $booking->end_date)
                                            {{ \Carbon\Carbon::parse($booking->start_date)->format('d.m.Y') }}
                                            -
                                            {{ \Carbon\Carbon::parse($booking->end_date)->format('d.m.Y') }}
                                        @else
                                            Не указано
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Менеджер:</strong> {{ $booking->manager->name ?? 'Не назначен' }}</p>
                                    <p><strong>Статус:</strong> 
                                        <span class="badge badge-{{ $booking->status_color }}">
                                            {{ $booking->status_label }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <hr>

                            <h5><i class="bi bi-file-earmark-text"></i> Документы по заявке</h5>

                            @if($booking->documents && count($booking->documents) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Название файла</th>
                                                <th>Дата загрузки</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($booking->documents as $index => $document)
                                                <tr>
                                                    <td>
                                                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                                                        {{ $document['name'] ?? 'Документ_' . ($index + 1) }}
                                                    </td>
                                                    <td>{{ isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('d.m.Y H:i') : '-' }}</td>
                                                    <td>
                                                        <a href="{{ asset('storage/' . $document['path']) }}" 
                                                           class="btn btn-sm btn-primary" 
                                                           target="_blank" 
                                                           download>
                                                            <i class="bi bi-download"></i> Скачать
                                                        </a>
                                                        <a href="{{ asset('storage/' . $document['path']) }}" 
                                                           class="btn btn-sm btn-info" 
                                                           target="_blank">
                                                            <i class="bi bi-eye"></i> Просмотр
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> По данной заявке пока нет документов
                                </div>
                            @endif

                            <!-- Форма загрузки документа -->
                            @if(in_array($booking->status, ['confirmed', 'new', 'progress']))
                                <div class="mt-3">
                                    <button class="btn btn-success btn-sm" data-toggle="collapse" 
                                            data-target="#uploadForm{{ $booking->id }}">
                                        <i class="bi bi-upload"></i> Загрузить документ
                                    </button>
                                    
                                    <div class="collapse mt-2" id="uploadForm{{ $booking->id }}">
                                        <div class="card card-body">
                                            <form action="{{ route('profile.upload-document', $booking->id) }}" 
                                                  method="POST" 
                                                  enctype="multipart/form-data">
                                                @csrf
                                                <div class="form-group">
                                                    <label for="document{{ $booking->id }}">Выберите файл</label>
                                                    <input type="file" 
                                                           class="form-control-file" 
                                                           id="document{{ $booking->id }}" 
                                                           name="document" 
                                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                                                           required>
                                                    <small class="form-text text-muted">
                                                        Допустимые форматы: PDF, JPG, PNG, DOC, DOCX. Максимальный размер: 10 МБ
                                                    </small>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-upload"></i> Загрузить
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="text-center text-muted p-5">
                            <i class="bi bi-folder-x" style="font-size: 5rem;"></i>
                            <h4 class="mt-3">У вас пока нет заявок с документами</h4>
                            <p>Документы появятся после создания и подтверждения заявок</p>
                            <a href="{{ route('bookings.create') }}" class="btn btn-primary btn-lg mt-3">
                                <i class="bi bi-plus-circle"></i> Создать заявку
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Информационная карточка -->
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-info-circle"></i> Информация о документах
                    </h3>
                </div>
                <div class="card-body">
                    <p><strong>Какие документы могут быть здесь:</strong></p>
                    <ul>
                        <li>Договор на оказание туристических услуг</li>
                        <li>Ваучеры и путевки</li>
                        <li>Страховые полисы</li>
                        <li>Билеты на транспорт</li>
                        <li>Подтверждения бронирования отелей</li>
                        <li>Памятки туристу</li>
                    </ul>
                    <p class="mb-0"><strong>Важно:</strong> Все документы доступны для скачивания и печати. Рекомендуем взять распечатанные копии документов в поездку.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
