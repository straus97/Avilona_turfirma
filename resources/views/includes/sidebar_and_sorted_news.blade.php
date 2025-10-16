<div class="col-md-2 d-none d-lg-block">
    <div class="mb-3">
        <h5>Фильтр</h5>
        <form action="{{ route('helpful_news.index') }}" method="get">
            <div class="form-group mb-2">
                <label for="date">Дата:</label>
                <input type="date" class="form-control" id="date" name="date" value="{{ request('date') }}">
            </div>
            <button type="submit" class="btn btn-primary">Найти</button>
            <a href="{{ route('helpful_news.index') }}" class="btn btn-secondary">Сбросить</a>
        </form>
        <sub>*выведет все записи от выбранной даты и ранее</sub>
        <hr>
    </div>
    <div class="mt-2">
        <a href="{{route('contact.index')}}" class="btn btn-primary btn-block">Задать вопрос</a>
    </div>
    <div class="mt-2">
        <a href="{{route('review.index')}}" class="btn btn-primary btn-block">Оставить отзыв</a>
    </div>
</div>
