<?php
/**
 * Файл: includes/tasks.php
 * Описание: CRUD-операции для задач (tasks) внутри мероприятий.
 *           Привязка к event_id, назначение исполнителя из участников,
 *           быстрое переключение is_done, сортировка по deadline.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/events.php';

/* ======================================================================
   Создание задачи
   ====================================================================== */

/**
 * Создаёт новую задачу в мероприятии.
 *
 * @param PDO    $pdo
 * @param int    $eventId    ID мероприятия
 * @param int    $creatorId  ID создателя задачи
 * @param int    $assignedTo ID исполнителя (0 = не назначен)
 * @param string $title      Название задачи
 * @param string $description Описание
 * @param string $deadline   Дедлайн (ISO 8601)
 * @return array ['success' => bool, 'error' => string|null, 'id' => int|null]
 */
function createTask(
    PDO $pdo, int $eventId, int $creatorId, int $assignedTo,
    string $title, string $description, string $deadline
): array {
    if (trim($title) === '') {
        return ['success' => false, 'error' => 'Название задачи обязательно.', 'id' => null];
    }

    /* Проверка, что исполнитель — участник мероприятия (если назначен) */
    if ($assignedTo > 0) {
        if (!isEventParticipant($pdo, $eventId, $assignedTo)) {
            return ['success' => false, 'error' => 'Исполнитель должен быть участником мероприятия.', 'id' => null];
        }
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO tasks (event_id, creator_id, assigned_to, title, description, deadline)
             VALUES (:event_id, :creator_id, :assigned_to, :title, :description, :deadline)'
        );
        $stmt->execute([
            ':event_id'    => $eventId,
            ':creator_id'  => $creatorId,
            ':assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            ':title'       => trim($title),
            ':description' => $description !== '' ? trim($description) : null,
            ':deadline'    => $deadline !== '' ? $deadline : null,
        ]);

        $taskId = (int) $pdo->lastInsertId();
        return ['success' => true, 'error' => null, 'id' => $taskId];
    } catch (PDOException $e) {
        error_log('Ошибка создания задачи: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при создании задачи.', 'id' => null];
    }
}

/* ======================================================================
   Список задач мероприятия
   ====================================================================== */

/**
 * Возвращает задачи мероприятия, отсортированные по дедлайну.
 *
 * @param PDO   $pdo
 * @param int   $eventId
 * @param array $filters ['done' => 'all'|'pending'|'completed', 'sort' => 'deadline'|'creator']
 * @return array
 */
function getTasks(PDO $pdo, int $eventId, array $filters = []): array
{
    $where  = ['event_id = :event_id'];
    $params = [':event_id' => $eventId];

    /* Фильтр по статусу выполнения */
    if (!empty($filters['done']) && $filters['done'] !== 'all') {
        if ($filters['done'] === 'completed') {
            $where[] = 'is_done = 1';
        } elseif ($filters['done'] === 'pending') {
            $where[] = 'is_done = 0';
        }
    }

    /* Сортировка */
    $order = 'deadline ASC, created_at DESC';
    if (!empty($filters['sort']) && $filters['sort'] === 'creator') {
        $order = 'creator_id ASC, deadline ASC';
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT t.*, u.username AS creator_name, a.username AS assigned_name
             FROM tasks t
             LEFT JOIN users u ON t.creator_id = u.id
             LEFT JOIN users a ON t.assigned_to = a.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $order
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Ошибка получения задач: ' . $e->getMessage());
        return [];
    }
}

/* ======================================================================
   Переключение статуса выполнения
   ====================================================================== */

/**
 * Переключает статус задачи (выполнена / не выполнена).
 *
 * @param PDO $pdo
 * @param int $taskId
 * @param int $userId     (для проверки прав)
 * @return array
 */
function toggleTask(PDO $pdo, int $taskId, int $userId): array
{
    try {
        /* Проверяем, что задача принадлежит к мероприятию, где пользователь имеет доступ */
        $stmt = $pdo->prepare(
            'SELECT t.event_id
             FROM tasks t
             WHERE t.id = :id'
        );
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            return ['success' => false, 'error' => 'Задача не найдена.'];
        }

        if (!canManageEvent($pdo, $task['event_id'], $userId) && $userId !== $task['creator_id']) {
            return ['success' => false, 'error' => 'Недостаточно прав.'];
        }

        /* Переключаем is_done */
        $stmt = $pdo->prepare(
            'UPDATE tasks SET is_done = 1 - is_done WHERE id = :id'
        );
        $stmt->execute([':id' => $taskId]);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка переключения задачи: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка.'];
    }
}

/* ======================================================================
   Удаление задачи
   ====================================================================== */

/**
 * Удаляет задачу.
 *
 * @param PDO $pdo
 * @param int $taskId
 * @param int $userId
 * @return array
 */
function deleteTask(PDO $pdo, int $taskId, int $userId): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT t.event_id
             FROM tasks t
             WHERE t.id = :id'
        );
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            return ['success' => false, 'error' => 'Задача не найдена.'];
        }

        if (!canManageEvent($pdo, $task['event_id'], $userId)) {
            return ['success' => false, 'error' => 'Недостаточно прав.'];
        }

        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $taskId]);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка удаления задачи: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при удалении задачи.'];
    }
}

/* ======================================================================
   Обновление задачи
   ====================================================================== */

/**
 * Обновляет данные задачи.
 *
 * @param PDO    $pdo
 * @param int    $taskId
 * @param int    $userId
 * @param string $title
 * @param string $description
 * @param int    $assignedTo
 * @param string $deadline
 * @return array
 */
function updateTask(
    PDO $pdo, int $taskId, int $userId, string $title,
    string $description, int $assignedTo, string $deadline
): array {
    if (trim($title) === '') {
        return ['success' => false, 'error' => 'Название задачи обязательно.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT event_id FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            return ['success' => false, 'error' => 'Задача не найдена.'];
        }

        if (!canManageEvent($pdo, $task['event_id'], $userId)) {
            return ['success' => false, 'error' => 'Недостаточно прав.'];
        }

        /* Если назначен новый исполнитель — проверим, что он участник */
        if ($assignedTo > 0 && !isEventParticipant($pdo, $task['event_id'], $assignedTo)) {
            return ['success' => false, 'error' => 'Исполнитель должен быть участником мероприятия.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE tasks SET title = :title, description = :description,
                              assigned_to = :assigned_to, deadline = :deadline
             WHERE id = :id'
        );
        $stmt->execute([
            ':title'       => trim($title),
            ':description' => $description !== '' ? trim($description) : null,
            ':assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            ':deadline'    => $deadline !== '' ? $deadline : null,
            ':id'          => $taskId,
        ]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка обновления задачи: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при обновлении.'];
    }
}

/* ======================================================================
   Вспомогательная: участник мероприятия?
   ====================================================================== */

/**
 * Проверяет, является ли пользователь участником мероприятия.
 *
 * @param PDO $pdo
 * @param int $eventId
 * @param int $userId
 * @return bool
 */
function isEventParticipant(PDO $pdo, int $eventId, int $userId): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM event_participants WHERE event_id = :event_id AND user_id = :user_id'
        );
        $stmt->execute([':event_id' => $eventId, ':user_id' => $userId]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Ошибка проверки участника: ' . $e->getMessage());
        return false;
    }
}
