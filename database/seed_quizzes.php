<?php
require_once __DIR__ . '/../config/db.php';

$db = getDB();

echo "Seeding sample quizzes and questions...\n";

$seedData = [
    [
        'category' => 'Astronomy & Space',
        'title' => 'Cosmos & Planetary Science',
        'description' => 'Explore planets, stars, galaxies, and black holes across the universe.',
        'time_limit' => 600,
        'negative_marking' => 0.25,
        'questions' => [
            [
                'q' => 'Which planet is known as the Red Planet?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'Solar System',
                'opts' => ['Venus', 'Mars', 'Jupiter', 'Mercury'],
                'correct' => 1
            ],
            [
                'q' => 'What is the closest star to Earth outside our Solar System?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'Stars',
                'opts' => ['Proxima Centauri', 'Sirius', 'Betelgeuse', 'Alpha Centauri A'],
                'correct' => 0
            ],
            [
                'q' => 'What is the boundary around a black hole beyond which nothing can escape?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Black Holes',
                'opts' => ['Photon Sphere', 'Event Horizon', 'Accretion Disk', 'Singularity'],
                'correct' => 1
            ],
            [
                'q' => 'Which moon in the solar system is known to have a thick nitrogen-rich atmosphere and liquid methane lakes?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Moons',
                'opts' => ['Europa', 'Titan', 'Ganymede', 'Enceladus'],
                'correct' => 1
            ],
            [
                'q' => 'What is the theoretical maximum mass of a stable white dwarf star called?',
                'marks' => 3,
                'difficulty' => 'hard',
                'tag' => 'Astrophysics',
                'opts' => ['Oppenheimer-Volkoff Limit', 'Chandrasekhar Limit', 'Schwarzschild Radius', 'Hubble Limit'],
                'correct' => 1
            ],
        ]
    ],
    [
        'category' => 'Technology',
        'title' => 'Core Computer Science & Web Fundamentals',
        'description' => 'Test your knowledge of programming, algorithms, networks, and modern tech.',
        'time_limit' => 600,
        'negative_marking' => 0.25,
        'questions' => [
            [
                'q' => 'What does HTTP stand for?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'Web',
                'opts' => ['HyperText Transfer Protocol', 'High Technical Transfer Program', 'Hyperlink Text Tool Process', 'Hyper Tool Tracking Protocol'],
                'correct' => 0
            ],
            [
                'q' => 'Which data structure operates on a First-In, First-Out (FIFO) basis?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'Data Structures',
                'opts' => ['Stack', 'Queue', 'Binary Tree', 'Graph'],
                'correct' => 1
            ],
            [
                'q' => 'What is the time complexity of searching an element in a balanced Binary Search Tree (BST)?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Algorithms',
                'opts' => ['O(1)', 'O(n)', 'O(log n)', 'O(n log n)'],
                'correct' => 2
            ],
            [
                'q' => 'Which protocol is responsible for mapping IP addresses to MAC hardware addresses on a local network?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Networking',
                'opts' => ['DHCP', 'DNS', 'ARP', 'ICMP'],
                'correct' => 2
            ],
            [
                'q' => 'In database transaction management, what does the "I" in ACID properties stand for?',
                'marks' => 3,
                'difficulty' => 'hard',
                'tag' => 'Databases',
                'opts' => ['Integrity', 'Isolation', 'Inheritance', 'Idempotency'],
                'correct' => 1
            ],
        ]
    ],
    [
        'category' => 'General Knowledge',
        'title' => 'World Trivia & General Knowledge',
        'description' => 'Curated trivia questions covering geography, history, world records, and culture.',
        'time_limit' => 600,
        'negative_marking' => 0.00,
        'questions' => [
            [
                'q' => 'What is the largest ocean on Earth?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'Geography',
                'opts' => ['Atlantic Ocean', 'Indian Ocean', 'Pacific Ocean', 'Arctic Ocean'],
                'correct' => 2
            ],
            [
                'q' => 'Which country is home to the ancient ruins of Machu Picchu?',
                'marks' => 1,
                'difficulty' => 'easy',
                'tag' => 'World History',
                'opts' => ['Chile', 'Peru', 'Mexico', 'Colombia'],
                'correct' => 1
            ],
            [
                'q' => 'Who wrote the play "Hamlet"?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Literature',
                'opts' => ['Charles Dickens', 'William Shakespeare', 'Mark Twain', 'George Orwell'],
                'correct' => 1
            ],
            [
                'q' => 'What is the capital city of Australia?',
                'marks' => 2,
                'difficulty' => 'medium',
                'tag' => 'Geography',
                'opts' => ['Sydney', 'Melbourne', 'Canberra', 'Brisbane'],
                'correct' => 2
            ],
            [
                'q' => 'Which element has the highest electrical conductivity of all metals at room temperature?',
                'marks' => 3,
                'difficulty' => 'hard',
                'tag' => 'Science',
                'opts' => ['Gold', 'Copper', 'Silver', 'Aluminum'],
                'correct' => 2
            ],
        ]
    ]
];

foreach ($seedData as $quiz) {
    // Find category ID
    $catStmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $catStmt->execute([$quiz['category']]);
    $catId = $catStmt->fetchColumn();

    if (!$catId) {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $quiz['category']));
        $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$quiz['category'], $slug]);
        $catId = (int)$db->lastInsertId();
    }

    // Check if quiz already exists
    $chkQ = $db->prepare("SELECT id FROM quizzes WHERE title = ? AND category_id = ?");
    $chkQ->execute([$quiz['title'], $catId]);
    $quizId = $chkQ->fetchColumn();

    if (!$quizId) {
        $ins = $db->prepare("
            INSERT INTO quizzes (category_id, title, description, time_limit_seconds, total_marks, negative_marking, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $totalMarks = array_sum(array_column($quiz['questions'], 'marks'));
        $ins->execute([$catId, $quiz['title'], $quiz['description'], $quiz['time_limit'], $totalMarks, $quiz['negative_marking']]);
        $quizId = (int)$db->lastInsertId();
    }

    // Insert questions
    $insQ = $db->prepare("INSERT INTO questions (quiz_id, question_text, marks, difficulty, tag, order_index) VALUES (?, ?, ?, ?, ?, ?)");
    $insOpt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

    foreach ($quiz['questions'] as $i => $q) {
        $chkQuest = $db->prepare("SELECT id FROM questions WHERE quiz_id = ? AND question_text = ?");
        $chkQuest->execute([$quizId, $q['q']]);
        if (!$chkQuest->fetchColumn()) {
            $insQ->execute([$quizId, $q['q'], $q['marks'], $q['difficulty'], $q['tag'], $i + 1]);
            $qId = (int)$db->lastInsertId();

            foreach ($q['opts'] as $optIdx => $optText) {
                $insOpt->execute([$qId, $optText, $optIdx === $q['correct'] ? 1 : 0]);
            }
        }
    }
}

echo "Seeding completed successfully!\n";
