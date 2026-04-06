<?php
/**
 * Файл: pages/comments.php
 * Описание: Лента комментариев мероприятия.
 *           Безопасный вывод через htmlspecialchars, хронологический порядок ASC,
 *           ограничение ввода (maxlength 2000), аватар-заглушки через bi-person-circle,
 *           защита от дублирования POST через PRG-паттерн (redirect после добавления).
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/comments.php';
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

/* Проверка: участник */
$isParticipant = isEventParticipant($pdo, $eventId, $userId);
$isCreator     = (int) $event['creator_id'] === $userId;
if (!$isParticipant && !$isCreator) {
    redirect('events/list.php');
}

$success = '';
$error   = '';

/* ===== Удаление комментария ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $csrfToken  = $_POST['csrf_token'] ?? '';
    $commentId  = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

    if (verifyCsrfToken($csrfToken) && $commentId > 0) {
        $result = deleteComment($pdo, $commentId, $userId);
        if ($result['success']) {
            logAction($pdo, $userId, 'delete', 'comment', $commentId, 'Удаление комментария');
        }
        redirect('comments.php?event_id=' . $eventId);
    }
    redirect('comments.php?event_id=' . $eventId);
}

/* ===== Добавление комментария ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности.';
    } else {
        $text = trim($_POST['text'] ?? '');

        if ($text === '') {
            $error = 'Комментарий не может быть пустым.';
        } else {
            $result = addComment($pdo, $eventId, $userId, $text);

            if ($result['success']) {
                logAction($pdo, $userId, 'comment', 'comment', $result['id'], 'Новый комментарий');
                /* PRG-паттерн: редирект на ту же страницу с якорем */
                redirect('comments.php?event_id=' . $eventId . '#comments-end');
            } else {
                $error = $result['error'];
            }
        }
    }
}

/* Получаем комментарии */
$comments = getComments($pdo, $eventId);
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Комментарии — <?= escape($event['title']) ?></title>
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
                <li class="breadcrumb-item active">Комментарии</li>
            </ol>
        </nav>

        <h3 class="fw-bold mb-4">
            <i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Комментарии
            <span class="badge bg-light text-dark"><?= count($comments) ?></span>
        </h3>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= escape($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
            </div>
        <?php endif; ?>

        <!-- Лента комментариев -->
        <div class="mb-4">
            <?php if (empty($comments)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-chat-square-text" style="font-size: 2rem;" aria-hidden="true"></i>
                    <p class="mt-2">Комментариев пока нет. Будьте первым!</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($comments as $c): ?>
                        <div class="list-group-item border-0 px-0 py-3">
                            <div class="d-flex gap-3">
                                <!-- Аватар-заглушка -->
                                <div class="flex-shrink-0">
                                    <i class="bi bi-person-circle text-muted" style="font-size: 2rem;" aria-hidden="true"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong><?= escape($c['username']) ?></strong>
                                        <small class="text-muted"><?= escape(date('d.m.Y H:i', strtotime($c['created_at']))) ?></small>
                                    </div>
                                    <p class="mb-0 mt-1" style="white-space: pre-wrap;"><?= escape($c['text']) ?></p>

                                    <!-- Кнопка удаления (автор или организатор) -->
                                    <?php if ((int) $c['user_id'] === $userId || canManageEvent($pdo, $eventId, $userId)): ?>
                                        <form method="post" action="comments.php?event_id=<?= $eventId ?>" class="mt-2"
                                              onsubmit="return confirm('Удалить комментарий?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" style="font-size: 0.75rem;">
                                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Удалить
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a id="comments-end"></a>
        </div>

        <!-- Форма добавления комментария -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Написать комментарий
            </div>
            <div class="card-body">
                <form method="post" action="comments.php?event_id=<?= $eventId ?>" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="comment_text" class="form-label visually-hidden">Комментарий</label>
                        <textarea class="form-control" id="comment_text" name="text" rows="3"
                                  maxlength="2000" required
                                  placeholder="Введите комментарий..."></textarea>
                        <div class="form-text text-end">
                            <span id="charCount">0</span> / 2000
                        </div>
                    </div>

                    <button type="submit" class="btn btn-auth-primary">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Отправить
                    </button>
                </form>
            </div>
        </div>

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
    <script>
        /* Счётчик символов в textarea */
        var textarea = document.getElementById('comment_text');
        var counter  = document.getElementById('charCount');
        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                counter.textContent = textarea.value.length;
            });
        }
    </script>
</body>
</html>
