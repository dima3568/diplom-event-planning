<?php
/**
 * Файл: pages/admin.php
 * Описание: Админ-панель — просмотр и управление всеми таблицами БД.
 *           Доступна только пользователю с логином «admin».
 *           Функции: просмотр таблиц, добавление/редактирование/удаление записей,
 *           фильтрация по полям, сортировка по столбцам, поиск.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

session_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

checkAuth();

/* Проверка: только admin имеет доступ */
if ($_SESSION['username'] !== 'admin') {
    redirect('dashboard.php');
}

$username = $_SESSION['username'];
$error    = '';
$success  = '';

/* ===== Доступные таблицы ===== */
$tables = [
    'users'              => 'Пользователи',
    'events'             => 'Мероприятия',
    'event_participants' => 'Участники',
    'tasks'              => 'Задачи',
    'comments'           => 'Комментарии',
    'activity_log'       => 'Журнал активности',
];

/* ===== Текущая таблица ===== */
$currentTable = $_GET['table'] ?? 'users';
if (!array_key_exists($currentTable, $tables)) {
    $currentTable = 'users';
}

/* ===== Фильтры и сортировка из GET ===== */
$filterField = $_GET['filter_field'] ?? '';
$filterValue = $_GET['filter_value'] ?? '';
$sortField   = $_GET['sort'] ?? '';
$sortDir     = ($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$search      = $_GET['search'] ?? '';

/* ===== Обработка POST-запросов (добавление/редактирование/удаление) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ошибка безопасности.';
    } else {
        $action = $_POST['action'] ?? '';

        /* Удаление записи */
        if ($action === 'delete') {
            $deleteId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
            if ($deleteId > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM $currentTable WHERE id = :id");
                    $stmt->execute([':id' => $deleteId]);
                    $success = 'Запись удалена.';
                } catch (PDOException $e) {
                    error_log('Ошибка удаления: ' . $e->getMessage());
                    $error = 'Ошибка при удалении: ' . (strpos($e->getMessage(), 'FOREIGN KEY') !== false
                        ? 'Запись используется в связанных таблицах.' : 'Произошла ошибка.');
                }
            }
            redirect('admin.php?table=' . $currentTable);
        }

        /* Добавление/редактирование записи */
        if ($action === 'save') {
            $recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
            $fields   = $_POST['fields'] ?? [];

            /* Убираем пустые значения */
            $cleanFields = [];
            foreach ($fields as $key => $val) {
                if ($key === 'id') continue;
                $cleanFields[$key] = $val === '' ? null : $val;
            }

            try {
                if ($recordId > 0) {
                    /* Обновление */
                    $setParts = [];
                    $params   = [];
                    foreach ($cleanFields as $key => $val) {
                        $setParts[]  = "$key = :$key";
                        $params[":$key"] = $val;
                    }
                    $params[':id'] = $recordId;
                    $sql = "UPDATE $currentTable SET " . implode(', ', $setParts) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = 'Запись обновлена.';
                } else {
                    /* Вставка */
                    $columns = array_keys($cleanFields);
                    $placeholders = array_map(fn($c) => ":$c", $columns);
                    $sql = "INSERT INTO $currentTable (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($cleanFields);
                    $success = 'Запись добавлена.';
                }
            } catch (PDOException $e) {
                error_log('Ошибка сохранения: ' . $e->getMessage());
                $error = 'Ошибка при сохранении: ' . (strpos($e->getMessage(), 'UNIQUE') !== false
                    ? 'Нарушено ограничение уникальности.' : 'Произошла ошибка.');
            }
            redirect('admin.php?table=' . $currentTable);
        }
    }
}

/* ===== Получаем структуру таблицы ===== */
try {
    $stmt = $pdo->query("PRAGMA table_info($currentTable)");
    $columns = $stmt->fetchAll();
} catch (PDOException $e) {
    $columns = [];
}

/* Колонка ID для каждой таблицы */
$idColumn = 'id';
foreach ($columns as $col) {
    if ((int) $col['pk'] === 1) {
        $idColumn = $col['name'];
        break;
    }
}

/* ===== Формируем запрос с фильтрами и сортировкой ===== */
$whereParts = [];
$params     = [];

/* Поиск по всем текстовым полям */
if ($search !== '') {
    $textColumns = array_filter($columns, fn($c) => in_array(strtolower($c['type']), ['text', 'varchar']));
    if (!empty($textColumns)) {
        $searchConditions = [];
        foreach ($textColumns as $col) {
            $searchConditions[] = $col['name'] . ' LIKE :search';
        }
        $whereParts[] = '(' . implode(' OR ', $searchConditions) . ')';
        $params[':search'] = '%' . $search . '%';
    }
}

