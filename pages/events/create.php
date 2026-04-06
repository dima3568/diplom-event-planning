<?php
/**
 * Файл: pages/events/create.php
 * Описание: Форма создания нового мероприятия.
 *           Поля: название, описание, дата/время начала и окончания,
 *           место проведения, статус (по умолчанию «Черновик»).
 *           Защита: CSRF-токен, серверная валидация datetime,
 *           prepared statements PDO.
 *           После успешного создания — редирект на страницу просмотра.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/db.php';

checkAuth();

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

$error = '';

/* Обработка POST-запроса */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        /* Санитизация входных данных */
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $startDt     = $_POST['start_datetime'] ?? '';
        $endDt       = $_POST['end_datetime'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        $status      = $_POST['status'] ?? 'draft';

        /* Валидация */
        if ($title === '') {
            $error = 'Название мероприятия обязательно.';
        } elseif (!in_array($status, ['draft', 'active', 'completed', 'cancelled'])) {
            $error = 'Некорректный статус.';
        } else {
            /* Валидация дат (если указаны) */
            if ($startDt !== '' && strtotime($startDt) === false) {
                $error = 'Некорректная дата начала.';
            } elseif ($endDt !== '' && strtotime($endDt) === false) {
                $error = 'Некорректная дата окончания.';
            } elseif ($startDt !== '' && $endDt !== '' && strtotime($endDt) <= strtotime($startDt)) {
                $error = 'Дата окончания должна быть позже даты начала.';
            } else {
                /* Создание мероприятия */
                $result = createEvent($pdo, $userId, $title, $description, $startDt, $endDt, $location, $status);

                if ($result['success']) {
                    /* Логирование действия */
                    logAction($pdo, $userId, 'create', 'event', $result['id'], 'Создание мероприятия: ' . $title);

                    /* Редирект на страницу просмотра (PRG-паттерн) */
                    redirect('view.php?id=' . $result['id']);
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать мероприятие — EventPlanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="../../assets/style.css" rel="stylesheet">
</head>
<body>

    <nav class="navbar navbar-expand-lg shadow-sm bg-white" aria-label="Навигация">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard.php" style="color: var(--indigo);">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>EventPlanner
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <?= escape($username) ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm" role="button">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="auth-card card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Новое мероприятие
                </h4>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                        <?= escape($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="create.php" novalidate>
                    <?= csrfField() ?>

                    <!-- Название -->
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Название <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text" class="form-control" id="title" name="title"
                                   placeholder="Введите название мероприятия"
                                   required maxlength="200"
                                   value="<?= isset($_POST['title']) ? escape($_POST['title']) : '' ?>">
                        </div>
                    </div>

                    <!-- Описание -->
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Описание</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  maxlength="2000"
                                  placeholder="Подробное описание мероприятия"><?= isset($_POST['description']) ? escape($_POST['description']) : '' ?></textarea>
                    </div>

                    <!-- Дата и время -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="start_datetime" class="form-label fw-bold">Начало</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime"
                                       value="<?= isset($_POST['start_datetime']) ? escape($_POST['start_datetime']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="end_datetime" class="form-label fw-bold">Окончание</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                                <input type="datetime-local" class="form-control" id="end_datetime" name="end_datetime"
                                       value="<?= isset($_POST['end_datetime']) ? escape($_POST['end_datetime']) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Место проведения -->
                    <div class="mb-3">
                        <label for="location" class="form-label fw-bold">Место проведения</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                            <input type="text" class="form-control" id="location" name="location"
                                   placeholder="Адрес или площадка" maxlength="200"
                                   value="<?= isset($_POST['location']) ? escape($_POST['location']) : '' ?>">
                        </div>
                    </div>

                    <!-- Статус -->
                    <div class="mb-4">
                        <label for="status" class="form-label fw-bold">Статус</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft"     <?= (($_POST['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Черновик</option>
                            <option value="active"    <?= (($_POST['status'] ?? '') === 'active') ? 'selected' : '' ?>>Активно</option>
                            <option value="completed" <?= (($_POST['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Завершено</option>
                            <option value="cancelled" <?= (($_POST['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Отменено</option>
                        </select>
                    </div>

                    <!-- Кнопки -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-auth-primary">
                            <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Создать
                        </button>
                        <a href="list.php" class="btn btn-outline-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="auth-footer">
        <div class="container">&copy; 2026 EventPlanner — Дипломный проект</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
