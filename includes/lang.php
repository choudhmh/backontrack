<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   LANGUAGE SWITCH
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

/* =========================
   DEFAULT LANGUAGE
========================= */
$lang = $_SESSION['lang'] ?? 'en';

/* =========================
   TRANSLATIONS
========================= */
$translations = [

    'en' => [
        'dashboard' => 'Dashboard',
        'join_class' => 'Join Class',
        'tasks' => 'Tasks',
        'materials' => 'Materials',
        'ai' => 'AI Recommendations',
        'logout' => 'Logout',
        'my_tasks' => 'My Tasks',
        'Your_Tasks' => 'Your Tasks',
        "study_materials" => "Study Materials"
    ],

    'ru' => [
        'dashboard' => 'Панель',
        'join_class' => 'Присоединиться к классу',
        'tasks' => 'Задачи',
        'materials' => 'Материалы',
        "study_materials" => "Учебные материалы",
        'my_tasks' => 'мои задачи',
        'Your_Tasks' => 'Ваши задачи',
        'ai' => 'AI Рекомендации',
        'logout' => 'Выйти'
    ],

    'kz' => [
        'dashboard' => 'Басты бет',
        'join_class' => 'Сыныпқа қосылу',
        'tasks' => 'Тапсырмалар',
        'materials' => 'Материалдар',
        "study_materials" => "Оқу материалдары",
        'my_tasks' => 'менің тапсырмаларым',
        'Your_Tasks' => 'Сіздің тапсырмаларыңыз',
        'ai' => 'AI Ұсыныстар',
        'logout' => 'Шығу'
    ]
];

/* =========================
   TRANSLATION FUNCTION
========================= */
function t($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}