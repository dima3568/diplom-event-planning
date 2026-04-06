<?php
/**
 * Файл: index.php
 * Описание: Корневой файл — перенаправление на страницу входа.
 *           Если пользователь уже авторизован — на панель управления.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();
require_once __DIR__ . '/includes/functions.php';

/* Если авторизован — на панель, иначе — на вход */
if (!empty($_SESSION['user_id'])) {
    redirect('pages/dashboard.php');
} else {
    redirect('pages/login.php');
}
