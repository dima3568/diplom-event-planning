<?php
/**
 * Файл: pages/dashboard.php
 * Описание: Панель управления — обзор мероприятий пользователя.
 *           Выводит список мероприятий через LEFT JOIN, агрегацию
 *           активных задач и ближайших событий, статус-бейджи,
 *           карточки быстрых действий и заглушку статистики.
 *           Обязательная проверка сессии (checkAuth).
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

/* Подключаем зависимости */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/db.php';

/* Проверка авторизации */
checkAuth();

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

/* Получаем мероприятия пользователя */
$eventsData = getEventsList($pdo, $userId, [], 1, 5);
$events     = $eventsData['events'];

/* Агрегация: количество активных задач */
try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tasks t
         INNER JOIN events e ON e.id = t.event_id
         WHERE t.is_done = 0 AND (e.creator_id = :uid OR EXISTS (
             SELECT 1 FROM event_participants ep WHERE ep.event_id = e.id AND ep.user_id = :uid
         ))'
    );
    $stmt->execute([':uid' => $userId]);
    $activeTasksCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Ошибка агрегации задач: ' . $e->getMessage());
    $activeTasksCount = 0;
}

/* Агрегация: количество ближайших активных событий (в течение 7 дней) */
try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT e.id) FROM events e
         LEFT JOIN event_participants ep ON ep.event_id = e.id
         WHERE (e.creator_id = :uid OR ep.user_id = :uid)
           AND e.status = \'active\'
           AND e.start_datetime BETWEEN datetime(\'now\') AND datetime(\'now\', \'+7 days\')'
    );
    $stmt->execute([':uid' => $userId]);
    $upcomingCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Ошибка агрегации событий: ' . $e->getMessage());
    $upcomingCount = 0;
}

/* Общее количество мероприятий */
$totalEvents = $eventsData['total'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Панель управления — EventPlanner">
    <title>Панель управления — EventPlanner</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Кастомные стили -->
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-number { font-size: 2rem; font-weight: 800; }
        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: box-shadow 0.2s ease;
        }
        .event-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <!-- ===== Навигация ===== -->
    <nav class="navbar navbar-expand-lg shadow-sm bg-white" aria-label="Навигация панели управления">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php" style="color: var(--indigo);">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>EventPlanner
            </a>
            <div class="d-flex align-items-center gap-3">
                <?php if ($username === 'admin'): ?>
                    <a href="admin.php" class="btn btn-sm btn-outline-danger" role="button">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Админ-панель
                    </a>
                <?php endif; ?>
                <span class="text-muted small">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <?= escape($username) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm" role="button">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Выйти
                </a>
            </div>
        </div>
    </nav>

    <!-- ===== Основной контент ===== -->
    <div class="container py-4">
        <!-- Приветствие -->
        <h2 class="fw-bold mb-1">Добро пожаловать, <?= escape($username) ?>!</h2>
        <p class="text-muted mb-4">Управляйте своими мероприятиями, задачами и командой.</p>

        <!-- ===== Статистика (заглушка) ===== -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;" aria-hidden="true"></i>
                        <div class="stat-number text-primary mt-2"><?= $totalEvents ?></div>
                        <div class="text-muted small">Мероприятий</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-list-check text-warning" style="font-size: 2rem;" aria-hidden="true"></i>
                        <div class="stat-number text-warning mt-2"><?= $activeTasksCount ?></div>
                        <div class="text-muted small">Активных задач</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history text-success" style="font-size: 2rem;" aria-hidden="true"></i>
                        <div class="stat-number text-success mt-2"><?= $upcomingCount ?></div>
                        <div class="text-muted small">Ближайших событий</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Карточки быстрых действий ===== -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <a href="events/create.php" class="btn btn-auth-primary w-100 py-3" role="button">
                    <i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Создать мероприятие
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="events/list.php" class="btn btn-outline-primary w-100 py-3" role="button">
                    <i class="bi bi-journal-text me-2" aria-hidden="true"></i>Все мероприятия
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="events/list.php?status%5B%5D=active" class="btn btn-outline-success w-100 py-3" role="button">
                    <i class="bi bi-play-circle me-2" aria-hidden="true"></i>Активные события
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="events/list.php?status%5B%5D=draft" class="btn btn-outline-secondary w-100 py-3" role="button">
                    <i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Черновики
                </a>
            </div>
        </div>

        <!-- ===== Последние мероприятия ===== -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Последние мероприятия</h4>
            <a href="events/list.php" class="btn btn-sm btn-outline-primary">Все мероприятия</a>
        </div>

        <?php if (empty($events)): ?>
            <!-- Заглушка: нет мероприятий -->
            <div class="text-center py-5">
                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;" aria-hidden="true"></i>
                <p class="text-muted mt-3">У вас пока нет мероприятий.</p>
                <a href="events/create.php" class="btn btn-auth-primary">Создать первое мероприятие</a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($events as $ev): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card event-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title fw-bold mb-0">
                                        <a href="events/view.php?id=<?= $ev['id'] ?>" class="text-decoration-none text-dark">
                                            <?= escape($ev['title']) ?>
                                        </a>
                                    </h5>
                                    <?= eventStatusBadge($ev['status']) ?>
                                </div>
                                <?php if (!empty($ev['start_datetime'])): ?>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                                        <?= escape(date('d.m.Y H:i', strtotime($ev['start_datetime']))) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($ev['location'])): ?>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                                        <?= escape($ev['location']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-list-check me-1" aria-hidden="true"></i>
                                    Активных задач: <?= (int) $ev['active_tasks'] ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <div class="btn-group btn-group-sm w-100">
                                    <a href="events/view.php?id=<?= $ev['id'] ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="tasks.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-list-task" aria-hidden="true"></i>
                                    </a>
                                    <a href="comments.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Футер -->
    <footer class="auth-footer">
        <div class="container">
            &copy; 2026 EventPlanner — Дипломный проект
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
