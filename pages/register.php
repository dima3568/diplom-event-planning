<?php
/**
 * Файл: pages/register.php
 * Описание: Страница регистрации нового пользователя.
 *           Форма: логин + пароль + подтверждение пароля + email (необязательно).
 *           Валидация: HTML5-атрибуты на фронтенде, серверная проверка.
 *           Защита: CSRF-токен, подготовленные запросы PDO,
 *                   password_hash(PASSWORD_DEFAULT) для хеширования пароля,
 *                   уникальность логина enforced на уровне БД.
 *           После успешной регистрации — редирект на login.php с флагом success.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

/* Если пользователь уже авторизован — перенаправляем на панель */
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/functions.php';
    redirect('dashboard.php');
}

/* Подключаем зависимости */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';

/* Обработка POST-запроса (отправка формы) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Проверка CSRF-токена */
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        /* Получение и санитизация входных данных */
        $username        = trim($_POST['username'] ?? '');
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $email           = trim($_POST['email'] ?? '');

        /* Валидация на стороне сервера */
        if ($username === '' || $password === '' || $passwordConfirm === '') {
            $error = 'Заполните все обязательные поля.';
        } elseif (!isValidUsername($username)) {
            $error = 'Логин должен содержать от 3 до 30 символов (буквы, цифры, _, -).';
        } elseif (!isValidPassword($password)) {
            $error = 'Пароль должен содержать минимум 6 символов.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Пароли не совпадают.';
        } elseif ($email !== '' && !isValidEmail($email)) {
            $error = 'Некорректный email-адрес.';
        } else {
            /* Попытка регистрации через функцию register() */
            $result = register($pdo, $username, $password, $email);

            if ($result['success']) {
                /* Редирект на страницу входа с параметром успеха */
                redirect('login.php?registered=1');
            } else {
                $error = $result['error'];
            }
        }
    }
}

/* Генерация нового CSRF-токена для формы */
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Регистрация в EventPlanner — система планирования мероприятий">
    <title>Регистрация — EventPlanner</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Кастомные стили -->
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>

    <div class="container py-5">
        <div class="auth-card card">
            <!-- Заголовок карточки -->
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Регистрация
                </h4>
            </div>

            <div class="card-body">
                <!-- Сообщение об ошибке -->
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                        <?= escape($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                    </div>
                <?php endif; ?>

                <!-- Форма регистрации -->
                <form method="post" action="register.php" novalidate>
                    <?= csrfField() ?>

                    <!-- Поле: Логин -->
                    <div class="mb-3">
                        <label for="username" class="form-label visually-hidden">Логин</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-person"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                placeholder="Логин (3-30 символов)"
                                required
                                minlength="3"
                                maxlength="30"
                                pattern="[a-zA-Z0-9_\-]{3,30}"
                                autocomplete="username"
                                value="<?= isset($_POST['username']) ? escape($_POST['username']) : '' ?>"
                                aria-label="Логин"
                            >
                        </div>
                        <div class="form-text">Только буквы, цифры, «_» и «-»</div>
                    </div>

                    <!-- Поле: Пароль -->
                    <div class="mb-3">
                        <label for="password" class="form-label visually-hidden">Пароль</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Пароль (минимум 6 символов)"
                                required
                                minlength="6"
                                autocomplete="new-password"
                                aria-label="Пароль"
                            >
                        </div>
                    </div>

                    <!-- Поле: Подтверждение пароля -->
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label visually-hidden">Подтверждение пароля</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input
                                type="password"
                                class="form-control"
                                id="password_confirm"
                                name="password_confirm"
                                placeholder="Подтвердите пароль"
                                required
                                minlength="6"
                                autocomplete="new-password"
                                aria-label="Подтверждение пароля"
                            >
                        </div>
                    </div>

                    <!-- Поле: Email (необязательное) -->
                    <div class="mb-3">
                        <label for="email" class="form-label visually-hidden">Email</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                placeholder="Email (необязательно)"
                                autocomplete="email"
                                value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>"
                                aria-label="Электронная почта"
                            >
                        </div>
                    </div>

                    <!-- Кнопка регистрации -->
                    <button type="submit" class="btn btn-auth-primary w-100 mb-3">
                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Зарегистрироваться
                    </button>
                </form>

                <!-- Разделитель -->
                <div class="text-center text-muted small mb-3">
                    <span>или</span>
                </div>

                <!-- Кнопки OAuth-заглушки -->
                <!-- РЕГИСТРАЦИЯ ЧЕРЕЗ ГОСУСЛУГИ — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с API Госуслуг (ESIA). -->
                <button type="button" class="btn btn-oauth w-100 mb-2" disabled aria-label="Регистрация через Госуслуги (скоро)">
                    <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Зарегистрироваться через Госуслуги
                </button>

                <!-- РЕГИСТРАЦИЯ ЧЕРЕЗ APPLE ID — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с Sign in with Apple. -->
                <button type="button" class="btn btn-oauth w-100 mb-2" disabled aria-label="Регистрация через Apple ID (скоро)">
                    <i class="bi bi-apple me-2" aria-hidden="true"></i>Зарегистрироваться через Apple ID
                </button>

                <!-- РЕГИСТРАЦИЯ ЧЕРЕЗ GOOGLE — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с Google OAuth 2.0. -->
                <button type="button" class="btn btn-oauth w-100 mb-3" disabled aria-label="Регистрация через Google (скоро)">
                    <i class="bi bi-google me-2" aria-hidden="true"></i>Зарегистрироваться через Google
                </button>

                <!-- Навигационные ссылки -->
                <div class="text-center">
                    <span class="text-muted">Уже есть аккаунт?</span>
                    <a href="login.php" class="auth-link">Войти</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Футер -->
    <footer class="auth-footer">
        <div class="container">
            &copy; 2026 EventPlanner — Дипломный проект
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"></script>
</body>
</html>
