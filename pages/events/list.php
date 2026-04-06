<?php
/**
 * Файл: pages/events/list.php
 * Описание: Список мероприятий пользователя с поиском, фильтрацией
 *           и постраничной навигацией.
 *           Фильтры через GET-параметры: q (поиск), status[], date_from, date_to.
 *           Динамическое формирование WHERE-условий (LIKE, BETWEEN, IN).
 *           Пагинация через LIMIT/OFFSET с сохранением фильтров в URL.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_once __DIR__ . '/../../includes/db.php';

checkAuth();

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Пользователь';

/* ===== Считываем фильтры из GET ===== */
$filters = [];

/* Поиск по названию */
if (!empty($_GET['q'])) {
    $filters['q'] = trim($_GET['q']);
}

/* Фильтр по статусу (массив) */
if (!empty($_GET['status']) && is_array($_GET['status'])) {
    $allowedStatuses = ['draft', 'active', 'completed', 'cancelled'];
    $filters['status'] = array_intersect($_GET['status'], $allowedStatuses);
}

/* Фильтр по дате «от» */
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

/* Фильтр по дате «до» */
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

/* Пагинация */
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 8;

/* Получаем данные */
$eventsData = getEventsList($pdo, $userId, $filters, $page, $perPage);
$events     = $eventsData['events'];
$totalPages = $eventsData['pages'];

/* ===== Формирование URL для пагинации (сохранение фильтров) ===== */
function buildFilterUrl(array $params = []): string
{
    $base = 'list.php';
    $qs   = $_GET;

    /* Убираем page из текущих параметров — подставим новый */
    unset($qs['page']);

    /* Перезаписываем переданные параметры */
    foreach ($params as $key => $val) {
        if ($val === null || $val === '') {
            unset($qs[$key]);
        } else {
            $qs[$key] = $val;
        }
    }

    return $base . '?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мероприятия — EventPlanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="../../assets/style.css" rel="stylesheet">
    <!-- Стили печати -->
    <link href="../../assets/print.css" rel="stylesheet" media="print">
</head>
<body>

    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg shadow-sm bg-white print-hide" aria-label="Навигация">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard.php" style="color: var(--indigo);">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>EventPlanner
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="list.php" class="nav-link active small fw-bold">Мероприятия</a>
                <span class="text-muted small">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <?= escape($username) ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm" role="button">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Заголовок + кнопки -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h2 class="fw-bold mb-0">
                <i class="bi bi-journal-text me-2" aria-hidden="true"></i>Мероприятия
            </h2>
            <div class="d-flex gap-2">
                <a href="create.php" class="btn btn-auth-primary btn-sm" role="button">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Создать
                </a>
                <button class="btn btn-outline-secondary btn-sm print-hide" onclick="window.print()">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Печать
                </button>
            </div>
        </div>

        <!-- ===== Фильтры ===== -->
        <form method="get" action="list.php" class="card border-0 shadow-sm mb-4 print-hide">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <!-- Поиск -->
                    <div class="col-md-4">
                        <label for="q" class="form-label small fw-bold">Поиск</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="q" name="q"
                                   placeholder="По названию..."
                                   value="<?= isset($filters['q']) ? escape($filters['q']) : '' ?>">
                        </div>
                    </div>

                    <!-- Статус -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Статус</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="draft" id="fs_draft"
                                       <?= in_array('draft', $filters['status'] ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fs_draft">Черновик</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="active" id="fs_active"
                                       <?= in_array('active', $filters['status'] ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fs_active">Активно</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="completed" id="fs_completed"
                                       <?= in_array('completed', $filters['status'] ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fs_completed">Завершено</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status[]" value="cancelled" id="fs_cancelled"
                                       <?= in_array('cancelled', $filters['status'] ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fs_cancelled">Отменено</label>
                            </div>
                        </div>
                    </div>

                    <!-- Дата от -->
                    <div class="col-md-2">
                        <label for="date_from" class="form-label small fw-bold">Дата от</label>
                        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                               value="<?= $filters['date_from'] ?? '' ?>">
                    </div>

                    <!-- Дата до -->
                    <div class="col-md-2">
                        <label for="date_to" class="form-label small fw-bold">Дата до</label>
                        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                               value="<?= $filters['date_to'] ?? '' ?>">
                    </div>

                    <!-- Кнопки -->
                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-auth-primary btn-sm">
                            <i class="bi bi-funnel" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- Сброс фильтров -->
                <?php if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    <div class="mt-2">
                        <a href="list.php" class="small text-muted">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Сбросить фильтры
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- ===== Таблица мероприятий ===== -->
        <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;" aria-hidden="true"></i>
                <p class="text-muted mt-3">Мероприятия не найдены.</p>
                <a href="create.php" class="btn btn-auth-primary btn-sm">Создать мероприятие</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Название</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Дата</th>
                            <th scope="col">Место</th>
                            <th scope="col">Задачи</th>
                            <th scope="col" class="text-end print-hide">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $ev['id'] ?>" class="text-decoration-none fw-bold">
                                        <?= escape($ev['title']) ?>
                                    </a>
                                </td>
                                <td><?= eventStatusBadge($ev['status']) ?></td>
                                <td class="text-nowrap">
                                    <?= $ev['start_datetime'] ? escape(date('d.m.Y', strtotime($ev['start_datetime']))) : '—' ?>
                                </td>
                                <td><?= $ev['location'] ? escape($ev['location']) : '—' ?></td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= (int) $ev['active_tasks'] ?></span>
                                </td>
                                <td class="text-end print-hide">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?= $ev['id'] ?>" class="btn btn-outline-primary" title="Просмотр">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <a href="../tasks.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline-secondary" title="Задачи">
                                            <i class="bi bi-list-task" aria-hidden="true"></i>
                                        </a>
                                        <a href="../comments.php?event_id=<?= $ev['id'] ?>" class="btn btn-outline-secondary" title="Комментарии">
                                            <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ===== Пагинация ===== -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Пагинация мероприятий">
                    <ul class="pagination justify-content-center">
                        <?php
                        /* Предыдущая страница */
                        if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildFilterUrl(['page' => $page - 1]) ?>" aria-label="Предыдущая">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        /* Номера страниц (показываем максимум 5) */
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $startPage + 4);
                        $startPage = max(1, $endPage - 4);

                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildFilterUrl(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        /* Следующая страница */
                        if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildFilterUrl(['page' => $page + 1]) ?>" aria-label="Следующая">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <p class="text-center text-muted small">
                    Страница <?= $page ?> из <?= $totalPages ?> (всего: <?= $eventsData['total'] ?>)
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="auth-footer print-hide">
        <div class="container">&copy; 2026 EventPlanner — Дипломный проект</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
