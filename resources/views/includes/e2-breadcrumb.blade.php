{{--
    E2-A3-I1 — общий хлебный след для публичных discovery-страниц.
    API намеренно маленький: $items — упорядоченный массив крошек, каждая
    ['label' => string, 'url' => string|null]. Последняя крошка = текущая
    страница (url игнорируется, помечается aria-current="page").
--}}
@php($items = $items ?? [])
@if(!empty($items))
    <nav aria-label="Хлебные крошки" class="e2-breadcrumb">
        <ol>
            @foreach($items as $crumb)
                <li>
                    @if(! $loop->last && ! empty($crumb['url']))
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @elseif($loop->last)
                        <span aria-current="page">{{ $crumb['label'] }}</span>
                    @else
                        <span>{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
