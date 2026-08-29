<?php
use App\Core\Router;

// ── Root / Home Redirect ─────────────────────────────────────
Router::get('/', function ($request) {
    if (isLoggedIn()) {
        if (isAdmin()) {
            header('Location: ' . BASE_PATH . '/admin');
        } else {
            header('Location: ' . BASE_PATH . '/dashboard');
        }
    } else {
        header('Location: ' . BASE_PATH . '/login');
    }
    exit;
});

// ── Auth Routes (Guest Only) ─────────────────────────────────
Router::get('/login',           'AuthController@showLogin',          ['guest']);
Router::post('/login',          'AuthController@login',              ['guest']);
Router::get('/register',        'AuthController@showRegister',       ['guest']);
Router::post('/register',       'AuthController@register',           ['guest']);
Router::get('/verify-otp',      'AuthController@showVerifyOtp');
Router::post('/verify-otp',     'AuthController@verifyOtp');
Router::get('/forgot-password', 'AuthController@showForgotPassword', ['guest']);
Router::post('/forgot-password','AuthController@forgotPassword',     ['guest']);
Router::get('/reset-password',  'AuthController@showResetPassword',  ['guest']);
Router::post('/reset-password', 'AuthController@resetPassword',      ['guest']);
Router::get('/logout',          'AuthController@logout');

// ── Student / User Routes (Auth Required) ────────────────────
Router::get('/dashboard',                  'DashboardController@index',       ['auth']);
Router::get('/quizzes',                    'QuizController@index',             ['auth']);
Router::get('/quiz/take/{id}',             'QuizController@take',              ['auth']);
Router::post('/quiz/submit/{id}',          'QuizController@submit',            ['auth']);
Router::post('/quiz/tab-switch/{id}',      'QuizController@pingTabSwitch',     ['auth']);
Router::get('/quiz/result/{attemptId}',    'QuizController@result',            ['auth']);
Router::get('/my-attempts',                'QuizController@attempts',          ['auth']);
Router::get('/certificates',               'CertificateController@index',      ['auth']);
Router::get('/leaderboard',                'LeaderboardController@index',      ['auth']);
Router::get('/profile',                    'ProfileController@index',          ['auth']);
Router::post('/profile',                   'ProfileController@update',         ['auth']);

// ── Practice Routes ──────────────────────────────────────────
Router::get('/practice',                   'PracticeController@practice',      ['auth']);
Router::get('/daily-quiz',                 'PracticeController@daily',         ['auth']);
Router::get('/weak-topics',                'PracticeController@weakTopics',    ['auth']);
Router::get('/adaptive-quiz',              'PracticeController@adaptive',      ['auth']);
Router::get('/ai-practice',                'PracticeController@aiPractice',    ['auth']);

// ── Admin Routes (Admin Only) ────────────────────────────────
Router::get('/admin',                      'Admin\DashboardController@index',     ['admin']);
Router::get('/admin/quizzes',              'Admin\QuizController@index',          ['admin']);
Router::post('/admin/quizzes',             'Admin\QuizController@create',         ['admin']);
Router::post('/admin/quizzes/update/{id}', 'Admin\QuizController@update',         ['admin']);
Router::post('/admin/quizzes/delete/{id}', 'Admin\QuizController@delete',         ['admin']);

Router::get('/admin/questions',            'Admin\QuestionController@index',      ['admin']);
Router::post('/admin/questions',           'Admin\QuestionController@create',     ['admin']);
Router::get('/admin/questions/edit/{id}',  'Admin\QuestionController@edit',       ['admin']);
Router::post('/admin/questions/update/{id}','Admin\QuestionController@update',    ['admin']);
Router::post('/admin/questions/delete/{id}','Admin\QuestionController@delete',    ['admin']);
Router::post('/admin/questions/unflag/{id}','Admin\QuestionController@unflag',    ['admin']);

Router::get('/admin/categories',           'Admin\CategoryController@index',      ['admin']);
Router::post('/admin/categories',          'Admin\CategoryController@create',     ['admin']);
Router::post('/admin/categories/update/{id}','Admin\CategoryController@update',   ['admin']);
Router::post('/admin/categories/delete/{id}','Admin\CategoryController@delete',   ['admin']);

Router::get('/admin/users',                'Admin\UserController@index',          ['admin']);
Router::post('/admin/users/ban/{id}',      'Admin\UserController@toggleBan',      ['admin']);
Router::post('/admin/users/delete/{id}',   'Admin\UserController@delete',         ['admin']);
Router::get('/admin/users/report/{id}',    'Admin\ReportController@student',      ['admin']);

Router::get('/admin/reports',              'Admin\ReportController@index',        ['admin']);
Router::get('/admin/reports/attempt/{id}', 'Admin\ReportController@attempt',      ['admin']);

Router::get('/admin/ai-generator',         'Admin\AiGeneratorController@index',   ['admin']);
Router::post('/admin/ai-generator',        'Admin\AiGeneratorController@generate',['admin']);
