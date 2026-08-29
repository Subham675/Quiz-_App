================================================================================
                          QUIZAPP - ONLINE QUIZ PLATFORM
================================================================================

  A full-featured, modern Online Quiz Application built with PHP 8, MySQL,
  Bootstrap 5, and a custom MVC framework. Designed for students to take
  quizzes, track progress, and earn certificates, and for admins to manage
  all content from a powerful dashboard.

  Repository : https://github.com/Subham675/Quiz-_App
  Author     : Subham
  License    : MIT
  PHP        : >= 8.0
  Database   : MySQL / MariaDB

================================================================================
                              TABLE OF CONTENTS
================================================================================

  1. Features
  2. Tech Stack
  3. Project Structure
  4. Installation & Setup
  5. Environment Variables
  6. Database Setup
  7. Running the Application
  8. MVC Architecture
  9. API Routes
  10. Email Validation
  11. Security Features
  12. Screenshots
  13. Contributing
  14. License

================================================================================
  1. FEATURES
================================================================================

  STUDENT FEATURES:
  -----------------
  * User registration with email OTP verification
  * Secure login with rate limiting and brute-force protection
  * Interactive quiz-taking interface with countdown timer
  * Real-time tab-switch detection (anti-cheating)
  * Detailed quiz results with correct/incorrect answer breakdown
  * Quiz attempt history with performance tracking
  * Student dashboard with progress stats and streak tracking
  * Leaderboard (all-time, monthly, weekly rankings)
  * PDF certificate generation for scores >= 60%
  * Practice modes:
      - Daily Quiz (streak-based challenges)
      - Weak Topics (targets concepts under 70% accuracy)
      - Adaptive Quiz (adjusts difficulty dynamically)
      - AI Practice (Google Gemini powered with Search Autocomplete & Suggestions)
  * Search Autocomplete & Suggestions (Typeahead):
      - Instant topic search with real-time substring matching
      - Type "pho" -> suggests "Photography", "Photosynthesis", etc.
      - Keyboard navigation (Arrow keys + Enter) & quick topic chips
  * Profile management with password change
  * Forgot password with email reset link

  ADMIN FEATURES:
  ----------------
  * Admin dashboard with platform metrics
  * Quiz management (CRUD) with time limits, negative marking
  * Question management with multiple-choice options
  * Category management with slugs
  * Student management (view, ban/unban, delete)
  * Detailed analytics and reports
  * Per-student performance reports
  * Attempt detail breakdown
  * AI Quiz Generator powered by Google Gemini API

================================================================================
  2. TECH STACK
================================================================================

  Backend:
  --------
  * PHP 8.0+          - Core language
  * Custom MVC        - Lightweight framework with Router, Controller, Model, View
  * PDO               - Database access with prepared statements
  * PHPMailer 6.8     - SMTP email delivery
  * TCPDF 6.6         - PDF certificate generation
  * vlucas/phpdotenv  - Environment variable management
  * Composer          - Dependency management with PSR-4 autoloading

  Frontend:
  ---------
  * Bootstrap 5.3     - Responsive UI framework
  * Chart.js          - Data visualization and analytics charts
  * Font Awesome 6    - Icon library
  * Google Fonts      - Typography (Inter, Outfit)
  * Vanilla JS        - Quiz timer, tab detection, AJAX interactions

  Database:
  ---------
  * MySQL 5.7+ / MariaDB 10.4+

  External APIs:
  --------------
  * Google Gemini API           - AI question generation
  * AbstractAPI Email Reputation - Email deliverability validation
  * Kickbox Open API            - Disposable email detection

================================================================================
  3. PROJECT STRUCTURE
