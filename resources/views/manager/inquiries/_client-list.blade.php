{{-- Список кандидатов/результатов поиска: выбор — только явной кнопкой, для каждого своя форма. --}}
<ul class="list-group mb-2">
    @foreach($entries as $entry)
        @php($candidate = $entry['user'])
        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>{{ $candidate->name }}</strong>
                <div class="small text-muted">
                    {{ $candidate->hasTechnicalEmail() ? 'email не указан' : $candidate->email }}
                    @if($candidate->phone) · {{ $candidate->phone }}@endif
                    · в системе с {{ $candidate->created_at?->format('d.m.Y') ?? '—' }}
                    · заявок: {{ $candidate->bookings_count ?? 0 }}
                    · email {{ $candidate->email_verified_at ? 'подтверждён' : 'не подтверждён' }}
                </div>
                @if(! empty($entry['reasons']))
                    <div class="small"><span class="badge text-bg-info">{{ implode(', ', $entry['reasons']) }}</span></div>
                @endif
            </div>
            @if($client && $client->id === $candidate->id)
                <span class="badge text-bg-success">Выбран</span>
            @else
                <form method="POST" action="{{ route('cabinet.manager.inquiries.client.select', $inquiry) }}" data-submit-once>
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $candidate->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Выбрать клиента {{ $candidate->name }}">Выбрать</button>
                </form>
            @endif
        </li>
    @endforeach
</ul>
