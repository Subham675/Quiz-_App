<?php
/**
 * Safe database auto-migration helper.
 * Runs on demand or ensures all new columns and tables exist.
 */
function runMigrations(PDO $db): void
{
    static $migrated = false;
    if ($migrated) return;

    $columnsToCheck = [
        'users' => [
            'current_streak'   => 'INT DEFAULT 0',
            'longest_streak'   => 'INT DEFAULT 0',
            'last_active_date' => 'DATE DEFAULT NULL',
            'is_deleted'       => 'TINYINT(1) DEFAULT 0',
            'deleted_at'       => 'DATETIME DEFAULT NULL',
        ],
        'categories' => [
            'deleted_at'       => 'DATETIME DEFAULT NULL',
        ],
        'quizzes' => [
            'negative_marking' => 'DECIMAL(4,2) DEFAULT 0.00',
            'starts_at'        => 'DATETIME DEFAULT NULL',
            'ends_at'          => 'DATETIME DEFAULT NULL',
            'deleted_at'       => 'DATETIME DEFAULT NULL',
        ],
        'questions' => [
            'difficulty'       => "ENUM('easy', 'medium', 'hard') DEFAULT 'medium'",
            'tag'              => 'VARCHAR(100) DEFAULT NULL',
            'times_attempted'  => 'INT DEFAULT 0',
            'times_correct'    => 'INT DEFAULT 0',
            'is_flagged'       => 'TINYINT(1) DEFAULT 0',
            'flag_reason'      => 'VARCHAR(255) DEFAULT NULL',
            'deleted_at'       => 'DATETIME DEFAULT NULL',
        ],
        'attempts' => [
            'tab_switch_count' => 'INT DEFAULT 0',
        ],
        'attempt_answers' => [
            'explanation'      => 'TEXT DEFAULT NULL',
        ],
    ];

    foreach ($columnsToCheck as $table => $columns) {
        foreach ($columns as $column => $definition) {
            try {
                $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                if ($check && $check->rowCount() === 0) {
                    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            } catch (Exception $e) {
                // Table might not exist yet or column already present
            }
        }
    }

    // Create email_queue table if not exists
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(150) NOT NULL,
            to_name VARCHAR(100) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('pending','sent','failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    // Create rate_limits table if not exists
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 0,
            last_attempt DATETIME DEFAULT NULL,
            blocked_until DATETIME DEFAULT NULL,
            UNIQUE KEY unique_ip_action (ip, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}

    // Ensure attempts.score can store decimals if negative marking is used
    try {
        $db->exec("ALTER TABLE attempts MODIFY COLUMN score DECIMAL(6,2) DEFAULT 0.00");
    } catch (Exception $e) {}

    // Seed expanded category list if missing
    $defaultCategories = [
        ['General Knowledge', 'general-knowledge', 'Test your everyday knowledge'],
        ['Science', 'science', 'Physics, Chemistry, Biology and natural sciences'],
        ['Mathematics', 'mathematics', 'Numbers, equations, algebra, and logic'],
        ['Technology', 'technology', 'Computers, programming, software, AI, and internet'],
        ['History', 'history', 'World, ancient, medieval, and modern history'],
        ['Politics', 'politics', 'Government, political theory, constitution, and public policy'],
        ['Geography', 'geography', 'Countries, capitals, rivers, mountains, and world maps'],
        ['Sports', 'sports', 'Cricket, Football, Olympics, rules, and tournaments'],
        ['Literature & Language', 'literature-language', 'Grammar, vocabulary, famous books, and authors'],
        ['Art & Culture', 'art-culture', 'Painting, heritage, world traditions, and architecture'],
        ['Economics & Business', 'economics-business', 'Finance, markets, trade, startups, and economy'],
        ['Entertainment & Movies', 'entertainment-movies', 'Cinema, television, pop culture, and trivia'],
        ['Music', 'music', 'Instruments, genres, composers, and songs'],
        ['Philosophy', 'philosophy', 'Ethics, logic, renowned thinkers, and philosophies'],
        ['Medicine & Health', 'medicine-health', 'Anatomy, fitness, wellness, and medical science'],
        ['Environment & Ecology', 'environment-ecology', 'Climate, wildlife, conservation, and nature'],
        ['Law & Constitution', 'law-constitution', 'Legal systems, rights, justice, and jurisprudence'],
        ['Current Affairs', 'current-affairs', 'Recent news, international events, and modern developments'],
        ['Psychology', 'psychology', 'Human behavior, cognitive science, and mental processes'],
        ['Astronomy & Space', 'astronomy-space', 'Planets, galaxies, astrophysics, and space missions'],
    ];

    try {
        $catStmt = $db->prepare("INSERT IGNORE INTO categories (name, slug, description) VALUES (?, ?, ?)");
        foreach ($defaultCategories as $cat) {
            $catStmt->execute($cat);
        }
    } catch (Exception $e) {}

    $migrated = true;
}
