<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Авилона')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            margin: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 30px;
        }
        .email-body h2 {
            color: #667eea;
            margin-top: 0;
        }
        .info-box {
            background-color: #f8f9fc;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box p {
            margin: 5px 0;
        }
        .info-box strong {
            color: #667eea;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            opacity: 0.9;
        }
        .email-footer {
            background-color: #f8f9fc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            color: #fff;
        }
        .status-new {
            background-color: #667eea;
        }
        .status-progress {
            background-color: #f6c23e;
        }
        .status-confirmed {
            background-color: #1cc88a;
        }
        .status-completed {
            background-color: #6c757d;
        }
        .status-cancelled {
            background-color: #e74a3b;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 2px 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>🌴 Авилона</h1>
            <p>Туристическое агентство</p>
        </div>
        
        <div class="email-body">
            @yield('content')
        </div>
        
        <div class="email-footer">
            <p><strong>ООО "Авилона"</strong></p>
            <p>
                Телефон: <a href="tel:+79219314345">+7 (921) 931-43-45</a>,
                <a href="tel:+79219842022">+7 (921) 984-20-22</a><br>
                Email: <a href="mailto:avilonatur@bk.ru">avilonatur@bk.ru</a><br>
                Сайт: <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
            </p>
            <p style="margin-top: 15px; color: #999;">
                Вы получили это письмо, потому что зарегистрированы на нашем сайте.<br>
                Если у вас есть вопросы, ответьте на это письмо или свяжитесь с нами.
            </p>
        </div>
    </div>
</body>
</html>
