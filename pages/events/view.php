<?php
/**
 * Файл: pages/events/view.php
 * Описание: Просмотр мероприятия — полная информация, лента активности,
 *           быстрые ссылки на задачи, комментарии, участников.
 *           Проверка прав: если не участник — отказ в доступе.
 *           Временная шкала активности из activity_log.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/tasks.php';
require_once __DIR__ . '/../../includes/comments.php';
require_once __DIR__ . '/../../includes/log.php';
require_once __DIR__ . '/../../includes/db.php';

checkAuth();

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

/* Получаем ID мероприятия */
$eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($eventId <= 0) {
    redirect('list.php');
}

/* Получаем мероприятие */
$event = getEvent($pdo, $eventId);
if (!$event) {
    redirect('list.php');
}

/* Проверка: пользователь — участник или создатель? */
$isParticipant = isEventParticipant($pdo, $eventId, $userId);
$isCreator     = (int) $event['creator_id'] === $userId;
$canManage     = $isCreator || $isParticipant && canManageEvent($pdo, $eventId, $userId);

if (!$isParticipant && !$isCreator) {
    redirect('list.php');
}

/* Подсчёт задач */
$allTasks   = getTasks($pdo, $eventId);
$doneTasks  = array_filter($allTasks, fn($t) => (int) $t['is_done'] === 1);
$doneCount  = count($doneTasks);
$totalCount = count($allTasks);

/* Лента активности */
$activityLog = getEventLog($pdo, $eventId, 30);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($event['title']) ?> — EventPlanner</title>
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
        <!-- Хлебные крошки -->
        <nav aria-label="Навигация по мероприятиям">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Панель</a></li>
                <li class="breadcrumb-item"><a href="list.php">Мероприятия</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= escape($event['title']) ?></li>
            </ol>
        </nav>

        <!-- Заголовок мероприятия -->
        <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-2">
            <div>
                <h2 class="fw-bold mb-1"><?= escape($event['title']) ?></h2>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <?= eventStatusBadge($event['status']) ?>
                    <span class="text-muted small">
                        <i class="bi bi-person me-1" aria-hidden="true"></i>
                        Создатель: <?= escape($event['creator_name']) ?>
                    </span>
                </div>
            </div>
            <?php if ($canManage): ?>
                <div class="btn-group btn-group-sm">
                    <a href="edit.php?id=<?= $eventId ?>" class="btn btn-outline-primary" role="button">
                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Редактировать
                    </a>
                    <a href="participants.php?event_id=<?= $eventId ?>" class="btn btn-outline-secondary" role="button">
                        <i class="bi bi-people me-1" aria-hidden="true"></i>Участники
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Левая колонка: информация -->
            <div class="col-lg-8">
                <!-- Детали -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Подробности
                    </div>
                    <div class="card-body">
                        <?php if (!empty($event['description'])): ?>
                            <p class="mb-3"><?= nl2br(escape($event['description'])) ?></p>
                        <?php endif; ?>
                        <div class="row g-2">
                            <?php if (!empty($event['start_datetime'])): ?>
                                <div class="col-sm-6">
                                    <span class="text-muted small"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Начало:</span><br>
                                    <strong><?= escape(date('d.m.Y H:i', strtotime($event['start_datetime']))) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($event['end_datetime'])): ?>
                                <div class="col-sm-6">
                                    <span class="text-muted small"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Окончание:</span><br>
                                    <strong><?= escape(date('d.m.Y H:i', strtotime($event['end_datetime']))) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($event['location'])): ?>
                                <div class="col-12">
                                    <span class="text-muted small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Место:</span><br>
                                    <strong><?= escape($event['location']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Прогресс задач -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-list-check me-1" aria-hidden="true"></i>Задачи: <?= $doneCount ?> / <?= $totalCount ?>
                    </div>
                    <div class="card-body">
                        <?php if ($totalCount > 0): ?>
                            <div class="progress mb-3" role="progressbar" aria-valuenow="<?= round($doneCount / $totalCount * 100) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: <?= $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0 ?>%"></div>
                            </div>
                        <?php endif; ?>
                        <a href="../tasks.php?event_id=<?= $eventId ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-list-task me-1" aria-hidden="true"></i>Управление задачами
                        </a>
                    </div>
                </div>

                <!-- Комментарии -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Комментарии
                    </div>
                    <div class="card-body">
                        <a href="../comments.php?event_id=<?= $eventId ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-chat-text me-1" aria-hidden="true"></i>Открыть ленту комментариев
                        </a>
                    </div>
                </div>
            </div>

            <!-- Правая колонка: лента активности -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-clock-history me-1" aria-hidden="true"></i>История активности
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activityLog)): ?>
                            <div class="text-center text-muted py-4 small">
                                Нет записей активности
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($activityLog as $log): ?>
                                    <div class="list-group-item border-0 px-3 py-2">
                                        <div class="d-flex gap-2">
                                            <i class="bi bi-person-circle text-muted mt-1" aria-hidden="true"></i>
                                            <div class="small">
                                                <strong><?= escape($log['username'] ?? '—') ?></strong>
                                                <span class="text-muted"><?= escape($log['action']) ?>
                                                    <?php if ($log['entity_type']): ?>
                                                        <?= escape($log['entity_type']) ?>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if (!empty($log['details'])): ?>
                                                    <br><span class="text-muted fst-italic"><?= escape($log['details']) ?></span>
                                                <?php endif; ?>
                                                <br><span class="text-muted" style="font-size: 0.75rem;">
                                                    <?= escape(date('d.m.Y H:i', strtotime($log['created_at']))) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Кнопка удаления (только для организаторов) -->
        <?php if ($canManage): ?>
            <div class="mt-4 text-end">
                <!-- Модальное окно подтверждения удаления -->
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>Удалить мероприятие
                </button>

                <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel">Подтверждение удаления</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body">
                                <p>Вы уверены, что хотите удалить мероприятие <strong>«<?= escape($event['title']) ?>»</strong>?</p>
                                <p class="text-muted small">Это действие изменит статус на «Отменено». Все связанные задачи и комментарии будут удалены.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <!-- Форма удаления -->
                                <form method="post" action="edit.php" style="display:inline;">
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf_token" value="<?= escape(generateCsrfToken()) ?>">
                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="auth-footer">
        <div class="container">&copy; 2026 EventPlanner — Дипломный проект</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
