<?php
/**
 * Файл: includes/auth.php
 * Описание: Функции авторизации — регистрация, вход, выход,
 *           проверка авторизации, генерация и проверка CSRF-токенов.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

/* Подключаем вспомогательные функции и подключение к БД */
require_once __DIR__ . '/functions.php';

/* ======================================================================
   CSRF-токены
   ====================================================================== */

/**
 * Генерирует CSRF-токен и сохраняет его в сессии.
 *
 * @return string Токен
 */
function generateCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Проверяет валидность CSRF-токена из POST-запроса.
 *
 * @param string $token Токен из формы
 * @return bool
 */
function verifyCsrfToken(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Возвращает скрытое поле с CSRF-токеном для вставки в форму.
 *
 * @return string HTML-код скрытого input
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . escape($token) . '">';
}

/* ======================================================================
   Регистрация
   ====================================================================== */

/**
 * Регистрирует нового пользователя.
 *
 * @param PDO $pdo        PDO-подключение
 * @param string $username Логин
 * @param string $password Пароль (открытый)
 * @param string $email    Email (необязательный)
 * @return array           ['success' => bool, 'error' => string|null]
 */
function register(PDO $pdo, string $username, string $password, string $email = ''): array
{
    /* Валидация логина */
    if (!isValidUsername($username)) {
        return ['success' => false, 'error' => 'Логин должен содержать от 3 до 30 символов (буквы, цифры, _, -).'];
    }

    /* Валидация пароля */
    if (!isValidPassword($password)) {
        return ['success' => false, 'error' => 'Пароль должен содержать минимум 6 символов.'];
    }

    /* Валидация email (если указан) */
    if ($email !== '' && !isValidEmail($email)) {
        return ['success' => false, 'error' => 'Некорректный email-адрес.'];
    }

    /* Хеширование пароля алгоритмом по умолчанию */
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        /* Подготовленный запрос — защита от SQL-инъекций */
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, email) VALUES (:username, :password_hash, :email)'
        );
        $stmt->execute([
            ':username'      => $username,
            ':password_hash' => $passwordHash,
            ':email'         => $email !== '' ? $email : null,
        ]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        /* Если ошибка — проверяем, не нарушение ли уникальности */
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            return ['success' => false, 'error' => 'Пользователь с таким логином уже существует.'];
        }

        /* Остальные ошибки БД — логируем, но не выводим детали */
        error_log('Ошибка регистрации: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при регистрации. Попробуйте позже.'];
    }
}

/* ======================================================================
   Вход (аутентификация)
   ====================================================================== */

/**
 * Аутентифицирует пользователя по логину и паролю.
 *
 * @param PDO $pdo        PDO-подключение
 * @param string $username Логин
 * @param string $password Пароль (открытый)
 * @return array           ['success' => bool, 'error' => string|null]
 */
function login(PDO $pdo, string $username, string $password): array
{
    try {
        /* Подготовленный запрос — защита от SQL-инъекций */
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        /* Если пользователь не найден — универсальное сообщение об ошибке */
        if ($user === false) {
            return ['success' => false, 'error' => 'Неверный логин или пароль.'];
        }

        /* Проверка пароля через password_verify */
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Неверный логин или пароль.'];
        }

        /* Установка сессионных переменных */
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка входа: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при входе. Попробуйте позже.'];
    }
}

/* ======================================================================
   Выход (разлогинивание)
   ====================================================================== */

/**
 * Уничтожает сессию и перенаправляет на страницу входа.
 *
 * @return never
 */
function logout(): never
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        /* Очистка массива сессии */
        $_SESSION = [];

        /* Удаление файла сессии */
        if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }

    redirect('login.php');
}

/* ======================================================================
   Проверка авторизации
   ====================================================================== */

/**
 * Проверяет, авторизован ли пользователь. Если нет — редирект на вход.
 *
 * @return void
 */
function checkAuth(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}
