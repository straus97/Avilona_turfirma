// скрипт для кнокпи скроллинга вверх страницы
$(document).ready(function () {
    $(window).scroll(function () {
        if ($(window).width() >= 1024) { // проверяем ширину экрана
            if ($(this).scrollTop() > 100) {
                $('#button-up').fadeIn();
            } else {
                $('#button-up').fadeOut();
            }
        } else {
            $('#button-up').hide(); // скрываем кнопку, если ширина экрана меньше 1200px
        }
    });
    $('#button-up').click(function () {
        $('html, body').animate({scrollTop: 0}, 500);
        return false;
    });
});

//скрипт для открытия модальног окна по нажатию на "Связаться с менеджером"
var modal = document.getElementById('contactModal');
var closeButton = document.getElementsByClassName("close-button")[0];

function openModal(number, manager) {
    currentNumber = number; // Сохраняем номер для использования в других функциях
    var managerText = 'связаться с менеджером ' + manager + ' через:'; // Текст для вывода в модальном окне
    document.getElementById('managerName').textContent = managerText; // Обновляем текст в модальном окне
    document.getElementById('contactModal').style.display = 'block'; // Показываем модальное окно
}

function openWhatsApp(number) {
    window.open(`https://wa.me/${number}`, '_blank');
    document.getElementById('contactModal').style.display = 'none'; // Скрываем модальное окно после выбора
}

function openTelegram(number) {
    window.open(`https://t.me/${number}`, '_blank');
    document.getElementById('contactModal').style.display = 'none'; // Скрываем модальное окно после выбора
}

closeButton.onclick = function () {
    modal.style.display = "none";
}

window.onclick = function (event) {
    var modal = document.getElementById('contactModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}
