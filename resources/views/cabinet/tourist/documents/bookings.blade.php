@extends('cabinet.layouts.app')

@section('title', 'Документы по заявкам')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Документы по заявкам</h1>
    <p class="page-subtitle">Билеты, ваучеры и другие документы от менеджера</p>
</div>

@if($bookingsWithDocuments->count() > 0)
    @foreach($bookingsWithDocuments as $booking)
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <div>
                    <h5 class="card-title-custom mb-1">
                        Заявка #{{ $booking->id }} • {{ $booking->destination_country }}
                        @if($booking->destination_city), {{ $booking->destination_city }}@endif
                    </h5>
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        <i class="bi bi-calendar3"></i> {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'Не указана' }}
                        @if($booking->manager)
                            • Менеджер: {{ $booking->manager->name }}
                        @endif
                    </div>
                </div>
                @include('cabinet.components.status-badge', ['status' => $booking->status])
            </div>

            <div class="row">
                @forelse($booking->documents as $document)
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-start gap-3 p-3 bg-light rounded">
                            <div style="width: 50px; height: 50px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                @switch($document->document_type)
                                    @case('contract')
                                        <i class="bi bi-file-earmark-text" style="font-size: 1.5rem; color: #3b82f6;"></i>
                                        @break
                                    @case('voucher')
                                        <i class="bi bi-receipt" style="font-size: 1.5rem; color: #10b981;"></i>
                                        @break
                                    @case('tickets')
                                        <i class="bi bi-ticket-perforated" style="font-size: 1.5rem; color: #f59e0b;"></i>
                                        @break
                                    @case('insurance')
                                        <i class="bi bi-shield-check" style="font-size: 1.5rem; color: #ef4444;"></i>
                                        @break
                                    @default
                                        <i class="bi bi-file-earmark" style="font-size: 1.5rem; color: #6b7280;"></i>
                                @endswitch
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h6 class="mb-1" style="font-weight: 600;">{{ $document->title }}</h6>
                                <div style="font-size: 0.75rem; color: #6b7280;">
                                    @switch($document->document_type)
                                        @case('contract') Договор @break
                                        @case('voucher') Ваучер @break
                                        @case('tickets') Билеты @break
                                        @case('insurance') Страховка @break
                                        @case('instructions') Инструкции @break
                                        @default Другое
                                    @endswitch
                                </div>
                                <div style="font-size: 0.75rem; color: #9ca3af;">
                                    {{ number_format($document->file_size / 1024, 0) }} КБ • {{ $document->uploaded_at->format('d.m.Y H:i') }}
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ Storage::url($document->file_path) }}" download class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Документы по этой заявке еще не загружены менеджером
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
@else
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-inbox',
        'title' => 'Документов пока нет',
        'description' => 'Когда менеджер загрузит документы по вашим заявкам, они появятся здесь',
        'actionUrl' => route('cabinet.bookings'),
        'actionText' => 'Мои заявки'
    ])
@endif
@endsection
