<?php
/**
 * Файл: includes/db.php
 * Описание: Подключение к базе данных SQLite через PDO.
 *           Файл БД хранится вне публичной директории (../data/app.db).
 *           Включена поддержка внешних ключей (PRAGMA foreign_keys = ON).
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

/* Путь к файлу базы данных (вне публичной директории) */
$dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db';

/* Проверка: если файл БД не существует, выведем сообщение */
if (!file_exists($dbPath)) {
    die('База данных не найдена. Запустите assets/init_db.php для инициализации.');
}

try {
    /* Создание PDO-подключения к SQLite */
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  /* Выбрасывать исключения при ошибках */
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        /* Возвращать массивы по умолчанию */
        PDO::ATTR_EMULATE_PREPARES   => false,                   /* Использовать нативные подготовленные запросы */
    ]);

    /* Включение поддержки внешних ключей в SQLite */
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    /* Обработка ошибки подключения — детали не выводим пользователю */
    error_log('Ошибка подключения к БД: ' . $e->getMessage());
    die('Произошла ошибка при подключении к базе данных.');
}
