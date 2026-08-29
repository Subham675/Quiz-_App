# 🎓 QuizApp — Enterprise MVC Online Quiz & Examination Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-brightgreen)](https://github.com/Subham675/Quiz-_App)
[![AI Engine](https://img.shields.io/badge/AI%20Engine-Google%20Gemini%202.5-8E75B2?logo=google&logoColor=white)](https://ai.google.dev)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A modern, production-grade **Online Quiz & Learning Management System** built with **PHP 8, MySQL, Bootstrap 5**, and a custom **Model-View-Controller (MVC)** framework engine. Features interactive tests, anti-cheat monitoring, TCPDF certificate generation, 3-layer live email reputation checks, and real-time **Google Gemini AI** practice tests with **Search Autocomplete & Suggestions (Typeahead)**.

---

## 📑 Table of Contents

1. [Project Overview & Elevator Pitch](#-1-project-overview--elevator-pitch)
2. [MVC Architecture & Request Lifecycle](#-2-mvc-architecture--request-lifecycle)
3. [Complete Directory & File Map](#-3-complete-directory--file-map)
4. [Key Modules & Standout Features](#-4-key-modules--standout-features)
5. [Security & Validation Architecture](#-5-security--validation-architecture)
6. [Database Connection Architecture & File Locations](#-6-database-connection-architecture--file-locations)
7. [Database Schema & Entity Relationships](#-7-database-schema--entity-relationships)
8. [Installation & Setup Guide](#-8-installation--setup-guide)
9. [Environment Configuration (.env)](#-9-environment-configuration-env)
10. [RESTful Clean Routes Reference](#-10-restful-clean-routes-reference)
11. [Examiner & Viva Voce Q&A](#-11-examiner--viva-voce-qa)

---

## 🌟 1. Project Overview & Elevator Pitch

> *"Sir, my project is an **Enterprise-Grade Online Quiz & Examination System** built in **PHP 8 and MySQL** following the **Model-View-Controller (MVC)** architectural pattern.*
> 
> *It separates concerns between Data Models, Business Logic Controllers, and Blade-like Views. It includes real-time quiz taking with countdown timers, anti-cheat tab-switch detection, PDF certificate generation for scores $\ge 60\%$, rate-limiting against brute force, a 3-layer live mailbox deliverability checker (AbstractAPI), and an **AI Quiz Engine** powered by **Google Gemini API** with **Search Autocomplete & Suggestions**."*

---

## 🏛️ 2. MVC Architecture & Request Lifecycle

The application operates on a single-point-of-entry **Front Controller** pattern:

```
                  ┌─────────────────────────────────────────┐
                  │          Browser / User Request         │
                  └────────────────────┬────────────────────┘
                                       │
                                       ▼
                       index.php (Front Controller)
                                       │
                                       ▼
                       routes/web.php (Route Matcher)
                                       │
                         [ Middleware: auth / admin / guest ]
                                       │
                                       ▼
                      App\Controllers\* (Business Logic)
                                       │
             ┌─────────────────────────┴─────────────────────────┐
             ▼                                                   ▼
     App\Models\* (Data & PDO)                       App\Core\View (Renderer)
             │                                                   │
             ▼                                                   ▼
      MySQL Database                                app/Views/layouts/main.php
                                                   + app/Views/quiz/take.php
                                                                 │
                                                                 ▼
                                                      HTML Response to Browser
```

### Core Framework Engine (`app/Core/`)
- **`Router.php`**: RESTful routing with parameterized URL matching (`/quiz/take/{id}`), route groups, and middleware (`auth`, `admin`, `guest`).
- **`Controller.php`**: Base controller providing helpers: `render($view, $data, $layout)`, `json($data, $code)`, `redirect($url)`, and `back()`.
- **`Model.php`**: PDO wrapper providing secure parameterized queries: `query()`, `fetchAll()`, `fetchOne()`, `fetchColumn()`, `lastInsertId()`.
- **`Request.php`**: Encapsulates `$_GET`, `$_POST`, sanitization, IP retrieval, URI normalization (stripping `BASE_PATH`), and CSRF verification.
- **`View.php`**: Master layout engine with output buffering (`ob_start()`), passing dynamic `$content` to master templates.

---

## 📂 3. Complete Directory & File Map

```
Quiz-_App/
├── app/
│   ├── Core/                       # Framework Engine
│   │   ├── Controller.php          # Base Controller (render, json, redirect)
│   │   ├── Model.php               # Base Model (PDO prepared statements)
│   │   ├── Request.php             # Input sanitization, CSRF, HTTP wrapper
│   │   ├── Router.php              # Parameterized URL dispatcher
│   │   └── View.php                # Master layout & template engine
│   │
│   ├── Controllers/                # Application Controllers
│   │   ├── AuthController.php      # Login, Register, OTP, Password Reset
│   │   ├── DashboardController.php # Student stats, streaks, recommendations
│   │   ├── QuizController.php      # Quiz catalog, take quiz, submit, results
│   │   ├── PracticeController.php  # Daily quiz, weak topics, adaptive, AI generator
│   │   ├── CertificateController.php# Certificate viewing & TCPDF download
│   │   ├── LeaderboardController.php# Weekly, monthly & all-time rankings
│   │   ├── ProfileController.php   # Account details & password changes
│   │   └── Admin/                  # Admin Controllers
│   │       ├── DashboardController.php  # Platform metrics & analytics
│   │       ├── QuizController.php       # Quiz CRUD & configurations
│   │       ├── QuestionController.php   # MCQ management & answer options
│   │       ├── CategoryController.php   # Categories & slugs
│   │       ├── UserController.php       # Student moderation (ban/unban/delete)
│   │       ├── ReportController.php     # Detailed performance breakdowns
│   │       └── AiGeneratorController.php# Gemini AI bulk quiz generator
│   │
│   ├── Models/                     # Data Access Objects (PDO)
│   │   ├── User.php
│   │   ├── Quiz.php
│   │   ├── Question.php
│   │   ├── Attempt.php
│   │   ├── Certificate.php
│   │   └── Category.php
│   │
│   └── Views/                      # Presentation Layer
│       ├── layouts/
│       │   ├── main.php            # Student master layout (sidebar, navbar, theme)
│       │   └── admin.php           # Admin master layout
│       ├── auth/                   # login, register, verify-otp, forgot-password
│       ├── dashboard/              # Student dashboard
│       ├── quiz/                   # list, take, result, my-attempts
│       ├── certificates/           # Certificates gallery
│       ├── leaderboard/            # Student rankings
│       ├── profile/                # Profile settings
│       ├── practice/               # daily-quiz, weak-topics, adaptive-quiz, ai-practice
│       └── admin/                  # All admin CRUD and reporting views
│
├── config/
│   ├── db.php                      # Database PDO singleton & .env loader
│   ├── migrate.php                 # Schema auto-migration
│   └── mailer.php                  # PHPMailer SMTP configuration
│
├── database/
│   ├── quiz_app.sql                # Complete schema & seed structure
│   └── seed_quizzes.php            # Sample quizzes seeder
│
├── includes/
│   ├── auth.php                    # Auth helpers, 3-layer email validation, OTP, CSRF
│   ├── rate_limiter.php            # Brute force lockout & IP rate limiter
│   └── gemini.php                  # Google Gemini API client
│
├── routes/
│   └── web.php                     # Central declarative route definitions
│
├── index.php                       # Front Controller
├── .htaccess                       # Apache URL rewriting to index.php
├── composer.json                   # PSR-4 Autoloading ("App\\": "app/")
├── .env.example                    # Environment template
└── README.md                       # Documentation
```

---

## 🚀 4. Key Modules & Standout Features

### 👤 Student Features:
- **Interactive Quiz-Taking:** Full-screen responsive quiz runner, active countdown timer, auto-submission on timeout, and question navigation.
- **Anti-Cheat Monitoring:** Monitors browser visibility events (`document.addEventListener("visibilitychange")`) and logs tab-switch counts to the attempt record.
- **Practice Modes:**
  - **Daily Challenge:** Daily streak-based quiz to encourage daily learning.
  - **Weak Topics Analysis:** Aggregates past wrong answers to identify subjects where user accuracy is $< 70\%$.
  - **Adaptive Quiz:** Dynamically scales question difficulty (Easy $\rightarrow$ Medium $\rightarrow$ Hard) based on real-time performance.
- **🤖 AI Practice Generator with Search Autocomplete & Suggestions:**
  - **Typeahead Topic Search:** Typing `pho` suggests *Photography*, *Photosynthesis*, *Photovoltaic Cells*, etc.
  - **Google Gemini API:** Generates customized MCQs on-demand with instant grading, correct answer reveals, and performance cards.
  - **Question Count Selector:** 3 Questions (Quick Sprint), 5 Questions (Standard), or 10 Questions (Deep Dive).
- **🏆 Leaderboard & PDF Certificates:**
  - Weekly, monthly, and all-time rankings based on total points and accuracy.
  - Automated PDF certificate generation for passing attempts ($\ge 60\%$) powered by **TCPDF**.

### 👑 Admin Features:
- **Comprehensive Analytics Dashboard:** Total students, quizzes taken, pass rates, average scores, and recent attempts.
- **Full CRUD Management:** Create and edit quizzes with custom time limits, negative marking penalties, and category assignments.
- **Question Bank:** Rich MCQ management with multiple options, marks, difficulty, and tags.
- **Student Moderation:** Ban/unban suspicious accounts, view individual student attempt histories.
- **Gemini AI Bulk Generator:** Generate complete 10-question quizzes for any subject directly into the database with one click.

---

## 🛡️ 5. Security & Validation Architecture

| Security Layer | Implementation Detail |
|---|---|
| **3-Layer Email Deliverability** | **1.** `filter_var()` format validation.<br>**2.** DNS MX lookup (`checkdnsrr`) + Kickbox API to reject disposable emails.<br>**3.** **AbstractAPI Email Reputation** to verify the specific mailbox exists before issuing OTP. |
| **Brute Force Protection** | `RateLimiter` class tracks failed login/register/OTP attempts by IP. Locks IP for 5, 10, or 15 minutes upon exceeding thresholds. |
| **CSRF Protection** | Every POST form requires a cryptographic `csrf_token` validated against `$_SESSION['csrf_token']`. |
| **SQL Injection Defense** | 100% of queries use PDO prepared statements with bound parameters. Zero raw string interpolation. |
| **XSS Sanitization** | All dynamic user output is filtered with `htmlspecialchars()` and `strip_tags()`. |
| **Session Security** | Sessions regenerate ID on login (`session_regenerate_id(true)`) to prevent session fixation attacks. |

---

## 🗄️ 6. Database Connection Architecture & File Locations

### 📍 File Locations on Your PC

| Component | File Path | Role & Purpose |
|---|---|---|
| **Database Connection & PDO Singleton** | `config/db.php` | Reads `.env`, creates PDO instance, configures error handling, and runs auto-migrations. |
| **Database Credentials** | `.env` | Holds database host (`127.0.0.1`), database name (`quiz_app`), user (`root`), and password. |
| **MVC Base Model (Query Layer)** | `app/Core/Model.php` | Provides query helpers: `fetchAll()`, `fetchOne()`, `query()`, `lastInsertId()`. |
| **Database Schema SQL** | `database/quiz_app.sql` | Complete schema DDL, table structures, and seed data. |
| **Auto-Migration Engine** | `config/migrate.php` | Automatically checks and creates missing tables/columns on application startup. |

### 🔌 How the Database Connection Works (PDO Singleton)

In `config/db.php`, the connection is established using the **PDO Singleton Pattern**:

```php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES         => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            runMigrations($pdo);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}
```

### 💡 Key Benefits of this Database Design:
1. **Singleton Pattern (`static $pdo`):** Opens **only one single connection** per HTTP request, preventing socket exhaustion and memory overhead.
2. **Native Prepared Statements (`ATTR_EMULATE_PREPARES => false`):** Forces true database-level prepared statements, guaranteeing **100% immunity against SQL Injection**.
3. **Full Unicode Support (`utf8mb4`):** Supports all international characters, mathematical notation, and emojis.
4. **Exception Handling (`ERRMODE_EXCEPTION`):** Converts database errors into catchable exceptions rather than exposing raw database errors to end users.

---

## 🗄️ 7. Database Schema & Entity Relationships

```
 users (id, name, email, password, role, is_verified, is_banned)
   │
   ├─► quiz_attempts (id, user_id, quiz_id, score, total_marks, tab_switches, is_completed)
   │     │
   │     └─► attempt_answers (id, attempt_id, question_id, selected_option, is_correct)
   │
   └─► certificates (id, user_id, quiz_id, certificate_code, pdf_path)

 categories (id, name, slug)
   │
   └─► quizzes (id, category_id, title, time_limit, negative_marks)
         │
         └─► questions (id, quiz_id, question_text, marks, difficulty, tag)
               │
               └─► options (id, question_id, option_text, is_correct)
```

---

## 💻 7. Installation & Setup Guide

### Prerequisites:
- PHP >= 8.0 with `pdo_mysql`, `curl`, `openssl`, `mbstring` extensions enabled.
- MySQL 5.7+ or MariaDB 10.4+
- Composer
- XAMPP / Apache with `mod_rewrite` enabled.

### Step-by-Step Setup:

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/Subham675/Quiz-_App.git
   cd Quiz-_App
   ```

2. **Install Composer Dependencies:**
   ```bash
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   ```
   Open `.env` and fill in your database credentials, Gmail SMTP, Gemini API key, and AbstractAPI key.

4. **Setup Database:**
   - Create database `quiz_app` in phpMyAdmin or MySQL CLI:
     ```sql
     CREATE DATABASE quiz_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   - Import `database/quiz_app.sql`.
   - The app's auto-migrator (`config/migrate.php`) will automatically maintain any missing columns.

5. **Run Locally:**
   - **XAMPP:** Place in `htdocs` and visit `http://localhost/Quiz_app/`
   - **PHP CLI Server:**
     ```bash
     php -S localhost:8000 router.php
     ```

---

## ⚙️ 8. Environment Configuration (.env)

```ini
DB_HOST=127.0.0.1
DB_NAME=quiz_app
DB_USER=root
DB_PASS=
APP_URL=http://localhost/Quiz_app

# Google Gemini AI Key for Practice & Quiz Generator
GEMINI_API_KEY="your_gemini_api_key_here"

# SMTP Mailer for Email OTP & Password Resets
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_gmail_app_password
MAIL_FROM=your_email@gmail.com
SMTP_FROM_NAME="QuizApp"

# AbstractAPI Key for Live Mailbox Reputation & Deliverability
ABSTRACT_EMAIL_API_KEY="your_abstract_api_key_here"
```

---

## 🛣️ 9. RESTful Clean Routes Reference

### Auth Routes (Public / Guest)
| Method | Route | Controller & Action | Middleware |
|---|---|---|---|
| `GET` | `/login` | `AuthController@showLogin` | `guest` |
| `POST` | `/login` | `AuthController@login` | `guest` |
| `GET` | `/register` | `AuthController@showRegister` | `guest` |
| `POST` | `/register` | `AuthController@register` | `guest` |
| `GET` | `/verify-otp` | `AuthController@showVerifyOtp` | Public |
| `POST` | `/verify-otp` | `AuthController@verifyOtp` | Public |
| `GET` | `/forgot-password` | `AuthController@showForgotPassword` | `guest` |
| `POST` | `/forgot-password`| `AuthController@forgotPassword` | `guest` |
| `GET` | `/reset-password` | `AuthController@showResetPassword` | `guest` |
| `POST` | `/reset-password` | `AuthController@resetPassword` | `guest` |
| `GET` | `/logout` | `AuthController@logout` | Public |

### Student Routes (Auth Required)
| Method | Route | Controller & Action |
|---|---|---|
| `GET` | `/dashboard` | `DashboardController@index` |
| `GET` | `/quizzes` | `QuizController@index` |
| `GET` | `/quiz/take/{id}` | `QuizController@take` |
| `POST` | `/quiz/submit/{id}` | `QuizController@submit` |
| `POST` | `/quiz/tab-switch/{id}` | `QuizController@pingTabSwitch` |
| `GET` | `/quiz/result/{attemptId}` | `QuizController@result` |
| `GET` | `/my-attempts` | `QuizController@attempts` |
| `GET` | `/leaderboard` | `LeaderboardController@index` |
| `GET` | `/certificates` | `CertificateController@index` |
| `GET` | `/practice` | `PracticeController@practice` |
| `GET` | `/daily-quiz` | `PracticeController@daily` |
| `GET` | `/weak-topics` | `PracticeController@weakTopics` |
| `GET` | `/adaptive-quiz` | `PracticeController@adaptive` |
| `GET` | `/ai-practice` | `PracticeController@aiPractice` |
| `POST` | `/ai-practice` | `PracticeController@generateAi` |
| `GET` | `/api/topics-suggest` | `PracticeController@suggestTopics` |
| `GET` | `/profile` | `ProfileController@index` |
| `POST` | `/profile` | `ProfileController@update` |

### Admin Routes (Admin Role Required)
| Method | Route | Controller & Action |
|---|---|---|
| `GET` | `/admin` | `Admin\DashboardController@index` |
| `GET` | `/admin/quizzes` | `Admin\QuizController@index` |
| `POST` | `/admin/quizzes` | `Admin\QuizController@create` |
| `POST` | `/admin/quizzes/update/{id}` | `Admin\QuizController@update` |
| `POST` | `/admin/quizzes/delete/{id}` | `Admin\QuizController@delete` |
| `GET` | `/admin/questions` | `Admin\QuestionController@index` |
| `POST` | `/admin/questions` | `Admin\QuestionController@create` |
| `GET` | `/admin/questions/edit/{id}` | `Admin\QuestionController@edit` |
| `POST` | `/admin/questions/update/{id}` | `Admin\QuestionController@update` |
| `POST` | `/admin/questions/delete/{id}` | `Admin\QuestionController@delete` |
| `GET` | `/admin/categories` | `Admin\CategoryController@index` |
| `POST` | `/admin/categories` | `Admin\CategoryController@create` |
| `GET` | `/admin/users` | `Admin\UserController@index` |
| `POST` | `/admin/users/ban/{id}` | `Admin\UserController@toggleBan` |
| `GET` | `/admin/reports` | `Admin\ReportController@index` |
| `GET` | `/admin/ai-generator` | `Admin\AiGeneratorController@index` |
| `POST` | `/admin/ai-generator` | `Admin\AiGeneratorController@generate` |

---

## 🎯 10. Examiner & Viva Voce Q&A

### Q1: *"Why did you use MVC instead of plain PHP scripts?"*
> **Answer:** *"Sir, plain procedural PHP mixes HTML presentation, database queries, and validation in single files, leading to code duplication and maintenance challenges. With MVC, our codebase is strictly decoupled: Models encapsulate database entities and prepared queries, Controllers handle business logic and HTTP requests, and Views manage the user interface through master layouts. This makes the application clean, scalable, and easy to maintain."*

### Q2: *"How does URL rewriting and routing work in your app?"*
> **Answer:** *"Sir, we use Apache's `.htaccess` with `RewriteRule ^(.*)$ index.php [QSA,L]` to direct all traffic to our Front Controller `index.php`. Our `App\Core\Router` extracts the requested URI, matches it against pattern routes in `routes/web.php` (such as `/quiz/take/{id}`), extracts the dynamic parameters, checks middleware permissions (`auth`, `admin`, `guest`), and invokes the corresponding controller method."*

### Q3: *"How does your AI Practice and Typeahead Autocomplete work?"*
> **Answer:** *"Sir, when a user starts typing in the search box (e.g. `pho`), the frontend triggers a debounced request to `/api/topics-suggest?q=pho`. The backend searches across curated topics, categories, and tags, returning instant suggestions with category badges and icons with keyboard navigation support. When submitted, the backend sends a structured JSON schema prompt to Google's Gemini API (`gemini-2.5-flash-lite`), which parses the generated questions and presents them to the student with instant client-side grading."*

### Q4: *"How did you prevent fake emails and OTP bypass?"*
> **Answer:** *"Sir, typical applications only validate email regex syntax. We implemented a 3-layer validation system: First, standard syntax validation; second, live DNS MX lookup (`checkdnsrr`) combined with the Kickbox disposable domain API; third, live mailbox deliverability verification via AbstractAPI. If a user tries to enter a non-existent mailbox (e.g. `fake1234@gmail.com`), the API detects `undeliverable` / `invalid_mailbox` and blocks registration before any OTP is generated or sent."*

### Q5: *"How is anti-cheat tab detection implemented?"*
> **Answer:** *"Sir, in the quiz interface, we bind a JavaScript event listener to the HTML5 Page Visibility API (`document.addEventListener('visibilitychange')`). Whenever the student minimizes the browser or switches to another tab, a counter increments, an alert warns the student, and an AJAX request updates the `tab_switches` column in the database attempt record for the admin to inspect."*

---

## 📄 License
This project is open-source software licensed under the [MIT License](LICENSE).
