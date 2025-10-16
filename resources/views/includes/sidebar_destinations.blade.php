<div class="col-md-2">
    <ul class="navbar-nav h5">
        @foreach($destination_title_menu as $item_destination_title_menu)
            <li class="nav-item">
                <a class="nav-link"
                   href="{{ route('destinations.show_destinations_image', $item_destination_title_menu->slug) }}">
                    {{$item_destination_title_menu->title}}
                </a>
            </li>
        @endforeach
    </ul>
    <hr>
    <div class="mt-2">
        <a href="{{route('contact.index')}}" class="btn btn-primary btn-block">Задать вопрос</a>
    </div>
    <div class="mt-2">
        <a href="{{route('review.index')}}" class="btn btn-primary btn-block">Оставить отзыв</a>
    </div>
</div>
