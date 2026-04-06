<?php
/**
 * Файл: assets/init_db.php
 * Описание: Инициализация базы данных SQLite — создание всех таблиц
 *           для модулей авторизации, мероприятий, задач, комментариев, лога.
 *           Запускается однократно при первом развёртывании.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

/* Путь к файлу БД */
$dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db';

/* Убедимся, что директория data существует */
$dataDir = dirname($dbPath);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

try {
    /* Подключение к SQLite */
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    /* Включение внешних ключей */
    $pdo->exec('PRAGMA foreign_keys = ON');

    /* ===== Таблица пользователей (авторизация) ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            email         TEXT,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_username ON users (username)');

    /* ===== Таблица мероприятий ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS events (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            creator_id     INTEGER,
            title          TEXT NOT NULL,
            description    TEXT,
            start_datetime TEXT,
            end_datetime   TEXT,
            location       TEXT,
            status         TEXT DEFAULT \'draft\',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(creator_id) REFERENCES users(id)
        )
    ');

    /* ===== Таблица участников мероприятия ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS event_participants (
            event_id INTEGER,
            user_id  INTEGER,
            role     TEXT DEFAULT \'participant\',
            PRIMARY KEY(event_id, user_id),
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id)  REFERENCES users(id)  ON DELETE CASCADE
        )
    ');

    /* ===== Таблица задач ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tasks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id    INTEGER,
            creator_id  INTEGER,
            assigned_to INTEGER,
            title       TEXT NOT NULL,
            description TEXT,
            deadline    TEXT,
            is_done     INTEGER DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(event_id)   REFERENCES events(id)  ON DELETE CASCADE,
            FOREIGN KEY(creator_id) REFERENCES users(id),
            FOREIGN KEY(assigned_to) REFERENCES users(id)
        )
    ');

    /* ===== Таблица комментариев ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id   INTEGER,
            user_id    INTEGER,
            text       TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id)  REFERENCES users(id)
        )
    ');

    /* ===== Таблица журнала активности ===== */
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS activity_log (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER,
            action       TEXT NOT NULL,
            entity_type  TEXT,
            entity_id    INTEGER,
            details      TEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )
    ');

    echo 'База данных успешно инициализирована: ' . $dbPath . PHP_EOL;
    echo 'Таблицы: users, events, event_participants, tasks, comments, activity_log' . PHP_EOL;
} catch (PDOException $e) {
    error_log('Ошибка инициализации БД: ' . $e->getMessage());
    die('Ошибка при создании базы данных. Проверьте права на запись в директорию data/.');
}
