<?php
/**
 * Файл: includes/log.php
 * Описание: Система логирования действий пользователей.
 *           Записывает каждое значимое действие (создание, обновление,
 *           удаление событий/задач/комментариев) в таблицу activity_log.
 *           Лог используется для временной шкалы на странице просмотра мероприятия.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

/**
 * Записывает действие пользователя в журнал активности.
 *
 * @param PDO    $pdo        PDO-подключение к БД
 * @param int    $userId     ID пользователя, совершившего действие
 * @param string $action     Тип действия (create, update, delete, join, leave и т.д.)
 * @param string $entityType Тип сущности (event, task, comment, participant)
 * @param int    $entityId   ID сущности (NULL если неприменимо)
 * @param string $details    Дополнительные подробности (JSON-строка или текст)
 * @return bool
 */
function logAction(
    PDO $pdo,
    int $userId,
    string $action,
    string $entityType,
    ?int $entityId,
    string $details = ''
): bool {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':details'     => $details !== '' ? $details : null,
        ]);
        return true;
    } catch (PDOException $e) {
        /* Логируем ошибку, но не прерываем работу — логирование не критично */
        error_log('Ошибка логирования: ' . $e->getMessage());
        return false;
    }
}

/**
 * Возвращает записи лога для конкретного мероприятия, отсортированные по дате.
 *
 * @param PDO  $pdo
 * @param int  $eventId  ID мероприятия
 * @param int  $limit    Максимальное число записей (по умолчанию 50)
 * @return array
 */
function getEventLog(PDO $pdo, int $eventId, int $limit = 50): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT al.*, u.username
             FROM activity_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.entity_type = :entity_type AND al.entity_id = :entity_id
             ORDER BY al.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':entity_type', 'event', PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Ошибка получения лога: ' . $e->getMessage());
        return [];
    }
}
