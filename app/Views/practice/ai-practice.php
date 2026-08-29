<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">AI Practice Generator</h1>
            <p class="page-subtitle">Generate custom practice questions on any topic</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Practice
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 p-4 mb-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-stars text-primary me-2"></i>Generate Practice Questions</h5>
    <form id="aiPracticeForm" class="row g-3">
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Subject / Topic</label>
            <input type="text" id="aiTopic" class="form-control" placeholder="e.g. Python List Comprehensions, SQL Joins, Organic Chemistry" value="<?= htmlspecialchars($_GET['topic'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Difficulty</label>
            <select id="aiDifficulty" class="form-select">
                <option value="easy">Easy</option>
                <option value="medium" selected>Medium</option>
                <option value="hard">Hard</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cpu me-1"></i>Generate</button>
        </div>
    </form>
</div>

<div id="aiContainer" style="display: none;">
    <div class="card shadow-sm border-0 p-4">
        <div id="aiQuestionsList"></div>
    </div>
</div>
