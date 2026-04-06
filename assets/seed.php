<?php
/**
 * Файл: assets/seed.php
 * Описание: Заполнение базы данных тестовыми данными.
 *           Создаёт пользователей, мероприятия, участников, задачи,
 *           комментарии и записи журнала активности.
 *           Запускается однократно для демонстрации.
 * Дипломный проект: «Веб-приложение для совместного планирования мероприятий»
 * Год: 2026
 */

$dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db';

if (!file_exists($dbPath)) {
    die('База данных не найдена. Сначала запустите init_db.php.');
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    echo "Начинаем заполнение базы данных...\n\n";

    /* ======================================================================
       1. ПОЛЬЗОВАТЕЛИ
       ====================================================================== */

    $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

    $users = [
        ['admin',     $passwordHash, 'admin@university.ru'],
        ['ivanov',    $passwordHash, 'ivanov@university.ru'],
        ['petrova',   $passwordHash, 'petrova@university.ru'],
        ['sidorov',   $passwordHash, 'sidorov@university.ru'],
        ['kozlova',   $passwordHash, 'kozlova@university.ru'],
        ['morozov',   $passwordHash, 'morozov@university.ru'],
        ['volkova',   $passwordHash, 'volkova@university.ru'],
        ['novikov',   $passwordHash, 'novikov@university.ru'],
        ['fedorova',  $passwordHash, 'fedorova@university.ru'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, password_hash, email) VALUES (:username, :password_hash, :email)');
    foreach ($users as [$username, $hash, $email]) {
        $stmt->execute([':username' => $username, ':password_hash' => $hash, ':email' => $email]);
        echo "  Пользователь: $username\n";
    }

    /* Получаем ID пользователей */
    $stmt = $pdo->query('SELECT id, username FROM users');
    $usersMap = [];
    foreach ($stmt->fetchAll() as $u) {
        $usersMap[$u['username']] = (int) $u['id'];
    }

    $ivanovId   = $usersMap['ivanov'];
    $petrovaId  = $usersMap['petrova'];
    $sidorovId  = $usersMap['sidorov'];
    $kozlovaId  = $usersMap['kozlova'];
    $morozovId  = $usersMap['morozov'];
    $volkovaId  = $usersMap['volkova'];
    $novikovId  = $usersMap['novikov'];
    $fedorovaId = $usersMap['fedorova'];

    echo "\n";

    /* ======================================================================
       2. МЕРОПРИЯТИЯ
       ====================================================================== */

    $now = date('Y-m-d H:i:s');

    $events = [
        [
            'creator_id'    => $ivanovId,
            'title'         => 'Корпоратив — Новый Год 2026',
            'description'   => "Организация новогоднего корпоративного вечера для сотрудников компании.\n\nПрограмма:\n— Приветственный коктейль\n— Развлекательная программа\n— Ужин\n— Танцы до утра\n\nНеобходимо продумать декорации, меню и музыкальное сопровождение.",
            'start_datetime'=> '2026-01-15 18:00:00',
            'end_datetime'  => '2026-01-16 02:00:00',
            'location'      => 'Ресторан «Гранд», ул. Пушкина, 42',
            'status'        => 'active',
        ],
        [
            'creator_id'    => $petrovaId,
            'title'         => 'Конференция TechSummit 2026',
            'description'   => "Ежегодная IT-конференция для разработчиков и дизайнеров.\n\nТемы:\n— Искусственный интеллект\n— Облачные технологии\n— Кибербезопасность\n— UX/UI дизайн\n\nОжидается 500+ участников.",
            'start_datetime'=> '2026-03-20 09:00:00',
            'end_datetime'  => '2026-03-21 18:00:00',
            'location'      => 'Конгресс-центр «Сколково»',
            'status'        => 'active',
        ],
        [
            'creator_id'    => $sidorovId,
            'title'         => 'Спортивный турнир по волейболу',
            'description'   => 'Межфакультетский турнир по волейболу.\n\n8 команд, групповой этап + плей-офф.\nПризовой фонд — 50 000 руб.',
            'start_datetime'=> '2026-02-10 10:00:00',
            'end_datetime'  => '2026-02-10 17:00:00',
            'location'      => 'Спорткомплекс «Олимпиец»',
            'status'        => 'draft',
        ],
        [
            'creator_id'    => $kozlovaId,
            'title'         => 'Выпускной вечер — Группа 2026',
            'description'   => "Торжественный вечер, посвящённый окончанию обучения.\n\nДресс-код: Black Tie.\nФотограф и видеооператор — обязательно.\nПосле банкета — after-party в лаунж-зоне.",
            'start_datetime'=> '2026-06-28 19:00:00',
            'end_datetime'  => '2026-06-29 03:00:00',
            'location'      => 'Отель «Метрополь», Большой зал',
            'status'        => 'draft',
        ],
        [
            'creator_id'    => $morozovId,
            'title'         => 'Хакатон «Code & Coffee»',
            'description'   => "48-часовой хакатон для студентов.\n\nТема: «Технологии для умного города».\nКоманды 3-5 человек.\nПитание и кофе — за счёт организаторов.",
            'start_datetime'=> '2026-04-12 08:00:00',
            'end_datetime'  => '2026-04-14 08:00:00',
            'location'      => 'Коворкинг «Точка кипения»',
            'status'        => 'active',
        ],
        [
            'creator_id'    => $volkovaId,
            'title'         => 'День открытых дверей университета',
            'description'   => "Мероприятие для абитуриентов и их родителей.\n\nЭкскурсии по кампусу, мастер-классы, встречи с преподавателями.\nОжидается 1000+ гостей.",
            'start_datetime'=> '2026-05-18 10:00:00',
            'end_datetime'  => '2026-05-18 16:00:00',
            'location'      => 'Главный корпус университета',
            'status'        => 'active',
        ],
        [
            'creator_id'    => $ivanovId,
            'title'         => 'Тимбилдинг «Верёвочный курс»',
            'description'   => 'Корпоративный тимбилдинг на свежем воздухе.\n\nВерёвочный курс, квесты, барбекю.\nДля отдела разработки (30 человек).',
            'start_datetime'=> '2026-07-05 09:00:00',
            'end_datetime'  => '2026-07-05 18:00:00',
            'location'      => 'Парк «Лосиный остров»',
            'status'        => 'draft',
        ],
        [
            'creator_id'    => $novikovId,
            'title'         => 'Благотворительный концерт',
            'description'   => "Концерт в поддержку детского хосписа.\n\nВыступления студенческих коллективов.\nВход свободный, сбор пожертвований.",
            'start_datetime'=> '2026-04-25 17:00:00',
            'end_datetime'  => '2026-04-25 21:00:00',
            'location'      => 'ДК «Студент», актовый зал',
            'status'        => 'active',
        ],
        [
            'creator_id'    => $petrovaId,
            'title'         => 'Мастер-класс по фотографии',
            'description'   => 'Бесплатный мастер-класс для начинающих.\n\nОсновы композиции, работа со светом, обработка в Lightroom.\n15 мест.',
            'start_datetime'=> '2026-03-01 14:00:00',
            'end_datetime'  => '2026-03-01 17:00:00',
            'location'      => 'Фотостудия «Ракурс»',
            'status'        => 'completed',
        ],
        [
            'creator_id'    => $fedorovaId,
            'title'         => 'Квиз «Что? Где? Когда?»',
            'description'   => 'Интеллектуальная игра для всех желающих.\n\n10 раундов, команды 5-7 человек.\nПриз для победителей — сертификат в книжный магазин.',
            'start_datetime'=> '2026-02-22 19:00:00',
            'end_datetime'  => '2026-02-22 22:00:00',
            'location'      => 'Антикафе «Время»',
            'status'        => 'completed',
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO events (creator_id, title, description, start_datetime, end_datetime, location, status)
         VALUES (:creator_id, :title, :description, :start_datetime, :end_datetime, :location, :status)'
    );

    $eventIds = [];
    foreach ($events as $ev) {
        $stmt->execute([
            ':creator_id'     => $ev['creator_id'],
            ':title'          => $ev['title'],
            ':description'    => $ev['description'],
            ':start_datetime' => $ev['start_datetime'],
            ':end_datetime'   => $ev['end_datetime'],
            ':location'       => $ev['location'],
            ':status'         => $ev['status'],
        ]);
        $eventIds[] = (int) $pdo->lastInsertId();
        echo "  Мероприятие: {$ev['title']}\n";
    }

    echo "\n";

    /* ======================================================================
       3. УЧАСТНИКИ МЕРОПРИЯТИЙ
       ====================================================================== */

    $participants = [
        // Новогодний корпоратив (event 1)
        [$eventIds[0], $petrovaId,  'organizer'],
        [$eventIds[0], $sidorovId,  'participant'],
        [$eventIds[0], $kozlovaId,  'participant'],
        [$eventIds[0], $morozovId,  'participant'],
        [$eventIds[0], $volkovaId,  'participant'],
        [$eventIds[0], $novikovId,  'participant'],
        // Конференция TechSummit (event 2)
        [$eventIds[1], $ivanovId,   'participant'],
        [$eventIds[1], $sidorovId,  'organizer'],
        [$eventIds[1], $kozlovaId,  'participant'],
        [$eventIds[1], $fedorovaId, 'participant'],
        // Турнир по волейболу (event 3)
        [$eventIds[2], $ivanovId,   'participant'],
        [$eventIds[2], $petrovaId,  'participant'],
        [$eventIds[2], $morozovId,  'organizer'],
        [$eventIds[2], $novikovId,  'participant'],
        // Выпускной (event 4)
        [$eventIds[3], $ivanovId,   'participant'],
        [$eventIds[3], $petrovaId,  'organizer'],
        [$eventIds[3], $volkovaId,  'participant'],
        [$eventIds[3], $fedorovaId, 'participant'],
        // Хакатон (event 5)
        [$eventIds[4], $ivanovId,   'participant'],
        [$eventIds[4], $petrovaId,  'participant'],
        [$eventIds[4], $sidorovId,  'participant'],
        [$eventIds[4], $kozlovaId,  'organizer'],
        // День открытых дверей (event 6)
        [$eventIds[5], $ivanovId,   'organizer'],
        [$eventIds[5], $petrovaId,  'organizer'],
        [$eventIds[5], $kozlovaId,  'participant'],
        [$eventIds[5], $fedorovaId, 'participant'],
        // Тимбилдинг (event 7)
        [$eventIds[6], $petrovaId,  'participant'],
        [$eventIds[6], $sidorovId,  'participant'],
        [$eventIds[6], $morozovId,  'organizer'],
        // Благотворительный концерт (event 8)
        [$eventIds[7], $ivanovId,   'participant'],
        [$eventIds[7], $kozlovaId,  'organizer'],
        [$eventIds[7], $volkovaId,  'participant'],
        // Мастер-класс по фотографии (event 9, completed)
        [$eventIds[8], $ivanovId,   'participant'],
        [$eventIds[8], $morozovId,  'participant'],
        [$eventIds[8], $volkovaId,  'participant'],
        [$eventIds[8], $fedorovaId, 'participant'],
        // Квиз (event 10, completed)
        [$eventIds[9], $petrovaId,  'participant'],
        [$eventIds[9], $sidorovId,  'participant'],
        [$eventIds[9], $novikovId,  'participant'],
        [$eventIds[9], $fedorovaId, 'participant'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO event_participants (event_id, user_id, role) VALUES (:event_id, :user_id, :role)');
    foreach ($participants as [$eid, $uid, $role]) {
        $stmt->execute([':event_id' => $eid, ':user_id' => $uid, ':role' => $role]);
    }
    echo "  Участники добавлены: " . count($participants) . " записей\n\n";

    /* ======================================================================
       4. ЗАДАЧИ
       ====================================================================== */

    $tasks = [
        // Новогодний корпоратив
        [$eventIds[0], $ivanovId,  $petrovaId,  'Забронировать ресторан',        'Позвонить и подтвердить бронирование большого зала на 50 человек', '2025-12-20'],
        [$eventIds[0], $petrovaId, $kozlovaId,  'Согласовать меню',               'Обсудить с шеф-поваром варианты фуршета и основного блюда',      '2025-12-25'],
        [$eventIds[0], $ivanovId,  $sidorovId,  'Найти ведущего',                 'Посмотреть варианты и выбрать ведущего для развлекательной программы', '2026-01-05'],
        [$eventIds[0], $petrovaId, $morozovId,  'Заказать украшение зала',        'Гирлянды, шары, новогодняя фотозона',                            '2026-01-10'],
        [$eventIds[0], $ivanovId,  $volkovaId,  'Подготовить подарки',            'Купить подарки для конкурса «Тайный Санта»',                     '2026-01-12'],
        [$eventIds[0], $sidorovId, $novikovId,  'Составить плейлист',             'Подобрать музыку для танцевальной части вечера',                   '2026-01-13'],
        // Конференция TechSummit
        [$eventIds[1], $petrovaId, $ivanovId,   'Пригласить спикеров',            'Связаться с потенциальными докладчиками',                         '2026-02-15'],
        [$eventIds[1], $sidorovId, $kozlovaId,  'Организовать стрим',             'Настроить трансляцию для онлайн-участников',                      '2026-03-01'],
        [$eventIds[1], $petrovaId, $fedorovaId, 'Заказать мерч',                  'Футболки, стикеры, блокноты с логотипом конференции',             '2026-03-10'],
        [$eventIds[1], $ivanovId,  $sidorovId,  'Подготовить программу',          'Составить расписание докладов и мастер-классов',                  '2026-03-15'],
        // Хакатон
        [$eventIds[4], $morozovId, $ivanovId,   'Найти менторов',                 'Пригласить опытных разработчиков в качестве наставников',         '2026-03-25'],
        [$eventIds[4], $kozlovaId, $petrovaId,  'Организовать питание',           'Заказать еду и напитки на 48 часов',                             '2026-04-01'],
        [$eventIds[4], $morozovId, $sidorovId,  'Подготовить API для хакатона',   'Создать тестовый API с данными для участников',                  '2026-04-05'],
        // День открытых дверей
        [$eventIds[5], $ivanovId,  $volkovaId,  'Напечатать буклеты',             'Разработать и напечатать 2000 буклетов',                         '2026-04-20'],
        [$eventIds[5], $petrovaId, $fedorovaId, 'Подготовить экскурсии',          'Составить маршрут и подготовить экскурсоводов',                  '2026-05-01'],
        [$eventIds[5], $kozlovaId, $kozlovaId,  'Заказать кейтеринг',             'Напитки и лёгкие закуски для гостей',                            '2026-05-10'],
        // Тимбилдинг
        [$eventIds[6], $ivanovId,  $morozovId,  'Арендовать площадку',            'Забронировать площадку в парке',                                      '2026-06-01'],
        [$eventIds[6], $petrovaId, $sidorovId,  'Заказать барбекю',               'Мясо, овощи, напитки для 30 человек',                            '2026-06-20'],
        // Турнир по волейболу
        [$eventIds[2], $morozovId, $novikovId,  'Купить мячи и сетки',            'Заказать 6 мячей Mikasa и 2 комплекта сеток',                    '2026-02-01'],
        [$eventIds[2], $ivanovId,  $ivanovId,   'Создать турнирную сетку',        'Подготовить расписание матчей',                                  '2026-02-05'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO tasks (event_id, creator_id, assigned_to, title, description, deadline)
         VALUES (:event_id, :creator_id, :assigned_to, :title, :description, :deadline)'
    );

    $taskIds = [];
    foreach ($tasks as [$eid, $cid, $aid, $title, $desc, $deadline]) {
        $stmt->execute([
            ':event_id'    => $eid,
            ':creator_id'  => $cid,
            ':assigned_to' => $aid,
            ':title'       => $title,
            ':description' => $desc,
            ':deadline'    => $deadline,
        ]);
        $taskIds[] = (int) $pdo->lastInsertId();
    }
    echo "  Задачи добавлены: " . count($tasks) . " записей\n\n";

    /* ======================================================================
       5. ОТМЕТИТЬ НЕКОТОРЫЕ ЗАДАЧИ КАК ВЫПОЛНЕННЫЕ
       ====================================================================== */

    /* Выполнены задачи с индексами 0, 1, 5, 8, 9, 14, 17, 18 */
    $completedTasks = [0, 1, 5, 8, 9, 14, 17, 18];
    foreach ($completedTasks as $idx) {
        if (isset($taskIds[$idx])) {
            $pdo->prepare('UPDATE tasks SET is_done = 1 WHERE id = :id')
                ->execute([':id' => $taskIds[$idx]]);
        }
    }
    echo "  Задачи отмечены как выполненные: " . count($completedTasks) . "\n\n";

    /* ======================================================================
       6. КОММЕНТАРИИ
       ====================================================================== */

    $comments = [
        [$eventIds[0], $petrovaId,  'Отличный выбор ресторана! Я там была на прошлом корпоративе — еда супер.'],
        [$eventIds[0], $sidorovId,  'Могу предложить свою кандидатуру в качестве ведущего. Есть опыт 😉'],
        [$eventIds[0], $kozlovaId,  'Давайте сделаем тематическую фотозону в стиле 80-х!'],
        [$eventIds[0], $morozovId,  'Украшение зала уже в процессе работы. Фотозона будет готова к 10 января.'],
        [$eventIds[1], $ivanovId,   'Тема ИИ в этом году очень актуальна. Предлагаю пригласить кого-то из Яндекса.'],
        [$eventIds[1], $fedorovaId, 'Мерч должен быть стильным! Предлагаю минималистичный дизайн с логотипом.'],
        [$eventIds[4], $ivanovId,   '48 часов — это серьёзный вызов! Нужно подготовить хорошую инфраструктуру.'],
        [$eventIds[4], $petrovaId,  'Могу привезти свою кофемашину, если нужно ☕'],
        [$eventIds[5], $ivanovId,   'Нужно обязательно сделать интерактивные зоны для абитуриентов.'],
        [$eventIds[5], $fedorovaId, 'Предлагаю добавить квест по кампусу с призами.'],
        [$eventIds[7], $kozlovaId,  'Очень важное дело! Буду помогать с организацией.'],
        [$eventIds[7], $volkovaId,  'Может, пригласить школьный хор для выступления?'],
        [$eventIds[2], $novikovId,  'Наша команда уже готова! 6 человек, тренируемся каждую неделю.'],
        [$eventIds[3], $petrovaId,  'Нужно начать подготовку заранее. Бронировать зал лучше за 3 месяца.'],
        [$eventIds[6], $sidorovId,  'Верёвочный курс — отличная идея! Главное, чтобы погода не подвела.'],
        [$eventIds[8], $ivanovId,   'Спасибо за мастер-класс! Очень полезно для начинающего фотографа.'],
        [$eventIds[8], $morozovId,  'Согласен, свет — это 80% хорошей фотографии.'],
        [$eventIds[9], $petrovaId,  'Было весело! Наша команда заняла 2-е место 🎉'],
        [$eventIds[9], $sidorovId,  'Вопросы были сложные, но интересные. Спасибо организаторам!'],
    ];

    $stmt = $pdo->prepare('INSERT INTO comments (event_id, user_id, text) VALUES (:event_id, :user_id, :text)');
    foreach ($comments as [$eid, $uid, $text]) {
        $stmt->execute([':event_id' => $eid, ':user_id' => $uid, ':text' => $text]);
    }
    echo "  Комментарии добавлены: " . count($comments) . " записей\n\n";

    /* ======================================================================
       7. ЖУРНАЛ АКТИВНОСТИ
       ====================================================================== */

    $activities = [
        [$ivanovId,   'create',       'event',     $eventIds[0], 'Создание мероприятия: Корпоратив — Новый Год 2026'],
        [$petrovaId,  'create',       'event',     $eventIds[1], 'Создание мероприятия: Конференция TechSummit 2026'],
        [$sidorovId,  'create',       'event',     $eventIds[2], 'Создание мероприятия: Спортивный турнир по волейболу'],
        [$kozlovaId,  'create',       'event',     $eventIds[3], 'Создание мероприятия: Выпускной вечер — Группа 2026'],
        [$morozovId,  'create',       'event',     $eventIds[4], 'Создание мероприятия: Хакатон Code & Coffee'],
        [$volkovaId,  'create',       'event',     $eventIds[5], 'Создание мероприятия: День открытых дверей'],
        [$ivanovId,   'create',       'event',     $eventIds[6], 'Создание мероприятия: Тимбилдинг Верёвочный курс'],
        [$novikovId,  'create',       'event',     $eventIds[7], 'Создание мероприятия: Благотворительный концерт'],
        [$petrovaId,  'create',       'event',     $eventIds[8], 'Создание мероприятия: Мастер-класс по фотографии'],
        [$fedorovaId, 'create',       'event',     $eventIds[9], 'Создание мероприятия: Квиз Что? Где? Когда?'],
        [$petrovaId,  'add_participant', 'participant', $eventIds[0], 'Добавлен участник: petrova (роль: organizer)'],
        [$sidorovId,  'add_participant', 'participant', $eventIds[0], 'Добавлен участник: sidorov (роль: participant)'],
        [$kozlovaId,  'add_participant', 'participant', $eventIds[0], 'Добавлен участник: kozlova (роль: participant)'],
        [$ivanovId,   'create',       'task',      $taskIds[0] ?? 1, 'Задача: Забронировать ресторан'],
        [$petrovaId,  'create',       'task',      $taskIds[1] ?? 2, 'Задача: Согласовать меню'],
        [$ivanovId,   'toggle_task',  'task',      $taskIds[0] ?? 1, 'Переключение статуса задачи'],
        [$petrovaId,  'toggle_task',  'task',      $taskIds[1] ?? 2, 'Переключение статуса задачи'],
        [$petrovaId,  'update',       'event',     $eventIds[0],  'Обновление: Корпоратив — Новый Год 2026'],
        [$sidorovId,  'create',       'task',      $taskIds[2] ?? 3, 'Задача: Найти ведущего'],
        [$petrovaId,  'create',       'task',      $taskIds[3] ?? 4, 'Задача: Заказать украшение зала'],
        [$ivanovId,   'create',       'task',      $taskIds[4] ?? 5, 'Задача: Подготовить подарки'],
        [$sidorovId,  'create',       'task',      $taskIds[5] ?? 6, 'Задача: Составить плейлист'],
        [$petrovaId,  'create',       'event',     $eventIds[8],  'Обновление: Мастер-класс по фотографии'],
        [$petrovaId,  'comment',      'comment',   0,             'Новый комментарий'],
        [$sidorovId,  'comment',      'comment',   0,             'Новый комментарий'],
        [$kozlovaId,  'comment',      'comment',   0,             'Новый комментарий'],
        [$ivanovId,   'comment',      'comment',   0,             'Новый комментарий'],
        [$petrovaId,  'update',       'event',     $eventIds[1],  'Обновление: Конференция TechSummit 2026'],
        [$ivanovId,   'create',       'task',      $taskIds[6] ?? 7, 'Задача: Пригласить спикеров'],
        [$sidorovId,  'create',       'task',      $taskIds[7] ?? 8, 'Задача: Организовать стрим'],
        [$petrovaId,  'create',       'task',      $taskIds[8] ?? 9, 'Задача: Заказать мерч'],
        [$petrovaId,  'toggle_task',  'task',      $taskIds[8] ?? 9, 'Переключение статуса задачи'],
        [$ivanovId,   'create',       'task',      $taskIds[9] ?? 10, 'Задача: Подготовить программу'],
        [$ivanovId,   'toggle_task',  'task',      $taskIds[9] ?? 10, 'Переключение статуса задачи'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
    );

    /* Генерируем временные метки для лога (от более старых к новым) */
    $baseTime = strtotime('2025-11-01 10:00:00');
    foreach ($activities as $i => [$uid, $action, $entityType, $entityId, $details]) {
        $timestamp = date('Y-m-d H:i:s', $baseTime + ($i * 86400 * 2 + rand(0, 43200)));
        $stmt->execute([
            ':user_id'     => $uid,
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId > 0 ? $entityId : null,
            ':details'     => $details,
        ]);
        /* Обновляем created_at на реальную дату */
        $logId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE activity_log SET created_at = :ts WHERE id = :id')
            ->execute([':ts' => $timestamp, ':id' => $logId]);
    }
    echo "  Записи лога добавлены: " . count($activities) . "\n\n";

    /* ======================================================================
       ИТОГО
       ====================================================================== */

    $counts = [];
    foreach (['users', 'events', 'event_participants', 'tasks', 'comments', 'activity_log'] as $table) {
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    echo "========================================\n";
    echo "База данных успешно заполнена!\n";
    echo "========================================\n";
    foreach ($counts as $table => $count) {
        printf("  %-25s %d записей\n", $table . ':', $count);
    }
    echo "========================================\n";
    echo "Логин для всех пользователей: [username]\n";
    echo "Пароль для всех пользователей: 123456\n";
    echo "========================================\n";

} catch (PDOException $e) {
    error_log('Ошибка заполнения БД: ' . $e->getMessage());
    die('Произошла ошибка при заполнении базы данных: ' . $e->getMessage());
}
