@props(['messages'])

@if ($messages)
    {{-- E4-E1/P-04: без role=alert ошибки валидации (в т.ч. неверный логин/
         пароль на /login, где Laravel возвращает ошибку в поле email) никак
         не анонсировались вспомогательными технологиями. --}}
    <ul role="alert" {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
