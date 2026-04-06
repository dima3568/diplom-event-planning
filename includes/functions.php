<?php
/**
 * Файл: includes/functions.php
 * Описание: Вспомогательные функции — санитизация ввода, валидация, редиректы.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

/* ======================================================================
   Санитизация вывода (защита от XSS)
   ====================================================================== */

/**
 * Экранирует HTML-сущности для безопасного вывода в браузер.
 *
 * @param string $data Входная строка
 * @return string Экранированная строка
 */
function escape(string $data): string
{
    return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ======================================================================
   Валидация
   ====================================================================== */

/**
 * Проверяет корректность email.
 *
 * @param string $email Проверяемый адрес
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Проверяет логин: только буквы, цифры, подчёркивание, дефис; от 3 до 30 символов.
 *
 * @param string $username
 * @return bool
 */
function isValidUsername(string $username): bool
{
    return preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username) === 1;
}

/**
 * Проверяет пароль: минимум 6 символов.
 *
 * @param string $password
 * @return bool
 */
function isValidPassword(string $password): bool
{
    return strlen($password) >= 6;
}

/* ======================================================================
   Редирект с принудительным завершением скрипта
   ====================================================================== */

/**
 * Выполняет HTTP-редирект и завершает выполнение скрипта.
 *
 * @param string $url URL для перенаправления
 * @return never
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
