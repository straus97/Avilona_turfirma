@php
    $layout = auth()->check() ? 'cabinet.layouts.app' : 'layouts.main';
@endphp

@extends($layout)

@section('title', auth()->check() ? 'Заявка #' . $booking->id : 'Заявка #' . $booking->id . ' - Авилона')
@section('meta_description', 'Детали заявки на тур')

@auth
    @section('sidebar')
        @if(Auth::user()->isAdmin())
            @include('cabinet.components.sidebar.admin')
        @elseif(Auth::user()->isManager())
            @include('cabinet.components.sidebar.manager')
        @elseif(Auth::user()->isTourist())
            @include('cabinet.components.sidebar.tourist')
        @endif
    @endsection
@endauth

@section('content')
    @auth
        <div class="page-header">
            <h1 class="page-title">Заявка #{{ $booking->id }}</h1>
            <p class="page-subtitle">{{ $booking->destination_country }}@if($booking->destination_city), {{ $booking->destination_city }}@endif</p>
        </div>
        
        <div class="row">
    @endauth

    @guest
        <main>
            <div class="container mt-5">
                <div class="row">
    @endguest
            <div class="col-md-8">
                <!-- Основная информация о заявке -->
                <div class="@auth card-custom @else card shadow @endauth mb-4">
                    @auth
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="bi bi-bookmark-fill"></i> Заявка #{{ $booking->id }}
                            </div>
                            <span class="badge bg-{{ $booking->status_color }} text-white">
                                {{ $booking->status_label }}
                            </span>
                        </div>
                    @else
                        <div class="card-header bg-{{ $booking->status_color }} text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="mb-0">
                                    <i class="bi bi-bookmark-fill"></i> Заявка #{{ $booking->id }}
                                </h3>
                                <span class="badge bg-white text-{{ $booking->status_color }} fs-6">
                                    {{ $booking->status_label }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                    @endauth
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif
                        @if($errors->has('status'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ $errors->first('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <!-- Направление -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="bi bi-geo-alt-fill"></i> Направление
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Город вылета:</strong><br>
                                        {{ $booking->departure_city }}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Страна:</strong><br>
                                        {{ $booking->destination_country }}
                                    </p>
                                </div>
                                @if($booking->destination_city)
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Курорт/Город:</strong><br>
                                            {{ $booking->destination_city }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <hr>

                        <!-- Даты и туристы -->
                        <div class="mb-4">
                            <h5 class="text-primary">
                                <i class="bi bi-calendar3"></i> Даты и туристы
                            </h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Дата вылета:</strong><br>
                                        @if($booking->start_date_end && $booking->start_date_end != $booking->start_date)
                                            {{ $booking->start_date->format('d.m.Y') }} - {{ $booking->start_date_end->format('d.m.Y') }}
                                        @else
                                            {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указано' }}
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Ночей:</strong><br>
                                        @if($booking->nights_max && $booking->nights_max != $booking->nights)
                                            {{ $booking->nights }} - {{ $booking->nights_max }}
                                        @else
                                            {{ $booking->nights }}
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1">
                                        <strong>Туристов:</strong><br>
                                        {{ $booking->adults }} {{ str_plural($booking->adults, 'взрослый', 'взрослых', 'взрослых') }}
                                        @if($booking->children > 0)
                                            , {{ $booking->children }} {{ str_plural($booking->children, 'ребенок', 'ребенка', 'детей') }}
                                            @if($booking->children_ages && count($booking->children_ages) > 0)
                                                <br>
                                                <small class="text-muted">
                                                    Возраст детей: 
                                                    @foreach($booking->children_ages as $age)
                                                        {{ $age }} {{ str_plural($age, 'год', 'года', 'лет') }}@if(!$loop->last), @endif
                                                    @endforeach
                                                </small>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Стоимость -->
                        @if($booking->total_price)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-cash-stack"></i> Стоимость
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Общая стоимость:</strong><br>
                                            <span class="text-success fs-4">{{ $booking->formatted_total_price }}</span>
                                        </p>
                                    </div>
                                    @if($booking->paid_amount > 0)
                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Оплачено:</strong><br>
                                                {{ number_format($booking->paid_amount, 0, ',', ' ') }} ₽
                                            </p>
                                            @if(!$booking->is_fully_paid)
                                                <p class="mb-1">
                                                    <strong>К оплате:</strong><br>
                                                    <span class="text-danger">{{ number_format($booking->remaining_amount, 0, ',', ' ') }} ₽</span>
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <hr>
                        @endif

                        <!-- Пожелания клиента -->
                        @if($booking->notes)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-chat-left-text"></i> Пожелания клиента
                                </h5>
                                <p class="text-muted">{{ $booking->notes }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Заметки менеджера -->
                        @if($booking->manager_notes && (auth()->user()->isManager() || auth()->user()->isAdmin()))
                            <div class="mb-4">
                                <h5 class="text-warning">
                                    <i class="bi bi-clipboard-check"></i> Заметки менеджера
                                </h5>
                                <p class="text-muted">{{ $booking->manager_notes }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Информация о клиенте (для менеджера/админа) -->
                        @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-person"></i> Клиент
                                </h5>
                                <p class="mb-1"><strong>Имя:</strong> {{ $booking->user->name ?? 'Удален' }}</p>
                                <p class="mb-1"><strong>Email:</strong> {{ $booking->user->email ?? 'Нет email' }}</p>
                            </div>
                            <hr>
                        @endif

                        <!-- Информация о менеджере -->
                        @if($booking->manager)
                            <div class="mb-4">
                                <h5 class="text-primary">
                                    <i class="bi bi-person-badge"></i> Ответственный сотрудник
                                </h5>
                                <p class="mb-1"><strong>Имя:</strong> {{ $booking->manager->name }}</p>
                                <p class="mb-1"><strong>Email:</strong> {{ $booking->manager->email }}</p>
                            </div>
                        @endif
                    @auth
                        <div class="mt-3 text-muted small">
                            Создано: {{ $booking->created_at->format('d.m.Y H:i') }}
                            @if($booking->updated_at->ne($booking->created_at))
                                | Обновлено: {{ $booking->updated_at->format('d.m.Y H:i') }}
                            @endif
                        </div>
                    @else
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                Создано: {{ $booking->created_at->format('d.m.Y H:i') }}
                                @if($booking->updated_at->ne($booking->created_at))
                                    | Обновлено: {{ $booking->updated_at->format('d.m.Y H:i') }}
                                @endif
                            </small>
                        </div>
                    @endauth
                </div>

                <!-- Кнопки действий -->
                <div class="@auth card-custom @else card shadow @endauth mb-4">
                    @auth
                        <div class="d-flex flex-wrap gap-2 justify-content-between">
                            <a href="{{ route('cabinet.bookings') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> К списку заявок
                            </a>

                            <div class="d-flex flex-wrap gap-2">
                                <!-- Кнопки для туриста -->
                                @if(auth()->user()->isTourist())
                                    @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CANCELLED))
                                        @can('cancel', $booking)
                                            <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                                @csrf
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-x-circle"></i> Отменить заявку
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                    @if($booking->status === App\Models\Booking::STATUS_NEW && !$booking->manager_id)
                                        <small class="text-muted align-self-center">
                                            <i class="bi bi-info-circle"></i> Вы можете отменить заявку до назначения менеджера
                                        </small>
                                    @elseif($booking->manager_id && in_array($booking->status, [App\Models\Booking::STATUS_PROGRESS, App\Models\Booking::STATUS_CONFIRMED]))
                                        <small class="text-muted align-self-center">
                                            <i class="bi bi-info-circle"></i> Заявка взята в работу менеджером. Для отмены свяжитесь с менеджером.
                                        </small>
                                    @endif
                                @endif

                                <!-- Кнопки для менеджера/админа -->
                                @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                    @php
                                        $isManager = auth()->user()->isManager();
                                        $managerCanEdit = !$isManager || !in_array($booking->status, [App\Models\Booking::STATUS_CANCELLED, App\Models\Booking::STATUS_COMPLETED]);
                                    @endphp
                                    @if($managerCanEdit)
                                        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-warning">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </a>
                                    @endif

                                    @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CONFIRMED))
                                        <form action="{{ route('bookings.confirm', $booking) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Подтвердить
                                            </button>
                                        </form>
                                    @endif

                                    @if($booking->canTransitionTo(\App\Models\Booking::STATUS_COMPLETED))
                                        <form action="{{ route('bookings.complete', $booking) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-all"></i> Завершить
                                            </button>
                                        </form>
                                    @endif

                                    @if($booking->canTransitionTo(\App\Models\Booking::STATUS_CANCELLED))
                                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                            @csrf
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x-circle"></i> Отменить
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                <!-- Удаление для админа -->
                                @if(auth()->user()->isAdmin())
                                    <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить заявку? Это действие необратимо!')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-dark">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 justify-content-between">
                                <a href="{{ route('cabinet.bookings') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> К списку заявок
                                </a>

                                <div class="d-flex flex-wrap gap-2">
                                    <!-- Кнопки для туриста -->
                                    @if(auth()->user()->isTourist())
                                        @can('cancel', $booking)
                                            <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                                @csrf
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-x-circle"></i> Отменить заявку
                                                </button>
                                            </form>
                                        @endcan
                                        @if($booking->status === App\Models\Booking::STATUS_NEW && !$booking->manager_id)
                                            <small class="text-muted align-self-center">
                                                <i class="bi bi-info-circle"></i> Вы можете отменить заявку до назначения менеджера
                                            </small>
                                        @elseif($booking->manager_id)
                                            <small class="text-muted align-self-center">
                                                <i class="bi bi-info-circle"></i> Заявка взята в работу менеджером. Для отмены свяжитесь с менеджером.
                                            </small>
                                        @endif
                                    @endif

                                    <!-- Кнопки для менеджера/админа -->
                                    @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                        @php
                                            $isManager = auth()->user()->isManager();
                                            $managerCanEdit = !$isManager || !in_array($booking->status, [App\Models\Booking::STATUS_CANCELLED, App\Models\Booking::STATUS_COMPLETED]);
                                        @endphp
                                        @if($managerCanEdit)
                                            <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-warning">
                                                <i class="bi bi-pencil"></i> Редактировать
                                            </a>

                                            @if($booking->status === App\Models\Booking::STATUS_PROGRESS)
                                                <form action="{{ route('bookings.confirm', $booking) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-check-circle"></i> Подтвердить
                                                    </button>
                                                </form>
                                            @endif

                                            @if($booking->status === App\Models\Booking::STATUS_CONFIRMED)
                                                <form action="{{ route('bookings.complete', $booking) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-check-all"></i> Завершить
                                                    </button>
                                                </form>
                                            @endif

                                            <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите отменить заявку?')">
                                                @csrf
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="bi bi-x-circle"></i> Отменить
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    <!-- Удаление для админа -->
                                    @if(auth()->user()->isAdmin())
                                        <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить заявку? Это действие необратимо!')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-dark">
                                                <i class="bi bi-trash"></i> Удалить
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endauth
                </div>

                @auth
                {{-- ========== Документы по заявке ========== --}}
                @php
                    $docTypeLabels = [
                        'contract'     => 'Договор',
                        'voucher'      => 'Ваучер',
                        'tickets'      => 'Билеты',
                        'insurance'    => 'Страховка',
                        'instructions' => 'Инструкция',
                        'other'        => 'Другое',
                    ];
                @endphp
                <div class="card-custom mb-4">
                    <div class="card-header-custom">
                        <div class="card-title-custom">
                            <i class="bi bi-file-earmark-text"></i> Документы по заявке
                        </div>
                    </div>

                    @if($booking->bookingDocuments->isEmpty())
                        <p class="text-muted mb-0">
                            <i class="bi bi-inbox"></i> Документы по этой заявке пока не загружены.
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Тип</th>
                                        <th>Размер</th>
                                        <th>Дата загрузки</th>
                                        @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                            <th>Загрузил</th>
                                        @endif
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($booking->bookingDocuments as $document)
                                        @php
                                            $uploadDate = $document->uploaded_at ?? $document->created_at;
                                            $sizeBytes  = (int)($document->file_size ?? 0);
                                            if ($sizeBytes >= 1048576) {
                                                $sizeLabel = number_format($sizeBytes / 1048576, 1) . ' МБ';
                                            } elseif ($sizeBytes > 0) {
                                                $sizeLabel = ceil($sizeBytes / 1024) . ' КБ';
                                            } else {
                                                $sizeLabel = '—';
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $document->title }}
                                                @if($document->file_type)
                                                    <span class="badge bg-secondary ms-1">{{ strtoupper($document->file_type) }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $docTypeLabels[$document->document_type] ?? $document->document_type }}</td>
                                            <td>{{ $sizeLabel }}</td>
                                            <td>{{ $uploadDate ? $uploadDate->format('d.m.Y H:i') : '—' }}</td>
                                            @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                                <td>{{ $document->uploadedBy?->name ?? 'Не указан' }}</td>
                                            @endif
                                            <td class="text-end text-nowrap">
                                                @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                                                    <a href="{{ route('bookings.documents.download', [$booking, $document]) }}"
                                                       class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <form action="{{ route('bookings.documents.destroy', [$booking, $document]) }}"
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @elseif(auth()->user()->isTourist())
                                                    <a href="{{ route('cabinet.documents.bookings.download', [$booking, $document]) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                        <hr class="mt-3">
                        <h6 class="mb-3">Загрузить документ</h6>
                        <form action="{{ route('bookings.documents.store', $booking) }}"
                              method="POST"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="doc_title" class="form-label">Название</label>
                                <input type="text" name="title" id="doc_title"
                                       class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title') }}"
                                       placeholder="Введите название документа">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="doc_type" class="form-label">Тип документа</label>
                                <select name="document_type" id="doc_type"
                                        class="form-select @error('document_type') is-invalid @enderror">
                                    <option value="">— Выберите тип —</option>
                                    <option value="contract"     {{ old('document_type') === 'contract'     ? 'selected' : '' }}>Договор</option>
                                    <option value="voucher"      {{ old('document_type') === 'voucher'      ? 'selected' : '' }}>Ваучер</option>
                                    <option value="tickets"      {{ old('document_type') === 'tickets'      ? 'selected' : '' }}>Билеты</option>
                                    <option value="insurance"    {{ old('document_type') === 'insurance'    ? 'selected' : '' }}>Страховка</option>
                                    <option value="instructions" {{ old('document_type') === 'instructions' ? 'selected' : '' }}>Инструкция</option>
                                    <option value="other"        {{ old('document_type') === 'other'        ? 'selected' : '' }}>Другое</option>
                                </select>
                                @error('document_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="doc_file" class="form-label">Файл</label>
                                <input type="file" name="file" id="doc_file"
                                       class="form-control @error('file') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="form-text">Форматы: PDF, DOC, DOCX, JPG, JPEG, PNG. Максимальный размер: 10 МБ.</div>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Загрузить
                            </button>
                        </form>
                    @endif
                </div>
                @endauth
            </div>

            <!-- Боковая панель -->
            <div class="col-md-4">
                <!-- Назначение менеджера (для админа) -->
                @if(auth()->user()->isAdmin())
                    <div class="@auth card-custom @else card shadow @endauth mb-4">
                        @auth
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="bi bi-person-plus"></i> Назначить ответственного
                                </div>
                            </div>
                        @else
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-plus"></i> Назначить ответственного
                                </h5>
                            </div>
                            <div class="card-body">
                        @endauth
                            <form action="{{ route('bookings.assign-manager', $booking) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="manager_id" class="form-label">Выберите ответственного сотрудника</label>
                                    <select name="manager_id" id="manager_id" class="form-select" required>
                                        <option value="">-- Выберите --</option>
                                        @foreach($assignableEmployees as $employee)
                                            <option value="{{ $employee->id }}" {{ $booking->manager_id == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">
                                    {{ $booking->manager ? 'Сменить ответственного' : 'Назначить' }}
                                </button>
                            </form>
                        @auth
                        @else
                            </div>
                        @endauth
                    </div>
                @endif

                <!-- История статусов -->
                <div class="@auth card-custom @else card shadow @endauth mb-4">
                    @auth
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="bi bi-clock-history"></i> Статус заявки
                            </div>
                        </div>
                    @else
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history"></i> Статус заявки
                            </h5>
                        </div>
                        <div class="card-body">
                    @endauth
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <span class="badge bg-{{ $booking->status_color }} float-end">Текущий</span>
                                <strong>{{ $booking->status_label }}</strong>
                                <br>
                                <small class="text-muted">{{ $booking->updated_at->diffForHumans() }}</small>
                            </div>
                        </div>
                    @auth
                    @else
                        </div>
                    @endauth
                </div>

                <!-- Информация -->
                <div class="@auth card-custom @else card shadow @endauth">
                    @auth
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="bi bi-info-circle"></i> Информация
                            </div>
                        </div>
                    @else
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle"></i> Информация
                            </h5>
                        </div>
                        <div class="card-body">
                    @endauth
                        <p class="small text-muted mb-2">
                            После подтверждения заявки менеджер свяжется с вами для уточнения деталей и оформления документов.
                        </p>
                        <p class="small text-muted mb-0">
                            При возникновении вопросов, обратитесь к своему менеджеру или в службу поддержки.
                        </p>
                    @auth
                    @else
                        </div>
                    @endauth
                </div>
            </div>
    @auth
        </div>
    @endauth

    @guest
                </div>
            </div>
        </main>
    @endguest
@endsection

@push('styles')
<style>
.gap-2 {
    gap: 0.5rem;
}
</style>
@endpush
