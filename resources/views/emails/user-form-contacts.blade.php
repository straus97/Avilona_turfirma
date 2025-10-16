@component('mail::message')
    <!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новое сообщение с сайта avilona.ru</title>
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
                        <h2>Информационное сообщение с сайта avilona.ru</h3>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 50px;">
                        <p><strong>{{ $validatedData['name'] }}</strong>, здравствуйте!</p>
                        <p>Большое спасибо за оставленное сообщение на сайте avilona.ru. Мы постараемся прочитать его
                            как можно скорее,
                            если оно требует ответа, то мы обязательно свяжемся с вами.</p>
                        <p><strong>*</strong>Данное сообщение было автоматически сформировано. Пожалуйста, не отвечайте
                            на него!</p>
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
                            191119, Россия, Санкт-Петербург, Звенигородская улица, д. 22, офис 053<br>
                            +7 (921) 931-43-45, +7 (921) 984-20-22<br>
                            <a href="https://avilona.ru" target="_blank">avilona.ru</a></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
@endcomponent
