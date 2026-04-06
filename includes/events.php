<?php
/**
 * Файл: includes/events.php
 * Описание: CRUD-операции для мероприятий (events).
 *           Все запросы — подготовленные выражения PDO.
 *           Проверка прав доступа: только создатель или организатор.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

require_once __DIR__ . '/functions.php';

/* ======================================================================
   Создание мероприятия
   ====================================================================== */

/**
 * Создаёт новое мероприятие.
 *
 * @param PDO $pdo
 * @param int    $creatorId   ID создателя
 * @param string $title       Название
 * @param string $description Описание
 * @param string $startDt     Начало (ISO 8601)
 * @param string $endDt       Окончание (ISO 8601)
 * @param string $location    Место проведения
 * @param string $status      Статус (draft, active, completed, cancelled)
 * @return array ['success' => bool, 'error' => string|null, 'id' => int|null]
 */
function createEvent(
    PDO $pdo, int $creatorId, string $title, string $description,
    string $startDt, string $endDt, string $location, string $status
): array {
    /* Валидация обязательного поля */
    if (trim($title) === '') {
        return ['success' => false, 'error' => 'Название мероприятия обязательно.', 'id' => null];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO events (creator_id, title, description, start_datetime, end_datetime, location, status)
             VALUES (:creator_id, :title, :description, :start_datetime, :end_datetime, :location, :status)'
        );
        $stmt->execute([
            ':creator_id'    => $creatorId,
            ':title'         => trim($title),
            ':description'   => $description !== '' ? trim($description) : null,
            ':start_datetime'=> $startDt !== '' ? $startDt : null,
            ':end_datetime'  => $endDt !== '' ? $endDt : null,
            ':location'      => $location !== '' ? trim($location) : null,
            ':status'        => $status,
        ]);

        $eventId = (int) $pdo->lastInsertId();
        return ['success' => true, 'error' => null, 'id' => $eventId];
    } catch (PDOException $e) {
        error_log('Ошибка создания мероприятия: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при создании мероприятия.', 'id' => null];
    }
}

/* ======================================================================
   Получение мероприятия по ID
   ====================================================================== */

/**
 * Возвращает одно мероприятие с информацией о создателе.
 *
 * @param PDO $pdo
 * @param int $eventId
 * @return array|false
 */
function getEvent(PDO $pdo, int $eventId)
{
    try {
        $stmt = $pdo->prepare(
            'SELECT e.*, u.username AS creator_name
             FROM events e
             LEFT JOIN users u ON e.creator_id = u.id
             WHERE e.id = :id'
        );
        $stmt->execute([':id' => $eventId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Ошибка получения мероприятия: ' . $e->getMessage());
        return false;
    }
}

/* ======================================================================
   Обновление мероприятия
   ====================================================================== */

/**
 * Обновляет данные мероприятия.
 *
 * @param PDO $pdo
 * @param int    $eventId     ID мероприятия
 * @param int    $userId      ID пользователя (для проверки прав)
 * @param string $title
 * @param string $description
 * @param string $startDt
 * @param string $endDt
 * @param string $location
 * @param string $status
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateEvent(
    PDO $pdo, int $eventId, int $userId, string $title, string $description,
    string $startDt, string $endDt, string $location, string $status
): array {
    if (trim($title) === '') {
        return ['success' => false, 'error' => 'Название мероприятия обязательно.'];
    }

    /* Проверка прав доступа */
    if (!canManageEvent($pdo, $eventId, $userId)) {
        return ['success' => false, 'error' => 'Недостаточно прав для редактирования.'];
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE events SET title = :title, description = :description,
                              start_datetime = :start_datetime, end_datetime = :end_datetime,
                              location = :location, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            ':title'         => trim($title),
            ':description'   => $description !== '' ? trim($description) : null,
            ':start_datetime'=> $startDt !== '' ? $startDt : null,
            ':end_datetime'  => $endDt !== '' ? $endDt : null,
            ':location'      => $location !== '' ? trim($location) : null,
            ':status'        => $status,
            ':id'            => $eventId,
        ]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка обновления мероприятия: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при обновлении мероприятия.'];
    }
}

/* ======================================================================
   Удаление мероприятия (мягкое удаление — изменение статуса)
   ====================================================================== */

/**
 * Мягко удаляет мероприятие, меняя статус на «cancelled».
 *
 * @param PDO $pdo
 * @param int $eventId
 * @param int $userId
 * @return array
 */
function deleteEvent(PDO $pdo, int $eventId, int $userId): array
{
    if (!canManageEvent($pdo, $eventId, $userId)) {
        return ['success' => false, 'error' => 'Недостаточно прав для удаления.'];
    }

    try {
        $stmt = $pdo->prepare('UPDATE events SET status = :status WHERE id = :id');
        $stmt->execute([':status' => 'cancelled', ':id' => $eventId]);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка удаления мероприятия: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при удалении.'];
    }
}