================================================================================

  Quiz-_App/
  |
  |-- app/                          # MVC Application Code
  |   |-- Core/                     # Framework Engine
  |   |   |-- Router.php            # URL routing with middleware
  |   |   |-- Controller.php        # Base controller (render, json, redirect)
  |   |   |-- Model.php             # Base model (PDO query helpers)
  |   |   |-- Request.php           # HTTP request, input sanitization, CSRF
  |   |   |-- View.php              # Template renderer with layouts
  |   |
  |   |-- Controllers/              # Application Controllers
  |   |   |-- AuthController.php    # Login, register, OTP, password reset
  |   |   |-- DashboardController.php
  |   |   |-- QuizController.php    # Browse, take, submit, results
  |   |   |-- PracticeController.php# Daily, weak topics, adaptive, AI
  |   |   |-- CertificateController.php
  |   |   |-- LeaderboardController.php
  |   |   |-- ProfileController.php
  |   |   |-- Admin/                # Admin Controllers
  |   |       |-- DashboardController.php
  |   |       |-- QuizController.php
  |   |       |-- QuestionController.php
  |   |       |-- CategoryController.php
  |   |       |-- UserController.php
  |   |       |-- ReportController.php
  |   |       |-- AiGeneratorController.php
  |   |
  |   |-- Models/                   # Data Access Objects
  |   |   |-- User.php
  |   |   |-- Quiz.php
  |   |   |-- Question.php
  |   |   |-- Attempt.php
  |   |   |-- Certificate.php
  |   |   |-- Category.php
  |   |
  |   |-- Views/                    # Presentation Layer
  |       |-- layouts/
  |       |   |-- main.php          # Student master layout
  |       |   |-- admin.php         # Admin master layout
  |       |-- auth/                 # Login, register, OTP, password views
  |       |-- dashboard/            # Student dashboard
  |       |-- quiz/                 # Quiz list, take, result, attempts
  |       |-- practice/             # Daily, weak topics, adaptive, AI
  |       |-- certificates/         # Certificate gallery
  |       |-- leaderboard/          # Student rankings
  |       |-- profile/              # Account settings
  |       |-- admin/                # All admin panel views
  |       |-- errors/               # 404 error page
  |
  |-- assets/                       # Static Assets (CSS, JS, images)
  |-- config/
  |   |-- db.php                    # Database connection & .env loader
  |   |-- migrate.php               # Auto-migration system
  |-- cron/                         # Scheduled tasks
  |-- database/
  |   |-- quiz_app.sql              # Database schema
  |   |-- seed_quizzes.php          # Sample data seeder
  |-- includes/
  |   |-- auth.php                  # Auth helpers, email validation, OTP, CSRF
  |   |-- rate_limiter.php          # Rate limiting for login/register/OTP
  |   |-- mailer.php                # PHPMailer SMTP configuration
  |-- public/                       # Legacy public scripts (procedural)
  |-- routes/
  |   |-- web.php                   # All route definitions
  |-- uploads/                      # User uploaded files
  |
  |-- index.php                     # Front Controller (entry point)
  |-- .htaccess                     # Apache URL rewriting
  |-- router.php                    # PHP built-in server router
  |-- composer.json                 # Composer dependencies & PSR-4
  |-- .env                          # Environment variables (not committed)
  |-- .env.example                  # Environment template
  |-- .gitignore                    # Git ignore rules
  |-- README.txt                    # This file

================================================================================
  4. INSTALLATION & SETUP
