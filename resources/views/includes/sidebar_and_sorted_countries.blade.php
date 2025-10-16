<div class="col-md-2">
    <div class="mb-3">
        <h5>Фильтр</h5>
        <form method="GET" action="{{route('countries.index')}}" class="mb-3">
            <div class="mb-3">
                <label for="title" class="form-label">Страна</label>
                <input type="text" name="title" id="title" class="form-control"
                       value="{{ request('title') }}">
            </div>
            <div class="mb-3">
                <label for="category" class="form-label">Категория</label>
                <select name="category" id="category" class="form-control">
                    <option value="">Все категории</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}"
                                @if(request('category') === $category) selected @endif>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Найти</button>
            <a href="{{ route('countries.index', ['reset' => true]) }}" class="btn btn-secondary">Сбросить</a>
        </form>
        <hr>
    </div>
    <div class="mt-2">
        <a href="{{route('contact.index')}}" class="btn btn-primary btn-block">Задать вопрос</a>
    </div>
    <div class="mt-2">
        <a href="{{route('review.index')}}" class="btn btn-primary btn-block">Оставить отзыв</a>
    </div>
</div>
