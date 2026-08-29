<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Browse Quizzes</h1>
        <p class="page-subtitle">Select a quiz to test your knowledge</p>
    </div>
</div>

<!-- Search & Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="row g-3 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search quiz title or topic..." value="<?= htmlspecialchars($search ?? '') ?>">
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= ($selectedCategory ?? '') === $cat['slug'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Filter</button>
                <?php if (!empty($search) || !empty($selectedCategory)): ?>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Quiz Grid -->
<?php if (empty($quizzes)): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <i class="bi bi-journal-x text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No quizzes found</h5>
        <p class="text-muted">Try adjusting your search criteria or check back later.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($quizzes as $quiz): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-light text-primary border"><?= htmlspecialchars($quiz['category_name'] ?? 'General') ?></span>
                        <?php if (!empty($quiz['attempt_id'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Completed</span>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($quiz['title']) ?></h5>
                    <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($quiz['description'] ?? 'No description available.') ?></p>
                    
                    <div class="d-flex justify-content-between align-items-center text-muted small mb-3 py-2 border-top border-bottom">
                        <span><i class="bi bi-patch-question me-1"></i><?= (int)$quiz['question_count'] ?> Questions</span>
                        <span><i class="bi bi-clock me-1"></i><?= (int)$quiz['time_limit_minutes'] ?> Mins</span>
                    </div>

                    <?php if (!empty($quiz['attempt_id'])): ?>
                        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/result/<?= $quiz['attempt_id'] ?>" class="btn btn-outline-success w-100">
                            <i class="bi bi-eye me-1"></i>View My Result
                        </a>
                    <?php else: ?>
                        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/take/<?= $quiz['id'] ?>" class="btn btn-primary w-100">
                            <i class="bi bi-play-circle me-1"></i>Start Quiz
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
