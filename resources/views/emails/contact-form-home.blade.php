@component('mail::message')
    <!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новое сообщение с сайта avilona.ru</title>
    <style>
        /* Добавим стиль для сообщения пользователя */
        .user-message {
            word-wrap: break-word; /* Переносим слова, если они слишком длинные */
            white-space: pre-wrap; /* Сохраняем пробелы и переносы строк */
            max-width: 100%; /* Ограничиваем максимальную ширину */
        }
    </style>
</head>
<body>
<table cellpadding="0" cellspacing="0" style="width: 100%; background-color: #F8F9FA;">
    <tr>
        <td align="center" valign="top">
            <table cellpadding="0" cellspacing="0" style="width: 600px; background-color: #ffffff; margin: 0 auto;">
                <tr>
                    <td colspan="2" style="padding: 20px 0 5px 0; text-align: center;">
                        <a href="https://avilona.ru"><img src="https://avilona.ru/img/logo.png" alt="Avilona"
                                                          width="200"></a>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <h2>Новое сообщение с главной страницы сайта avilona.ru</h2>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 50px;">
                        <p><strong>Имя:</strong> {{ $validatedData['name'] }}</p>
                        <p><strong>Email:</strong> {{ $validatedData['email'] }}</p>
                        <p><strong>Тема:</strong> {{ $validatedData['subject'] }}</p>
                        <p><strong>Сообщение:</strong></p>
                        <p class="user-message">{{ $validatedData['message'] }}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="height: 1px; background-color: #E5E7EB;"></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 20px 50px; text-align: center;">
                        <a href="https://avilona.ru"
                           style="background-color: #333333; color: #ffffff; display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px;"
                           target="_blank">Вернуться на сайт</a>
                        <p class="email_footer">С любовью, турфирма Авилона!<br>
                            191119, Россия, Санкт-Петербург, ул. Генерала Симоняка, д. 10<br>
                            +7 (921) 931-43-45, +7 (921) 984-20-22<br></p>
                        <p></p><a href="https://avilona.ru" target="_blank"
                                  style="text-decoration: none;">avilona.ru</a></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
@endcomponent
