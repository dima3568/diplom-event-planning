<?php
/**
 * Файл: pages/tasks.php
 * Описание: Управление задачами мероприятия.
 *           CRUD задач, назначение исполнителя из участников,
 *           чек-бокс для переключения is_done, сортировка по deadline/creator,
 *           защита от несанкционированного редактирования.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/tasks.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/db.php';

checkAuth();

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
if ($eventId <= 0) {
    redirect('events/list.php');
}

$event = getEvent($pdo, $eventId);
if (!$event) {
    redirect('events/list.php');
}

/* Проверка: пользователь — участник */
$isParticipant = isEventParticipant($pdo, $eventId, $userId);
$isCreator     = (int) $event['creator_id'] === $userId;
if (!$isParticipant && !$isCreator) {
    redirect('events/list.php');
}

$canManage = canManageEvent($pdo, $eventId, $userId);

$error   = '';
$success = '';

/* ===== Создание задачи ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignedTo  = isset($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : 0;
        $deadline    = $_POST['deadline'] ?? '';

        if ($title === '') {
            $error = 'Название задачи обязательно.';
        } elseif ($deadline !== '' && strtotime($deadline) === false) {
            $error = 'Некорректный дедлайн.';
        } else {
            $result = createTask($pdo, $eventId, $userId, $assignedTo, $title, $description, $deadline);

            if ($result['success']) {
                logAction($pdo, $userId, 'create', 'task', $result['id'], 'Задача: ' . $title);
                redirect('tasks.php?event_id=' . $eventId);
            } else {
                $error = $result['error'];
            }
        }
    }
}

/* ===== Переключение статуса ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $taskId    = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;

    if (verifyCsrfToken($csrfToken) && $taskId > 0) {
        $result = toggleTask($pdo, $taskId, $userId);
        if ($result['success']) {
            logAction($pdo, $userId, 'toggle_task', 'task', $taskId, 'Переключение статуса задачи');
        }
        redirect('tasks.php?event_id=' . $eventId);
    }
    redirect('tasks.php?event_id=' . $eventId);
}

/* ===== Удаление задачи ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $taskId    = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;

    if (verifyCsrfToken($csrfToken) && $taskId > 0) {
        $result = deleteTask($pdo, $taskId, $userId);
        if ($result['success']) {
            logAction($pdo, $userId, 'delete', 'task', $taskId, 'Удаление задачи');
        }
        redirect('tasks.php?event_id=' . $eventId);
    }
    redirect('tasks.php?event_id=' . $eventId);
}

/* ===== Фильтры и сортировка ===== */
$taskFilters = [];
if (!empty($_GET['done']) && in_array($_GET['done'], ['all', 'pending', 'completed'])) {
    $taskFilters['done'] = $_GET['done'];
}
if (!empty($_GET['sort']) && in_array($_GET['sort'], ['deadline', 'creator'])) {
    $taskFilters['sort'] = $_GET['sort'];
}

$tasks = getTasks($pdo, $eventId, $taskFilters);

