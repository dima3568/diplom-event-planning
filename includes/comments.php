<?php
/**
 * Файл: includes/comments.php
 * Описание: CRUD-операции для комментариев к мероприятиям.
 *           Хронологический порядок ASC, безопасный вывод через htmlspecialchars,
 *           защита от дублирования через PRG-паттерн (redirect после POST).
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/events.php';

/* ======================================================================
   Добавление комментария
   ====================================================================== */

/**
 * Добавляет новый комментарий к мероприятию.
 *
 * @param PDO    $pdo
 * @param int    $eventId  ID мероприятия
 * @param int    $userId   ID автора
 * @param string $text     Текст комментария
 * @return array ['success' => bool, 'error' => string|null, 'id' => int|null]
 */
function addComment(PDO $pdo, int $eventId, int $userId, string $text): array
{
    $text = trim($text);

    if ($text === '') {
        return ['success' => false, 'error' => 'Комментарий не может быть пустым.', 'id' => null];
    }

    /* Ограничение длины — 2000 символов */
    if (mb_strlen($text) > 2000) {
        return ['success' => false, 'error' => 'Комментарий слишком длинный (макс. 2000 символов).', 'id' => null];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO comments (event_id, user_id, text) VALUES (:event_id, :user_id, :text)'
        );
        $stmt->execute([
            ':event_id' => $eventId,
            ':user_id'  => $userId,
            ':text'     => $text,
        ]);

        $commentId = (int) $pdo->lastInsertId();
        return ['success' => true, 'error' => null, 'id' => $commentId];
    } catch (PDOException $e) {
        error_log('Ошибка добавления комментария: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при добавлении комментария.', 'id' => null];
    }
}

/* ======================================================================
   Получение комментариев мероприятия
   ====================================================================== */

/**
 * Возвращает комментарии мероприятия в хронологическом порядке.
 *
 * @param PDO $pdo
 * @param int $eventId
 * @param int $limit Максимум комментариев (0 = без ограничения)
 * @return array
 */
function getComments(PDO $pdo, int $eventId, int $limit = 100): array
{
    try {
        if ($limit > 0) {
            $stmt = $pdo->prepare(
                'SELECT c.*, u.username
                 FROM comments c
                 LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.event_id = :event_id
                 ORDER BY c.created_at ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare(
                'SELECT c.*, u.username
                 FROM comments c
                 LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.event_id = :event_id
                 ORDER BY c.created_at ASC'
            );
            $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Ошибка получения комментариев: ' . $e->getMessage());
        return [];
    }
}

/* ======================================================================
   Удаление комментария
   ====================================================================== */

/**
 * Удаляет комментарий (только автор или организатор мероприятия).
 *
 * @param PDO $pdo
 * @param int $commentId
 * @param int $userId
 * @return array
 */
function deleteComment(PDO $pdo, int $commentId, int $userId): array
{
    try {
        $stmt = $pdo->prepare('SELECT event_id, user_id FROM comments WHERE id = :id');
        $stmt->execute([':id' => $commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            return ['success' => false, 'error' => 'Комментарий не найден.'];
        }

        /* Удалить может автор или организатор мероприятия */
        if ((int) $comment['user_id'] !== $userId && !canManageEvent($pdo, $comment['event_id'], $userId)) {
            return ['success' => false, 'error' => 'Недостаточно прав.'];
        }

        $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute([':id' => $commentId]);
        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ошибка удаления комментария: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Произошла ошибка при удалении комментария.'];
    }
}
