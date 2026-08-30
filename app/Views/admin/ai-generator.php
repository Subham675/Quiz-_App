<div class="mb-4">
    <h1 class="page-title">AI Quiz &amp; Question Generator</h1>
    <p class="page-subtitle">Generate questions automatically using Google Gemini AI</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger shadow-sm mb-3"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success shadow-sm mb-3"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator" id="adminAiForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="quiz_id" id="quizIdHidden" value="<?= isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : '' ?>">

            <div class="row g-3">
                <!-- Target Quiz: User Input + Suggestions -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">
                        Target Quiz <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="quiz_title" 
                        id="quizTitleInput" 
                        class="form-control" 
                        placeholder="Type a quiz name or pick from list..." 
                        list="quizSuggestions"
                        autocomplete="off"
                        required
                    >
                    <datalist id="quizSuggestions">
                        <?php foreach ($quizzes as $qz): ?>
                            <option 
                                value="<?= htmlspecialchars($qz['title']) ?>" 
                                data-id="<?= $qz['id'] ?>" 
                                data-category="<?= htmlspecialchars($qz['category_name'] ?? '') ?>"
                            >
                                <?= htmlspecialchars($qz['category_name'] ?? 'General') ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text small text-muted">
                        Pick an existing quiz or type a new quiz title (auto-created if new).
                    </div>
                </div>

                <!-- Topic / Concept: User Input with Live API Concept Suggestions -->
                <div class="col-md-6 position-relative">
                    <label class="form-label fw-semibold small">
                        Topic / Concept <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input 
                            type="text" 
                            name="topic" 
                            id="topicInput" 
                            class="form-control" 
                            placeholder="Type any topic (e.g. Photosynthesis, React, World War II...)" 
                            list="topicSuggestions"
                            autocomplete="off"
                            required
                        >
                        <span class="input-group-text bg-white border-start-0 text-muted" id="conceptLoadingSpinner" style="display: none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                    <datalist id="topicSuggestions">
                        <!-- Dynamically filled via API -->
                    </datalist>
                    <div class="form-text small text-muted" id="topicHelpText">
                        Type a topic to automatically fetch and suggest related concepts via API.
                    </div>
                </div>

                <!-- Live API Concept Suggestions (Click to fill) -->
                <div class="col-12" id="conceptSuggestionsContainer" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="small fw-semibold text-muted">
                            <i class="bi bi-stars text-warning me-1"></i>Related Concepts from API (Click to select):
                        </div>
                        <span class="badge bg-light text-secondary border small" id="conceptCountBadge">6 concepts</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2" id="conceptChipsWrapper"></div>
                </div>

                <!-- Number of Questions -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Number of Questions</label>
                    <input 
                        type="number" 
                        name="count" 
                        class="form-control" 
                        min="1" 
                        max="50" 
                        value="5" 
                        list="countSuggestions" 
                        placeholder="Type any number or pick from list (1-50)"
                        required
                    >
                    <datalist id="countSuggestions">
                        <option value="3">3 Questions (Quick Sprint)</option>
                        <option value="5">5 Questions (Standard)</option>
                        <option value="10">10 Questions</option>
                        <option value="15">15 Questions</option>
                        <option value="20">20 Questions</option>
                        <option value="25">25 Questions</option>
                        <option value="30">30 Questions</option>
                        <option value="50">50 Questions (Max)</option>
                    </datalist>
                </div>

                <!-- Difficulty Level -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Difficulty Level</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy">Easy / Beginner</option>
                        <option value="medium" selected>Medium / Intermediate</option>
                        <option value="hard">Hard / Advanced</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="col-12 mt-4 text-end">
                    <button type="submit" id="submitBtn" class="btn btn-primary px-4">
                        <i class="bi bi-stars me-1"></i>Generate &amp; Insert Questions
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.concept-chip {
    transition: all 0.15s ease-in-out;
}
.concept-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const basePath = '<?= defined('BASE_PATH') ? BASE_PATH : '' ?>';
    const quizTitleInput = document.getElementById('quizTitleInput');
    const quizIdHidden = document.getElementById('quizIdHidden');
    const topicInput = document.getElementById('topicInput');
    const topicSuggestions = document.getElementById('topicSuggestions');
    const topicHelpText = document.getElementById('topicHelpText');
    const conceptLoadingSpinner = document.getElementById('conceptLoadingSpinner');
    const conceptContainer = document.getElementById('conceptSuggestionsContainer');
    const conceptChipsWrapper = document.getElementById('conceptChipsWrapper');
    const conceptCountBadge = document.getElementById('conceptCountBadge');

    let debounceTimer = null;
    let lastFetchedQuery = '';

    // Existing Quizzes from PHP
    const quizzesData = <?= json_encode(array_map(function($q) {
        return [
            'id' => (int)$q['id'],
            'title' => $q['title'],
            'category' => $q['category_name'] ?? '',
        ];
    }, $quizzes)) ?>;

    // Fetch related concepts via API
    function fetchRelatedConcepts(topic) {
        const query = topic.trim();
        if (query.length < 2) {
            conceptContainer.style.display = 'none';
            return;
        }

        if (query === lastFetchedQuery) return;
        lastFetchedQuery = query;

        const quizTitle = quizTitleInput.value.trim();
        conceptLoadingSpinner.style.display = 'block';

        fetch(`${basePath}/api/concept-suggestions?topic=${encodeURIComponent(query)}&quiz=${encodeURIComponent(quizTitle)}`)
            .then(res => res.json())
            .then(data => {
                conceptLoadingSpinner.style.display = 'none';
                if (data.success && data.concepts && data.concepts.length > 0) {
                    renderConceptSuggestions(data.concepts, data.topic);
                } else {
                    conceptContainer.style.display = 'none';
                }
            })
            .catch(() => {
                conceptLoadingSpinner.style.display = 'none';
            });
    }

    function renderConceptSuggestions(concepts, topicName) {
        // Update Datalist
        topicSuggestions.innerHTML = '';
        concepts.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            topicSuggestions.appendChild(opt);
        });

        // Update Quick Clickable Chips
        conceptChipsWrapper.innerHTML = '';
        concepts.forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary concept-chip bg-white';
            btn.innerHTML = `<i class="bi bi-check2-circle me-1"></i>${escapeHtml(c)}`;
            btn.addEventListener('click', function () {
                topicInput.value = c;
                topicHelpText.textContent = `Selected concept: "${c}"`;
            });
            conceptChipsWrapper.appendChild(btn);
        });

        conceptCountBadge.textContent = `${concepts.length} concepts`;
        conceptContainer.style.display = 'block';
        topicHelpText.textContent = `Showing API concepts for "${topicName}"`;
    }

    // Debounced listener on Topic input
    topicInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const val = this.value;
        debounceTimer = setTimeout(() => {
            fetchRelatedConcepts(val);
        }, 400);
    });

    // When Target Quiz changes, sync and optionally refresh concept suggestions
    quizTitleInput.addEventListener('change', function () {
        const val = this.value.trim();
        const found = quizzesData.find(q => q.title.toLowerCase() === val.toLowerCase());
        if (found) {
            quizIdHidden.value = found.id;
        } else {
            quizIdHidden.value = '';
        }

        if (topicInput.value.trim() === '' && val !== '') {
            // Suggest concepts based on the quiz title if topic is still blank
            fetchRelatedConcepts(val);
        }
    });

    // Auto-select quiz if query param present
    const preselectedQuizId = parseInt(quizIdHidden.value, 10);
    if (preselectedQuizId > 0) {
        const found = quizzesData.find(q => q.id === preselectedQuizId);
        if (found) {
            quizTitleInput.value = found.title;
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    document.getElementById('adminAiForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating...';
    });
});
</script>
