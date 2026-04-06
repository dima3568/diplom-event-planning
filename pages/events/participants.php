<?php
/**
 * Файл: pages/events/participants.php
 * Описание: Управление участниками мероприятия.
 *           Поиск пользователей по логину, добавление с ролью,
 *           вывод таблицы участников с иконками ролей Bootstrap Icons,
 *           защита от изменения списка не-организатором,
 *           логирование каждого добавления/удаления.
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

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
if ($eventId <= 0) {
    redirect('../dashboard.php');
}

$event = getEvent($pdo, $eventId);
if (!$event) {
    redirect('../dashboard.php');
}

/* Проверка прав — только организатор или создатель */
if (!canManageEvent($pdo, $eventId, $userId)) {
    redirect('view.php?id=' . $eventId);
}

$error   = '';
$success = '';

/* ===== Добавление участника ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности.';
    } else {
        $targetUsername = trim($_POST['target_username'] ?? '');
        $role           = $_POST['role'] ?? 'participant';

        if ($targetUsername === '') {
            $error = 'Введите логин пользователя.';
        } elseif (!in_array($role, ['participant', 'organizer'])) {
            $error = 'Некорректная роль.';
        } else {
            try {
                /* Ищем пользователя по логину */
                $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = :username LIMIT 1');
                $stmt->execute([':username' => $targetUsername]);
                $targetUser = $stmt->fetch();

                if (!$targetUser) {
                    $error = 'Пользователь «' . escape($targetUsername) . '» не найден.';
                } elseif ((int) $targetUser['id'] === $userId) {
                    $error = 'Нельзя добавить себя.';
                } else {
                    /* Проверяем, не участник ли уже */
                    $stmt = $pdo->prepare('SELECT 1 FROM event_participants WHERE event_id = :eid AND user_id = :uid');
                    $stmt->execute([':eid' => $eventId, ':uid' => $targetUser['id']]);

                    if ($stmt->fetch()) {
                        $error = 'Пользователь «' . escape($targetUser['username']) . '» уже является участником.';
                    } else {
                        /* Добавляем участника */
                        $stmt = $pdo->prepare(
                            'INSERT INTO event_participants (event_id, user_id, role) VALUES (:eid, :uid, :role)'
                        );
                        $stmt->execute([':eid' => $eventId, ':uid' => $targetUser['id'], ':role' => $role]);

                        /* Логирование */
                        logAction($pdo, $userId, 'add_participant', 'participant', $eventId,
                                  'Добавлен участник: ' . $targetUser['username'] . ' (роль: ' . $role . ')');

                        $success = 'Пользователь «' . escape($targetUser['username']) . '» добавлен.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Ошибка добавления участника: ' . $e->getMessage());
                $error = 'Произошла ошибка при добавлении участника.';
            }
        }
    }
}

/* ===== Удаление участника ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $removeUid = isset($_POST['remove_user_id']) ? (int) $_POST['remove_user_id'] : 0;

    if (!verifyCsrfToken($csrfToken) || $removeUid <= 0) {
        $error = 'Ошибка безопасности.';
    } elseif ($removeUid === $userId) {
        $error = 'Нельзя удалить себя из участников.';
    } else {
        try {
            /* Получаем имя удаляемого для лога */
            $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
            $stmt->execute([':id' => $removeUid]);
            $removedUser = $stmt->fetch();

            $stmt = $pdo->prepare('DELETE FROM event_participants WHERE event_id = :eid AND user_id = :uid');
            $stmt->execute([':eid' => $eventId, ':uid' => $removeUid]);

            if ($stmt->rowCount() > 0 && $removedUser) {
                logAction($pdo, $userId, 'remove_participant', 'participant', $eventId,
                          'Удалён участник: ' . $removedUser['username']);
                $success = 'Участник «' . escape($removedUser['username']) . '» удалён.';
            }
        } catch (PDOException $e) {
            error_log('Ошибка удаления участника: ' . $e->getMessage());
            $error = 'Произошла ошибка при удалении участника.';
        }
    }
}

/* ===== Получаем список участников ===== */
try {
    $stmt = $pdo->prepare(
        'SELECT ep.*, u.username
         FROM event_participants ep
         INNER JOIN users u ON ep.user_id = u.id
         WHERE ep.event_id = :eid
         ORDER BY ep.role DESC, u.username ASC'
    );
    $stmt->execute([':eid' => $eventId]);
    $participants = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Ошибка получения участников: ' . $e->getMessage());
    $participants = [];
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Участники — <?= escape($event['title']) ?></title>
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
                <li class="breadcrumb-item active">Участники</li>
            </ol>
        </nav>

        <h3 class="fw-bold mb-4">
            <i class="bi bi-people me-2" aria-hidden="true"></i>Участники мероприятия
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

        <div class="row g-4">
            <!-- Форма добавления -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Добавить участника
                    </div>
                    <div class="card-body">
                        <form method="post" action="participants.php?event_id=<?= $eventId ?>" novalidate>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add">

                            <div class="mb-3">
                                <label for="target_username" class="form-label">Логин пользователя</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="target_username" name="target_username"
                                           placeholder="Введите логин" required autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Роль</label>
                                <select class="form-select form-select-sm" id="role" name="role">
                                    <option value="participant">Участник</option>
                                    <option value="organizer">Организатор</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-auth-primary btn-sm w-100">
                                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Добавить
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Таблица участников -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-people me-1" aria-hidden="true"></i>Список участников (<?= count($participants) ?>)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Участник</th>
                                    <th>Роль</th>
                                    <th class="text-end">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $p): ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-person-circle me-1 text-muted" aria-hidden="true"></i>
                                            <?= escape($p['username']) ?>
                                            <?php if ((int) $p['user_id'] === (int) $event['creator_id']): ?>
                                                <span class="badge bg-primary ms-1">Создатель</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['role'] === 'organizer'): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-star me-1" aria-hidden="true"></i>Организатор
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-person me-1" aria-hidden="true"></i>Участник
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ((int) $p['user_id'] !== $userId && (int) $p['user_id'] !== (int) $event['creator_id']): ?>
                                                <form method="post" action="participants.php?event_id=<?= $eventId ?>" style="display:inline;"
                                                      onsubmit="return confirm('Удалить участника <?= escape($p['username']) ?>?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="remove_user_id" value="<?= $p['user_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a href="view.php?id=<?= $eventId ?>" class="btn btn-outline-secondary btn-sm">
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