/* Фильтр по конкретному полю */
if ($filterField !== '' && $filterValue !== '') {
    $whereParts[] = "$filterField = :filter_val";
    $params[':filter_val'] = $filterValue;
}

$whereSQL = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

/* Сортировка */
$orderSQL = '';
if ($sortField !== '' && in_array($sortField, array_column($columns, 'name'))) {
    $orderSQL = " ORDER BY $sortField $sortDir";
}

/* Получаем данные */
try {
    $stmt = $pdo->prepare("SELECT * FROM $currentTable $whereSQL $orderSQL");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    $records = [];
}

/* Значения для формы редактирования */
$editRecord = null;
if (isset($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM $currentTable WHERE $idColumn = :id");
        $stmt->execute([':id' => $editId]);
        $editRecord = $stmt->fetch();
    } catch (PDOException $e) {
        // ignore
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — EventPlanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .admin-sidebar { min-height: calc(100vh - 56px); background: var(--light-gray); }
        .admin-sidebar .list-group-item { border: none; border-radius: 8px !important; margin-bottom: 2px; }
        .admin-sidebar .list-group-item.active { background: var(--gradient); color: #fff; }
        .table-admin td, .table-admin th { font-size: 0.85rem; vertical-align: middle; }
        .table-admin th { white-space: nowrap; }
        .table-admin td { max-width: 200px; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>

    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg shadow-sm bg-white" aria-label="Навигация">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php" style="color: var(--indigo);">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>EventPlanner
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-danger"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Админ</span>
                <span class="text-muted small"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= escape($username) ?></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm" role="button">Выйти</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- ===== Боковое меню: таблицы ===== -->
            <div class="col-md-2 admin-sidebar py-3 px-2 d-none d-md-block">
                <h6 class="fw-bold text-muted px-2 mb-3">Таблицы БД</h6>
                <div class="list-group">
                    <?php foreach ($tables as $key => $label): ?>
                        <a href="admin.php?table=<?= $key ?>"
                           class="list-group-item list-group-item-action <?= $key === $currentTable ? 'active' : '' ?>">
                            <i class="bi bi-table me-1" aria-hidden="true"></i>
                            <?= escape($label) ?>
                            <span class="badge bg-light text-dark float-end"><?= $key === $currentTable ? count($records) : '' ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== Мобильное меню таблиц ===== -->
            <div class="col-12 d-md-none py-2 px-3 bg-light">
                <select class="form-select" onchange="location.href='admin.php?table='+this.value">
                    <?php foreach ($tables as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === $currentTable ? 'selected' : '' ?>><?= escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ===== Основная область ===== -->
            <div class="col-md-10 py-3 px-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h4 class="fw-bold mb-0">
                        <i class="bi bi-database me-2" aria-hidden="true"></i><?= escape($tables[$currentTable]) ?>
                    </h4>
                    <div class="d-flex gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm" role="button">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>На панель
                        </a>
                    </div>
                </div>

                <!-- Уведомления -->
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

                <!-- Поиск и фильтры -->
                <form method="get" action="admin.php" class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-2">
                        <input type="hidden" name="table" value="<?= $currentTable ?>">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Поиск…"
                                           value="<?= escape($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" name="filter_field">
                                    <option value="">— Фильтр по полю —</option>
                                    <?php foreach ($columns as $col): ?>
                                        <option value="<?= escape($col['name']) ?>"
                                                <?= $filterField === $col['name'] ? 'selected' : '' ?>>
                                            <?= escape($col['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="filter_value"
                                       placeholder="Значение" value="<?= escape($filterValue) ?>">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-auth-primary btn-sm">Применить</button>
                            </div>
                        </div>
                        <?php if ($search !== '' || $filterField !== ''): ?>
                            <div class="mt-1">
                                <a href="admin.php?table=<?= $currentTable ?>" class="small text-muted">
                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Сбросить
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Форма добавления/редактирования -->
                <?php if ($editRecord || isset($_GET['new'])): ?>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-bold">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                            <?= $editRecord ? 'Редактирование записи #' . $editRecord[$idColumn] : 'Новая запись' ?>
                        </div>
                        <div class="card-body">
                            <form method="post" action="admin.php?table=<?= $currentTable ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="record_id" value="<?= $editRecord ? $editRecord[$idColumn] : '' ?>">
                                <div class="row g-2">
                                    <?php foreach ($columns as $col): ?>
                                        <?php if ($col['name'] === $idColumn && !$editRecord) continue; /* автоинкремент */ ?>
                                        <div class="col-md-4 col-lg-3">
                                            <label class="form-label small fw-bold"><?= escape($col['name']) ?></label>
                                            <?php
                                                $val = $editRecord[$col['name']] ?? '';
                                                $isPK = (int) $col['pk'] === 1;
                                                $isNullable = (int) $col['notnull'] === 0;
                                            ?>
                                            <?php if ($isPK): ?>
                                                <input type="text" class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]"
                                                       value="<?= escape($val) ?>" readonly>
                                            <?php elseif (in_array(strtolower($col['type']), ['text', 'varchar'])): ?>
                                                <?php if (strlen($val) > 100 || strpos($col['name'], 'description') !== false || strpos($col['name'], 'text') !== false || strpos($col['name'], 'details') !== false): ?>
                                                    <textarea class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]" rows="2"
                                                              <?= !$isNullable ? 'required' : '' ?>><?= escape($val) ?></textarea>
                                                <?php else: ?>
                                                    <input type="text" class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]"
                                                           value="<?= escape($val) ?>" maxlength="500" <?= !$isNullable && $val === '' ? 'required' : '' ?>>
                                                <?php endif; ?>
                                            <?php elseif (strtolower($col['type']) === 'integer'): ?>
                                                <input type="number" class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]"
                                                       value="<?= escape($val) ?>" <?= !$isNullable && $val === '' ? 'required' : '' ?>>
                                            <?php elseif (strtolower($col['type']) === 'datetime'): ?>
                                                <input type="datetime-local" class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]"
                                                       value="<?= $val ? date('Y-m-d\TH:i', strtotime($val)) : '' ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control form-control-sm" name="fields[<?= $col['name'] ?>]"
                                                       value="<?= escape($val) ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-auth-primary btn-sm">
                                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Сохранить
                                    </button>
                                    <a href="admin.php?table=<?= $currentTable ?>" class="btn btn-outline-secondary btn-sm">Отмена</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Кнопка добавления -->
                <?php if (!$editRecord && !isset($_GET['new'])): ?>
                    <a href="admin.php?table=<?= $currentTable ?>&new=1" class="btn btn-auth-primary btn-sm mb-3">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Добавить запись
                    </a>
                <?php endif; ?>

                <!-- Таблица данных -->
                <div class="table-responsive">
                    <table class="table table-hover table-admin table-bordered">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <th>
                                        <a href="admin.php?table=<?= $currentTable ?>&sort=<?= escape($col['name']) ?>&dir=<?= $sortField === $col['name'] && $sortDir === 'ASC' ? 'DESC' : 'ASC' ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $filterField !== '' ? '&filter_field=' . urlencode($filterField) . '&filter_value=' . urlencode($filterValue) : '' ?>"
                                           class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                            <?= escape($col['name']) ?>
                                            <?php if ($sortField === $col['name']): ?>
                                                <i class="bi bi-chevron-<?= $sortDir === 'ASC' ? 'up' : 'down' ?>" style="font-size:0.7rem;"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                                <th class="text-end">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="<?= count($columns) + 1 ?>" class="text-center text-muted py-4">
                                        Нет записей
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <td title="<?= escape((string) $row[$col['name']]) ?>">
                                                <?php
                                                    $val = $row[$col['name']];
                                                    if ($val === null || $val === '') {
                                                        echo '<span class="text-muted">—</span>';
                                                    } elseif (strlen((string) $val) > 50) {
                                                        echo escape(mb_substr((string) $val, 0, 50)) . '…';
                                                    } else {
                                                        echo escape((string) $val);
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <a href="admin.php?table=<?= $currentTable ?>&edit_id=<?= $row[$idColumn] ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $filterField !== '' ? '&filter_field=' . urlencode($filterField) . '&filter_value=' . urlencode($filterValue) : '' ?><?= $sortField !== '' ? '&sort=' . urlencode($sortField) . '&dir=' . $sortDir : '' ?>"
                                                   class="btn btn-outline-primary btn-sm" title="Редактировать">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                                <form method="post" action="admin.php?table=<?= $currentTable ?>" style="display:inline;"
                                                      onsubmit="return confirm('Удалить запись #<?= $row[$idColumn] ?>?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="record_id" value="<?= $row[$idColumn] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Удалить">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="text-muted small">Всего записей: <?= count($records) ?></p>
            </div>
        </div>
    </div>

    <footer class="auth-footer">
        <div class="container">&copy; 2026 EventPlanner — Админ-панель</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