================================================================================

  Prerequisites:
  --------------
  * PHP 8.0 or higher
  * MySQL 5.7+ or MariaDB 10.4+
  * Apache with mod_rewrite OR PHP built-in server
  * Composer (https://getcomposer.org)
  * XAMPP / WAMP / LAMP (recommended for local development)

  Steps:
  ------
  1. Clone the repository:

     git clone https://github.com/Subham675/Quiz-_App.git
     cd Quiz-_App

  2. Install PHP dependencies:

     composer install

  3. Create the environment file:

     Copy .env.example to .env and fill in your values:

     cp .env.example .env        (Linux/Mac)
     copy .env.example .env      (Windows)

  4. Configure your .env file (see Section 5 below).

  5. Create the database:

     Open phpMyAdmin or MySQL CLI and create:

     CREATE DATABASE quiz_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

  6. Import the schema:

     mysql -u root quiz_app < database/quiz_app.sql

     Or import database/quiz_app.sql via phpMyAdmin.

  7. The application auto-migrates on first request (adds any missing
     columns/tables automatically via config/migrate.php).

================================================================================
  5. ENVIRONMENT VARIABLES
================================================================================

  Create a .env file in the project root with these values:

  +---------------------------+------------------------------------------+
  | Variable                  | Description                              |
  +---------------------------+------------------------------------------+
  | DB_HOST                   | Database host (default: 127.0.0.1)       |
  | DB_NAME                   | Database name (default: quiz_app)        |
  | DB_USER                   | Database username (default: root)        |
  | DB_PASS                   | Database password (default: empty)       |
  | APP_URL                   | Full application URL                     |
  |                           | e.g. http://localhost/Quiz_app            |
  | GEMINI_API_KEY            | Google Gemini API key for AI features    |
  | SMTP_HOST                 | SMTP server (e.g. smtp.gmail.com)        |
  | SMTP_PORT                 | SMTP port (default: 587)                 |
  | SMTP_USER                 | SMTP email address                       |
  | SMTP_PASS                 | SMTP app password                        |
  | MAIL_FROM                 | From email address                       |
  | SMTP_FROM_NAME            | From display name (e.g. "QuizApp")       |
  | ABSTRACT_EMAIL_API_KEY    | AbstractAPI email validation key         |
  +---------------------------+------------------------------------------+

  NOTE: For Gmail SMTP, you need to generate an App Password:
        Google Account > Security > 2-Step Verification > App Passwords

================================================================================
  6. DATABASE SETUP
================================================================================

  The database schema is in database/quiz_app.sql.

  Core Tables:
  ------------
  * users              - User accounts, roles, verification status
  * quizzes            - Quiz definitions with time limits, settings
  * questions          - Multiple choice questions with options
  * categories         - Quiz categories with slugs
  * quiz_attempts      - Student attempt records with scores
  * attempt_answers    - Individual answer records per attempt
  * certificates       - Generated certificates for passing scores
  * rate_limits        - Rate limiting records for security

  Auto-Migration:
  ---------------
  The app automatically checks and adds missing columns on startup
  via config/migrate.php. No manual ALTER TABLE needed.

================================================================================
  7. RUNNING THE APPLICATION
================================================================================

  Option A: XAMPP / Apache (Recommended)
  ----------------------------------------
  1. Place the Quiz-_App folder in C:\xampp\htdocs\ (or create a symlink)
  2. Start Apache and MySQL from the XAMPP Control Panel
  3. Visit: http://localhost/Quiz-_App/

  Option B: PHP Built-in Server
  ------------------------------
  Run from the project root:

     php -S localhost:8000 router.php

  Then visit: http://localhost:8000/

  Option C: Apache Virtual Host
  ------------------------------
  Add to your Apache httpd-vhosts.conf:

     <VirtualHost *:80>
         DocumentRoot "C:/path/to/Quiz-_App"
         ServerName quizapp.local
         <Directory "C:/path/to/Quiz-_App">
             AllowOverride All
             Require all granted
         </Directory>
     </VirtualHost>

  Then visit: http://quizapp.local/

================================================================================
  8. MVC ARCHITECTURE
================================================================================

  The application follows a clean Model-View-Controller pattern:

  Request Flow:
  -------------
  Browser Request
      |
      v
  index.php (Front Controller)
      |
      v
  .htaccess (URL Rewriting) --> routes/web.php (Route Definitions)
      |
      v
  App\Core\Router (Route Matching + Middleware)
      |
      v
  App\Controllers\*Controller (Business Logic)
      |
      v
  App\Models\* (Database Queries via PDO)
      |
      v
  App\Core\View (Template Rendering)
      |
      v
  App\Views\layouts\*.php + App\Views\*\*.php
      |
      v
  HTML Response to Browser

  Middleware:
  ----------
  * 'auth'   - Requires logged-in user, redirects to /login
  * 'admin'  - Requires admin role, redirects to /login
  * 'guest'  - Only for non-logged-in users, redirects to /dashboard

================================================================================
  9. API ROUTES
================================================================================

  AUTH ROUTES:
  GET  /login                        - Login page
  POST /login                        - Process login
  GET  /register                     - Registration page
  POST /register                     - Process registration
  GET  /verify-otp                   - OTP verification page
  POST /verify-otp                   - Verify OTP code
  GET  /forgot-password              - Forgot password page
  POST /forgot-password              - Send reset email
  GET  /reset-password               - Reset password page
  POST /reset-password               - Process password reset
  GET  /logout                       - Logout

  STUDENT ROUTES (requires auth):
  GET  /dashboard                    - Student dashboard
  GET  /quizzes                      - Browse available quizzes
  GET  /quiz/take/{id}               - Take a quiz
  POST /quiz/submit/{id}             - Submit quiz answers
  POST /quiz/tab-switch/{id}         - Report tab switch (anti-cheat)
  GET  /quiz/result/{attemptId}      - View quiz result
  GET  /my-attempts                  - View attempt history
  GET  /certificates                 - View earned certificates
  GET  /leaderboard                  - View rankings
  GET  /profile                      - Profile settings
  POST /profile                      - Update profile

  PRACTICE ROUTES (requires auth):
  GET  /practice                     - Practice hub
  GET  /daily-quiz                   - Daily streak quiz
  GET  /weak-topics                  - Weak topic practice
  GET  /adaptive-quiz                - Adaptive difficulty quiz
  GET  /ai-practice                  - AI-generated practice

  ADMIN ROUTES (requires admin):
  GET  /admin                        - Admin dashboard
  GET  /admin/quizzes                - Manage quizzes
  POST /admin/quizzes                - Create quiz
  POST /admin/quizzes/update/{id}    - Update quiz
  POST /admin/quizzes/delete/{id}    - Delete quiz
  GET  /admin/questions              - Manage questions
  POST /admin/questions              - Create question
  GET  /admin/questions/edit/{id}    - Edit question form
  POST /admin/questions/update/{id}  - Update question
  POST /admin/questions/delete/{id}  - Delete question
  GET  /admin/categories             - Manage categories
  POST /admin/categories             - Create category
  POST /admin/categories/update/{id} - Update category
  POST /admin/categories/delete/{id} - Delete category
  GET  /admin/users                  - Manage students
  POST /admin/users/ban/{id}         - Ban/unban student
  POST /admin/users/delete/{id}      - Delete student
  GET  /admin/users/report/{id}      - Student performance report
  GET  /admin/reports                - Analytics & reports
  GET  /admin/reports/attempt/{id}   - Attempt detail breakdown
  GET  /admin/ai-generator           - AI quiz generator page
  POST /admin/ai-generator           - Generate AI questions

================================================================================
  10. EMAIL VALIDATION
================================================================================

  The application uses a 3-layer email validation system:

  Layer 1: Format Validation
  --------------------------
  * PHP filter_var() with FILTER_VALIDATE_EMAIL
  * Rejects malformed email syntax

  Layer 2: Domain & DNS Validation (isFakeEmail)
  ------------------------------------------------
  * DNS MX record lookup via checkdnsrr()
  * Kickbox Open API for disposable domain detection
  * Rejects domains that cannot receive email

  Layer 3: Mailbox Deliverability (isDeliverableEmail)
  -----------------------------------------------------
  * AbstractAPI Email Reputation endpoint
  * Verifies the specific mailbox exists (catches random@gmail.com)
  * Checks SMTP validity, disposable status, and risk score
  * API Key configured via ABSTRACT_EMAIL_API_KEY in .env

  This validation runs on:
  * User registration (before sending OTP)
  * User login (before authentication)
  * Forgot password (before sending reset link)

================================================================================
  11. SECURITY FEATURES
================================================================================

  * CSRF Token Protection      - All forms include hidden CSRF tokens
  * Rate Limiting               - Login, register, OTP, forgot password
  * Password Hashing            - bcrypt via password_hash()
  * Prepared Statements         - All SQL queries use PDO prepared statements
  * Input Sanitization          - htmlspecialchars() and strip_tags()
  * Session Security            - Regenerated session IDs on login
  * Email Verification          - 6-digit OTP required before access
  * Anti-Cheat Tab Detection    - Monitors browser tab switches during quizzes
  * Ban System                  - Admins can suspend abusive accounts
  * Brute Force Protection      - Progressive lockout (5 min, 10 min, 15 min)

================================================================================
  12. SCREENSHOTS
================================================================================

  Visit the live application or clone the repository to see:

  * Student Dashboard         - Progress stats, streaks, recommendations
  * Quiz Interface            - Full-screen quiz with timer
  * Results Page              - Score breakdown with correct answers
  * Admin Dashboard           - Platform metrics and analytics
  * Certificate Gallery       - PDF certificates for passing students
  * Leaderboard               - Weekly, monthly, all-time rankings
  * AI Quiz Generator         - Google Gemini powered question generation

================================================================================
  13. CONTRIBUTING
================================================================================

  1. Fork the repository
  2. Create your feature branch:   git checkout -b feature/my-feature
  3. Commit your changes:          git commit -m "Add my feature"
  4. Push to the branch:           git push origin feature/my-feature
  5. Open a Pull Request

  Please follow the existing code style and MVC architecture patterns.

================================================================================
  14. LICENSE
================================================================================

  This project is open source and available under the MIT License.

  Copyright (c) 2026 Subham

  Permission is hereby granted, free of charge, to any person obtaining
  a copy of this software and associated documentation files (the
  "Software"), to deal in the Software without restriction, including
  without limitation the rights to use, copy, modify, merge, publish,
  distribute, sublicense, and/or sell copies of the Software, and to
  permit persons to whom the Software is furnished to do so, subject
  to the following conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.

================================================================================
                              END OF README
================================================================================