/* ======================================================================
   Список мероприятий пользователя с поиском и фильтрацией
   ====================================================================== */

/**
 * Возвращает список мероприятий с пагинацией, поиском и фильтрацией.
 *
 * @param PDO   $pdo
 * @param int   $userId    ID текущего пользователя
 * @param array $filters   ['q' => строка, 'status' => массив, 'date_from' => дата, 'date_to' => дата]
 * @param int   $page      Номер страницы (1-based)
 * @param int   $perPage   Записей на странице
 * @return array ['events' => array, 'total' => int, 'page' => int, 'pages' => int]
 */
function getEventsList(PDO $pdo, int $userId, array $filters = [], int $page = 1, int $perPage = 10): array
{
    /* Базовый запрос: все мероприятия, где пользователь — создатель или участник */
    $whereParts   = [];
    $params       = [':user_id' => $userId];
    $bindCounter  = 0;

    /* Условие принадлежности */
    $whereParts[] = '(e.creator_id = :user_id OR ep.user_id = :user_id)';

    /* Поиск по названию */
    if (!empty($filters['q'])) {
        $whereParts[] = 'e.title LIKE :q';
        $params[':q'] = '%' . trim($filters['q']) . '%';
    }

    /* Фильтр по статусу */
    if (!empty($filters['status']) && is_array($filters['status'])) {
        $statusPlaceholders = [];
        foreach ($filters['status'] as $i => $s) {
            $key = ':status_' . $i;
            $statusPlaceholders[] = $key;
            $params[$key] = $s;
        }
        $whereParts[] = 'e.status IN (' . implode(', ', $statusPlaceholders) . ')';
    }

    /* Фильтр по дате */
    if (!empty($filters['date_from'])) {
        $whereParts[] = 'e.start_datetime >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $whereParts[] = 'e.start_datetime <= :date_to';
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }

    $whereSQL = implode(' AND ', $whereParts);

    /* Подсчёт общего количества */
    $countSQL  = "SELECT COUNT(DISTINCT e.id) FROM events e
                  LEFT JOIN event_participants ep ON ep.event_id = e.id
                  WHERE $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $total     = (int) $countStmt->fetchColumn();

    /* Постраничный вывод */
    $page   = max(1, $page);
    $pages  = max(1, (int) ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $selectSQL = "SELECT e.*, u.username AS creator_name,
                         (SELECT COUNT(*) FROM tasks t WHERE t.event_id = e.id AND t.is_done = 0) AS active_tasks
                  FROM events e
                  LEFT JOIN event_participants ep ON ep.event_id = e.id
                  LEFT JOIN users u ON e.creator_id = u.id
                  WHERE $whereSQL
                  GROUP BY e.id
                  ORDER BY e.start_datetime DESC
                  LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($selectSQL);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'events' => $stmt->fetchAll(),
        'total'  => $total,
        'page'   => $page,
        'pages'  => $pages,
    ];
}

/* ======================================================================
   Проверка прав доступа к мероприятию
   ====================================================================== */

/**
 * Проверяет, может ли пользователь управлять мероприятием
 * (редактировать, удалять, менять участников).
 *
 * @param PDO $pdo
 * @param int $eventId
 * @param int $userId
 * @return bool
 */
function canManageEvent(PDO $pdo, int $eventId, int $userId): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT e.creator_id, ep.role
             FROM events e
             LEFT JOIN event_participants ep ON ep.event_id = e.id AND ep.user_id = :user_id
             WHERE e.id = :event_id'
        );
        $stmt->execute([':user_id' => $userId, ':event_id' => $eventId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        /* Создатель всегда может управлять */
        if ((int) $row['creator_id'] === $userId) {
            return true;
        }

        /* Организатор тоже может управлять */
        return $row['role'] === 'organizer';
    } catch (PDOException $e) {
        error_log('Ошибка проверки прав: ' . $e->getMessage());
        return false;
    }
}

/* ======================================================================
   Статус-бейдж для мероприятия
   ====================================================================== */

/**
 * Возвращает HTML-бейдж статуса мероприятия.
 *
 * @param string $status
 * @return string
 */
function eventStatusBadge(string $status): string
{
    $map = [
        'draft'     => ['label' => 'Черновик',      'class' => 'bg-secondary'],
        'active'    => ['label' => 'Активно',        'class' => 'bg-success'],
        'completed' => ['label' => 'Завершено',      'class' => 'bg-primary'],
        'cancelled' => ['label' => 'Отменено',       'class' => 'bg-danger'],
    ];

    $info = $map[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
    return '<span class="badge ' . $info['class'] . '">' . escape($info['label']) . '</span>';
}
