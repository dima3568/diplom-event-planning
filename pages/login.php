<?php
/**
 * Файл: pages/login.php
 * Описание: Страница входа в систему.
 *           Форма: логин + пароль.
 *           Защита: CSRF-токен, подготовленные запросы PDO,
 *                   password_verify для проверки пароля.
 *           После успешного входа — редирект на dashboard.php.
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

$error   = '';
$success = '';

/* Проверяем, пришёл ли пользователь с регистрации */
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Регистрация прошла успешно. Теперь войдите в систему.';
}

/* Обработка POST-запроса (отправка формы) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Проверка CSRF-токена */
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        /* Получение и санитизация входных данных */
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        /* Валидация на стороне сервера */
        if ($username === '' || $password === '') {
            $error = 'Заполните все обязательные поля.';
        } else {
            /* Попытка аутентификации через функцию login() */
            $result = login($pdo, $username, $password);

            if ($result['success']) {
                /* Редирект на панель управления */
                redirect('dashboard.php');
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
    <meta name="description" content="Вход в EventPlanner — система планирования мероприятий">
    <title>Вход — EventPlanner</title>

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
                    <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Вход в систему
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

                <!-- Сообщение об успехе (после регистрации) -->
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        <?= escape($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                    </div>
                <?php endif; ?>

                <!-- Форма входа -->
                <form method="post" action="login.php" novalidate>
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
                                placeholder="Логин"
                                required
                                minlength="3"
                                maxlength="30"
                                autocomplete="username"
                                value="<?= isset($_POST['username']) ? escape($_POST['username']) : '' ?>"
                                aria-label="Логин"
                            >
                        </div>
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
                                placeholder="Пароль"
                                required
                                minlength="6"
                                autocomplete="current-password"
                                aria-label="Пароль"
                            >
                        </div>
                    </div>

                    <!-- Кнопка входа -->
                    <button type="submit" class="btn btn-auth-primary w-100 mb-3">
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Войти
                    </button>
                </form>

                <!-- Разделитель -->
                <div class="text-center text-muted small mb-3">
                    <span>или</span>
                </div>

                <!-- Кнопки OAuth-заглушки -->
                <!-- ВХОД ЧЕРЕЗ ГОСУСЛУГИ — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с API Госуслуг (ESIA). Требуется регистрация приложения в ЕСИА. -->
                <button type="button" class="btn btn-oauth w-100 mb-2" disabled aria-label="Вход через Госуслуги (скоро)">
                    <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Войти через Госуслуги
                </button>

                <!-- ВХОД ЧЕРЕЗ APPLE ID — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с Sign in with Apple. Требуется Apple Developer аккаунт. -->
                <button type="button" class="btn btn-oauth w-100 mb-2" disabled aria-label="Вход через Apple ID (скоро)">
                    <i class="bi bi-apple me-2" aria-hidden="true"></i>Войти через Apple ID
                </button>

                <!-- ВХОД ЧЕРЕЗ GOOGLE — ЗАГЛУШКА -->
                <!-- TODO: Интеграция с Google OAuth 2.0. Требуется Google Cloud Console. -->
                <button type="button" class="btn btn-oauth w-100 mb-3" disabled aria-label="Вход через Google (скоро)">
                    <i class="bi bi-google me-2" aria-hidden="true"></i>Войти через Google
                </button>

                <!-- Навигационные ссылки -->
                <div class="text-center">
                    <a href="register.php" class="auth-link">Создать аккаунт</a>
                    <span class="text-muted mx-1">·</span>
                    <a href="recover.php" class="auth-link">Забыли пароль?</a>
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
