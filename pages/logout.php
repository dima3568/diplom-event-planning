<?php
/**
 * Файл: pages/logout.php
 * Описание: Выход из системы — уничтожение сессии и редирект на страницу входа.
 *           Очищает массив $_SESSION, удаляет cookie сессии,
 *           вызывает session_destroy(), затем редирект с exit.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

/* Очистка массива сессии */
$_SESSION = [];

/* Удаление cookie сессии */
if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

/* Уничтожение сессии */
session_destroy();

/* Редирект на страницу входа (абсолютный путь для надёжности) */
header('Location: login.php');
exit;
