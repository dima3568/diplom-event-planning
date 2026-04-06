<?php
/**
 * Файл: pages/events/edit.php
 * Описание: Редактирование мероприятия и обработка удаления.
 *           POST-запросы: update (изменение данных) и delete (мягкое удаление).
 *           Защита: CSRF, проверка прав (canManageEvent),
 *                   prepared statements PDO, PRG-паттерн.
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

$userId = (int) $_SESSION['user_id'];

/* Определяем действие */
$action = $_POST['action'] ?? 'update';

/* ===== ОБРАБОТКА УДАЛЕНИЯ ===== */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $eventId   = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

    if (!verifyCsrfToken($csrfToken) || $eventId <= 0) {
        redirect('list.php');
    }

    $result = deleteEvent($pdo, $eventId, $userId);
    if ($result['success']) {
        /* Логирование удаления */
        logAction($pdo, $userId, 'delete', 'event', $eventId, 'Мероприятие отменено');
    }

    redirect('list.php');
}

/* ===== ОБРАБОТКА ОБНОВЛЕНИЯ ===== */
if ($action === 'update') {
    $eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($eventId <= 0) {
        redirect('list.php');
    }

    $event = getEvent($pdo, $eventId);
    if (!$event) {
        redirect('list.php');
    }

    /* Проверка прав */
    if (!canManageEvent($pdo, $eventId, $userId)) {
        redirect('view.php?id=' . $eventId);
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!verifyCsrfToken($csrfToken)) {
            $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
        } else {
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $startDt     = $_POST['start_datetime'] ?? '';
            $endDt       = $_POST['end_datetime'] ?? '';
            $location    = trim($_POST['location'] ?? '');
            $status      = $_POST['status'] ?? 'draft';

            if ($title === '') {
                $error = 'Название мероприятия обязательно.';
            } elseif (!in_array($status, ['draft', 'active', 'completed', 'cancelled'])) {
                $error = 'Некорректный статус.';
            } elseif ($startDt !== '' && strtotime($startDt) === false) {
                $error = 'Некорректная дата начала.';
            } elseif ($endDt !== '' && strtotime($endDt) === false) {
                $error = 'Некорректная дата окончания.';
            } elseif ($startDt !== '' && $endDt !== '' && strtotime($endDt) <= strtotime($startDt)) {
                $error = 'Дата окончания должна быть позже даты начала.';
            } else {
                $result = updateEvent($pdo, $eventId, $userId, $title, $description, $startDt, $endDt, $location, $status);

                if ($result['success']) {
                    /* Логирование обновления */
                    logAction($pdo, $userId, 'update', 'event', $eventId, 'Обновление: ' . $title);
                    redirect('view.php?id=' . $eventId);
                } else {
                    $error = $result['error'];
                }
            }
        }
    }

    $csrfToken = generateCsrfToken();
    $username  = $_SESSION['username'] ?? 'Пользователь';

    /* Заполняем форму данными из БД (если нет POST-ошибки) */
    $formData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $event;
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Редактирование — <?= escape($event['title']) ?></title>
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
                    <span class="text-muted small"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= escape($username) ?></span>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm" role="button">Выйти</a>
                </div>
            </div>
        </nav>

        <div class="container py-4">
            <nav aria-label="Навигация">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Панель</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Мероприятия</a></li>
                    <li class="breadcrumb-item"><a href="view.php?id=<?= $eventId ?>"><?= escape($event['title']) ?></a></li>
                    <li class="breadcrumb-item active">Редактирование</li>
                </ol>
            </nav>

            <div class="auth-card card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Редактирование мероприятия</h4>
                </div>
                <div class="card-body">
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= escape($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="edit.php?id=<?= $eventId ?>" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">Название <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                <input type="text" class="form-control" id="title" name="title" required maxlength="200"
                                       value="<?= escape($formData['title'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Описание</label>
                            <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= escape($formData['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="start_datetime" class="form-label fw-bold">Начало</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime"
                                           value="<?= isset($formData['start_datetime']) && $formData['start_datetime'] ? date('Y-m-d\TH:i', strtotime($formData['start_datetime'])) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="end_datetime" class="form-label fw-bold">Окончание</label>
            <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                                    <input type="datetime-local" class="Form-control" id="end_datetime" name="end_datetime"
                                           value="<?= isset($formData['end_datetime']) && $formData['end_datetime'] ? date('Y-m-d\TH:i', strtotime($formData['end_datetime'])) : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label fw-bold">Место проведения</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control" id="location" name="location" maxlength="200"
                                       value="<?= escape($formData['location'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label fw-bold">Статус</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft"     <?= ($formData['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                                <option value="active"    <?= ($formData['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активно</option>
                                <option value="completed" <?= ($formData['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Завершено</option>
                                <option value="cancelled" <?= ($formData['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Отменено</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-auth-primary">
                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Сохранить
                            </button>
                            <a href="view.php?id=<?= $eventId ?>" class="btn btn-outline-secondary">Отмена</a>
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
    <?php
    exit;
}

/* Если действие неизвестно — редирект */
redirect('list.php');
