<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SEO-файлы (NOV-PUBLIC-01)
|--------------------------------------------------------------------------
|
| Подключаются без группы 'web': ответ не должен ставить сессионные cookie
| поисковым роботам. Регистрируются в обоих профилях релиза.
|
*/

Route::group(['namespace' => 'Seo'], function () {
    Route::get('/robots.txt', 'SeoFileController@robots')->name('seo.robots');
    Route::get('/sitemap.xml', 'SeoFileController@sitemap')->name('seo.sitemap');
});
