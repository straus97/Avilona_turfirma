@extends('cabinet.layouts.app')

@section('title', 'Документы по заявкам')

@section('sidebar')
    @include('cabinet.components.sidebar.tourist')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Документы по заявкам</h1>
    <p class="page-subtitle">Билеты, ваучеры и другие документы, которые загрузил менеджер</p>
</div>

@forelse($bookingsWithDocuments as $booking)
    <div class="card-custom">
        <div class="card-header-custom">
            <div style="min-width: 0;">
                <h2 class="card-title-custom mb-1">
                    Заявка #{{ $booking->id }} • {{ $booking->destination_country }}@if($booking->destination_city), {{ $booking->destination_city }}@endif
                </h2>
                <div class="tc-thread__sub">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    {{ $booking->start_date ? $booking->start_date->format('d.m.Y') : 'дата вылета не указана' }}
                    @if($booking->manager)
                        <span class="mx-1">•</span> менеджер: {{ $booking->manager->name }}
                    @endif
                </div>
            </div>
            @include('cabinet.components.status-badge', ['status' => $booking->status])
        </div>

        @if($booking->bookingDocuments->isEmpty())
            <div class="tc-notice">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <span>По этой заявке менеджер ещё не загрузил документы.</span>
            </div>
        @else
            <div class="tc-doc-grid">
                @foreach($booking->bookingDocuments as $document)
                    <div class="tc-doc">
                        <div class="tc-doc__head">
                            <div class="tc-doc__icon" aria-hidden="true">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 class="tc-doc__name" style="font-size: 0.95rem;">{{ $document->title }}</h3>
                                <div class="tc-doc__meta">
                                    <span>{{ $document->file_type ? strtoupper($document->file_type) : 'файл' }}</span>
                                    @if($document->file_size)
                                        <span>{{ number_format($document->file_size / 1024, 0, '.', ' ') }} КБ</span>
                                    @endif
                                    <span>{{ ($document->uploaded_at ?? $document->created_at)?->format('d.m.Y') ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="tc-doc__actions">
                            <a href="{{ route('cabinet.documents.bookings.download', [$booking, $document]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye" aria-hidden="true"></i> Просмотр
                            </a>
                            <a href="{{ route('cabinet.documents.bookings.download', [$booking, $document]) }}" download class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download" aria-hidden="true"></i> Скачать
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@empty
    @include('cabinet.components.empty-state', [
        'icon' => 'bi-folder2-open',
        'title' => 'Документов по заявкам пока нет',
        'description' => 'Когда менеджер загрузит билеты или ваучеры по вашим заявкам, они появятся здесь.',
        'actionUrl' => route('cabinet.bookings'),
        'actionText' => 'Мои заявки',
    ])
@endforelse
@endsection