/* Получаем участников для выпадающего списка */
try {
    $stmt = $pdo->prepare('SELECT id, username FROM users u INNER JOIN event_participants ep ON ep.user_id = u.id WHERE ep.event_id = :eid');
    $stmt->execute([':eid' => $eventId]);
    $participants = $stmt->fetchAll();
} catch (PDOException $e) {
    $participants = [];
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задачи — <?= escape($event['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>

    <nav class="navbar navbar-expand-lg shadow-sm bg-white" aria-label="Навигация">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php" style="color: var(--indigo);">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>EventPlanner
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= escape($username) ?></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm" role="button">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <nav aria-label="Навигация">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Панель</a></li>
                <li class="breadcrumb-item"><a href="events/list.php">Мероприятия</a></li>
                <li class="breadcrumb-item"><a href="events/view.php?id=<?= $eventId ?>"><?= escape($event['title']) ?></a></li>
                <li class="breadcrumb-item active">Задачи</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h3 class="fw-bold mb-0">
                <i class="bi bi-list-task me-2" aria-hidden="true"></i>Задачи
            </h3>
            <div class="d-flex gap-2">
                <!-- Фильтры -->
                <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='tasks.php?event_id=<?= $eventId ?>&done='+this.value<?= !empty($taskFilters['sort']) ? '&sort='.escape($taskFilters['sort']) : '' ?>">
                    <option value="all"   <?= ($taskFilters['done'] ?? 'all') === 'all' ? 'selected' : '' ?>>Все</option>
                    <option value="pending" <?= ($taskFilters['done'] ?? '') === 'pending' ? 'selected' : '' ?>>Активные</option>
                    <option value="completed" <?= ($taskFilters['done'] ?? '') === 'completed' ? 'selected' : '' ?>>Выполненные</option>
                </select>
                <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='tasks.php?event_id=<?= $eventId ?><?= !empty($taskFilters['done']) ? '&done='.escape($taskFilters['done']) : '' ?>&sort='+this.value">
                    <option value="deadline" <?= ($taskFilters['sort'] ?? 'deadline') === 'deadline' ? 'selected' : '' ?>>По дедлайну</option>
                    <option value="creator" <?= ($taskFilters['sort'] ?? '') === 'creator' ? 'selected' : '' ?>>По создателю</option>
                </select>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <!-- Форма создания задачи -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Новая задача
                </div>
                <div class="card-body">
                    <form method="post" action="tasks.php?event_id=<?= $eventId ?>" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create">

                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label for="task_title" class="form-label small">Название <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="task_title" name="title"
                                       placeholder="Название задачи" required maxlength="200">
                            </div>
                            <div class="col-md-3">
                                <label for="task_desc" class="form-label small">Описание</label>
                                <input type="text" class="form-control form-control-sm" id="task_desc" name="description"
                                       placeholder="Описание" maxlength="500">
                            </div>
                            <div class="col-md-2">
                                <label for="task_assigned" class="form-label small">Исполнитель</label>
                                <select class="form-select form-select-sm" id="task_assigned" name="assigned_to">
                                    <option value="0">Не назначен</option>
                                    <?php foreach ($participants as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= escape($p['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="task_deadline" class="form-label small">Дедлайн</label>
                                <input type="date" class="form-control form-control-sm" id="task_deadline" name="deadline">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-auth-primary btn-sm w-100">
                                    <i class="bi bi-plus" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Список задач -->
        <?php if (empty($tasks)): ?>
            <div class="text-center py-5">
                <i class="bi bi-clipboard-check text-muted" style="font-size: 3rem;" aria-hidden="true"></i>
                <p class="text-muted mt-3">Задач пока нет.</p>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($tasks as $task): ?>
                    <div class="list-group-item list-group-item-action <?= (int) $task['is_done'] ? 'bg-light' : '' ?>">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <!-- Чек-бокс выполнения -->
                            <?php if ($canManage || $userId === (int) $task['creator_id']): ?>
                                <form method="post" action="tasks.php?event_id=<?= $eventId ?>" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= (int) $task['is_done'] ? 'btn-success' : 'btn-outline-secondary' ?> p-1"
                                            title="<?= (int) $task['is_done'] ? 'Отметить как невыполненную' : 'Отметить как выполненную' ?>"
                                            style="width:32px;height:32px;">
                                        <i class="bi bi-<?= (int) $task['is_done'] ? 'check-square-fill' : 'square' ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Информация о задаче -->
                            <div class="flex-grow-1">
                                <h6 class="mb-1 <?= (int) $task['is_done'] ? 'text-decoration-line-through text-muted' : 'fw-bold' ?>">
                                    <?= escape($task['title']) ?>
                                </h6>
                                <?php if (!empty($task['description'])): ?>
                                    <small class="text-muted d-block"><?= escape($task['description']) ?></small>
                                <?php endif; ?>
                                <div class="d-flex gap-3 mt-1" style="font-size: 0.8rem;">
                                    <span class="text-muted">
                                        <i class="bi bi-person me-1" aria-hidden="true"></i><?= escape($task['creator_name']) ?>
                                    </span>
                                    <?php if (!empty($task['assigned_name'])): ?>
                                        <span class="text-muted">
                                            <i class="bi bi-person-check me-1" aria-hidden="true"></i><?= escape($task['assigned_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($task['deadline'])): ?>
                                        <span class="<?= strtotime($task['deadline']) < time() && !(int) $task['is_done'] ? 'text-danger fw-bold' : 'text-muted' ?>">
                                            <i class="bi bi-clock me-1" aria-hidden="true"></i><?= escape(date('d.m.Y', strtotime($task['deadline']))) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Удаление (только для организаторов) -->
                            <?php if ($canManage): ?>
                                <form method="post" action="tasks.php?event_id=<?= $eventId ?>"
                                      onsubmit="return confirm('Удалить задачу?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="events/view.php?id=<?= $eventId ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Вернуться к мероприятию
            </a>
        </div>
    </div>

    <footer class="auth-footer">
        <div class="container">&copy; 2026 EventPlanner — Дипломный проект</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
