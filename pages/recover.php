<?php
/**
 * Файл: pages/recover.php
 * Описание: Страница восстановления пароля.
 *           Форма: ввод логина или email.
 *           Защита: CSRF-токен.
 *           Логика: заглушка — выводит сообщение об отправке ссылки.
 *           TODO: В будущем интеграция с PHPMailer для реальной отправки писем.
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

$success = '';
$error   = '';

/* Обработка POST-запроса (отправка формы) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Проверка CSRF-токена */
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        /* Получение и санитизация входных данных */
        $loginOrEmail = trim($_POST['login_or_email'] ?? '');

        if ($loginOrEmail === '') {
            $error = 'Введите логин или email.';
        } else {
            /*
             * ЗАГЛУШКА: Реальная отправка письма будет реализована
             * через PHPMailer после интеграции SMTP-сервера.
             *
             * TODO:
             *  1. Найти пользователя по логину или email в БД.
             *  2. Сгенерировать токен сброса пароля и сохранить в БД
             *     с таймаутом (например, 1 час).
             *  3. Отправить email с ссылкой на сброс:
             *       $mail = new PHPMailer(true);
             *       $mail->isSMTP();
             *       $mail->Host       = 'smtp.example.com';
             *       $mail->SMTPAuth   = true;
             *       $mail->Username   = 'noreply@example.com';
             *       $mail->Password   = 'your-smtp-password';
             *       $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
             *       $mail->Port       = 587;
             *       $mail->setFrom('noreply@example.com', 'EventPlanner');
             *       $mail->addAddress($userEmail);
             *       $mail->Subject = 'Сброс пароля — EventPlanner';
             *       $mail->Body    = "Ссылка для сброса: https://example.com/reset.php?token={$resetToken}";
             *       $mail->send();
             *  4. Перенаправить на страницу с подтверждением.
             */

            /* Для безопасности — всегда выводим одно сообщение,
               чтобы не раскрывать, существует ли пользователь */
            $success = 'Если указанный логин или email зарегистрирован в системе, '
                     . 'вы получите письмо с инструкцией по восстановлению пароля.';
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
    <meta name="description" content="Восстановление пароля — EventPlanner">
    <title>Восстановление пароля — EventPlanner</title>

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
                    <i class="bi bi-key me-2" aria-hidden="true"></i>Восстановление пароля
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

                <!-- Сообщение об успехе -->
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        <?= escape($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                    </div>
                <?php endif; ?>

                <!-- Форма восстановления пароля -->
                <form method="post" action="recover.php" novalidate>
                    <?= csrfField() ?>

                    <!-- Поле: Логин или Email -->
                    <div class="mb-3">
                        <label for="login_or_email" class="form-label visually-hidden">Логин или Email</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="login_or_email"
                                name="login_or_email"
                                placeholder="Логин или email"
                                required
                                autocomplete="username"
                                value="<?= isset($_POST['login_or_email']) ? escape($_POST['login_or_email']) : '' ?>"
                                aria-label="Логин или электронная почта"
                            >
                        </div>
                        <div class="form-text">Введите логин или email, указанный при регистрации</div>
                    </div>

                    <!-- Кнопка отправки -->
                    <button type="submit" class="btn btn-auth-primary w-100 mb-3">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Отправить ссылку
                    </button>
                </form>

                <!-- Навигационные ссылки -->
                <div class="text-center">
                    <a href="login.php" class="auth-link">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Вернуться ко входу
                    </a>
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
