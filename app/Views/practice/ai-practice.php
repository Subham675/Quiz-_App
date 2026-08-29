<div class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="page-title d-flex align-items-center gap-2">
                <i class="bi bi-robot text-primary"></i>
                AI Practice Generator
            </h1>
            <p class="page-subtitle mb-0">Generate instant, interactive practice questions on any topic powered by Google Gemini AI</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Practice
        </a>
    </div>
</div>

<!-- Generator Card -->
<div class="card shadow-sm border-0 mb-4" id="generatorCard">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-stars text-warning me-2"></i>Configure Practice Session
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                <i class="bi bi-search me-1"></i>Search Autocomplete &amp; Suggestions
            </span>
        </div>

        <form id="aiPracticeForm" autocomplete="off">
            <div class="row g-3">
                <!-- Topic Input with Autocomplete -->
                <div class="col-lg-6 position-relative">
                    <label class="form-label fw-semibold small text-secondary">
                        Subject / Topic <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input 
                            type="text" 
                            id="aiTopic" 
                            name="topic"
                            class="form-control border-start-0 ps-0" 
                            placeholder="Type a topic (e.g. Photography, Photosynthesis, Python...)" 
                            value="<?= htmlspecialchars($initialTopic ?? '') ?>" 
                            required
                            autocomplete="off"
                        >
                    </div>

                    <!-- Autocomplete Dropdown List -->
                    <div id="typeaheadDropdown" class="dropdown-menu w-100 shadow-lg border-0 mt-1 py-1" style="display: none; max-height: 280px; overflow-y: auto; z-index: 1050;">
                        <div class="dropdown-header small text-uppercase fw-bold text-muted px-3 py-1">
                            <i class="bi bi-lightning-charge me-1"></i>Suggested Topics (Typeahead)
                        </div>
                        <div id="typeaheadItems"></div>
                    </div>
                </div>

                <!-- Number of Questions -->
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold small text-secondary">Number of Questions</label>
                    <select id="aiCount" name="count" class="form-select">
                        <option value="3">3 Questions (Quick Sprint)</option>
                        <option value="5" selected>5 Questions (Standard Practice)</option>
                        <option value="10">10 Questions (Deep Dive)</option>
                    </select>
                </div>

                <!-- Difficulty -->
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold small text-secondary">Difficulty Level</label>
                    <select id="aiDifficulty" name="difficulty" class="form-select">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <!-- Quick Topic Chips -->
            <div class="mt-3">
                <div class="small fw-semibold text-muted mb-2">
                    <i class="bi bi-lightbulb me-1"></i>Popular Suggestions (Click to fill):
                </div>
                <div class="d-flex flex-wrap gap-2" id="quickTopicChips">
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Photography">
                        <i class="bi bi-camera me-1 text-primary"></i>Photography
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Photosynthesis & Plant Biology">
                        <i class="bi bi-flower1 me-1 text-success"></i>Photosynthesis
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Python Programming">
                        <i class="bi bi-code-slash me-1 text-info"></i>Python
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Physics: Optics & Mechanics">
                        <i class="bi bi-atom me-1 text-warning"></i>Physics
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Indian Constitution & Polity">
                        <i class="bi bi-shield-check me-1 text-danger"></i>Constitution
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="Astronomy & Space Exploration">
                        <i class="bi bi-moon-stars me-1 text-purple"></i>Astronomy
                    </button>
                    <button type="button" class="btn btn-sm btn-light border topic-chip" data-topic="World History & Civilizations">
                        <i class="bi bi-bank me-1 text-secondary"></i>World History
                    </button>
                </div>
            </div>

            <!-- Action Button -->
            <div class="mt-4 pt-2 border-top d-flex justify-content-end">
                <button type="submit" id="generateBtn" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="bi bi-cpu me-2"></i>Generate AI Questions
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loading Spinner & Progress -->
<div id="aiLoadingContainer" class="card shadow-sm border-0 mb-4 text-center py-5" style="display: none;">
    <div class="card-body">
        <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="fw-bold text-dark mb-1">Generating AI Questions...</h5>
        <p class="text-muted small mb-3" id="loadingTopicText">Connecting with Google Gemini to craft custom practice problems</p>
        <div class="progress mx-auto" style="max-width: 320px; height: 6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%;"></div>
        </div>
    </div>
</div>

<!-- Error Alert -->
<div id="aiErrorAlert" class="alert alert-danger shadow-sm border-0 d-flex align-items-center gap-2 mb-4" style="display: none;">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div id="aiErrorMessage">Could not generate questions. Please try again.</div>
</div>

<!-- Practice Session Container -->
<div id="aiPracticeContainer" style="display: none;">
    <!-- Live Header & Stats -->
    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="badge bg-primary me-2" id="activeTopicBadge">Topic</span>
                <span class="badge bg-secondary me-2" id="activeDifficultyBadge">Medium</span>
                <span class="text-muted small" id="activeCountBadge">5 Questions</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="small fw-semibold">
                    Progress: <span id="answeredCount" class="text-primary fw-bold">0</span> / <span id="totalQuestionsCount">5</span>
                </div>
                <div class="small fw-semibold">
                    Score: <span id="correctScoreCount" class="text-success fw-bold">0</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="newSessionBtn">
                    <i class="bi bi-arrow-repeat me-1"></i>New Topic
                </button>
            </div>
        </div>
    </div>

    <!-- Questions List -->
    <div id="questionsContainer" class="d-flex flex-column gap-3"></div>

    <!-- Completion Summary Card -->
    <div id="completionCard" class="card shadow-sm border-0 mt-4 text-center p-4 bg-white" style="display: none;">
        <div class="card-body">
            <div class="display-5 mb-2" id="completionEmoji">🎉</div>
            <h4 class="fw-bold mb-1" id="completionTitle">Great Job! Practice Complete!</h4>
            <p class="text-muted mb-3" id="completionSubtitle">You answered all questions for this topic.</p>
            
            <div class="d-inline-flex align-items-center justify-content-center gap-4 bg-light p-3 rounded-3 mb-4">
                <div>
                    <div class="fs-4 fw-bold text-success" id="finalCorrectCount">0</div>
                    <div class="small text-muted">Correct</div>
                </div>
                <div class="vr"></div>
                <div>
                    <div class="fs-4 fw-bold text-danger" id="finalWrongCount">0</div>
                    <div class="small text-muted">Incorrect</div>
                </div>
                <div class="vr"></div>
                <div>
                    <div class="fs-4 fw-bold text-primary" id="finalAccuracyPercent">0%</div>
                    <div class="small text-muted">Accuracy</div>
                </div>
            </div>

            <div>
                <button type="button" class="btn btn-primary px-4 me-2" id="retrySameTopicBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Generate More on This Topic
                </button>
                <button type="button" class="btn btn-outline-secondary px-3" id="tryAnotherTopicBtn">
                    <i class="bi bi-plus-circle me-1"></i>Try Another Topic
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Style for Interactive Option Cards */
.ai-option-card {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    border: 1.5px solid #e9ecef;
    background-color: #fff;
    border-radius: 8px;
    padding: 12px 16px;
    user-select: none;
}
.ai-option-card:hover:not(.locked) {
    border-color: #0d6efd;
    background-color: #f8faff;
    transform: translateY(-1px);
}
.ai-option-card.correct-opt {
    border-color: #198754 !important;
    background-color: #e8f5e9 !important;
    color: #0f5132 !important;
    font-weight: 600;
}
.ai-option-card.wrong-opt {
    border-color: #dc3545 !important;
    background-color: #fde8e8 !important;
    color: #842029 !important;
}
.ai-option-card.locked {
    cursor: default;
}
.typeahead-item {
    cursor: pointer;
    transition: background 0.15s;
    padding: 8px 14px;
}
.typeahead-item:hover, .typeahead-item.active {
    background-color: #f0f4ff;
}
.typeahead-highlight {
    font-weight: bold;
    color: #0d6efd;
    text-decoration: underline;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const basePath = '<?= defined('BASE_PATH') ? BASE_PATH : '' ?>';
    const form = document.getElementById('aiPracticeForm');
    const topicInput = document.getElementById('aiTopic');
    const countSelect = document.getElementById('aiCount');
    const difficultySelect = document.getElementById('aiDifficulty');
    const generateBtn = document.getElementById('generateBtn');
    const dropdown = document.getElementById('typeaheadDropdown');
    const dropdownItems = document.getElementById('typeaheadItems');
    const loadingContainer = document.getElementById('aiLoadingContainer');
    const errorAlert = document.getElementById('aiErrorAlert');
    const errorMessage = document.getElementById('aiErrorMessage');
    const practiceContainer = document.getElementById('aiPracticeContainer');
    const questionsContainer = document.getElementById('questionsContainer');
    const completionCard = document.getElementById('completionCard');
    const generatorCard = document.getElementById('generatorCard');

    let currentQuestions = [];
    let answeredCount = 0;
    let correctCount = 0;
    let selectedSuggestionIndex = -1;

    // ── Search Autocomplete & Suggestions (Typeahead) ─────────────
    let debounceTimer = null;

    function fetchSuggestions(query) {
        fetch(`${basePath}/api/topics-suggest?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                renderSuggestions(data.suggestions || [], query);
            })
            .catch(() => {
                dropdown.style.display = 'none';
            });
    }

    function renderSuggestions(suggestions, query) {
        if (!suggestions || suggestions.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        selectedSuggestionIndex = -1;
        dropdownItems.innerHTML = '';

        suggestions.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'typeahead-item d-flex align-items-center justify-content-between';
            row.dataset.topic = item.name;
            row.dataset.index = index;

            // Highlight matched query letters
            let displayName = item.name;
            if (query && query.trim() !== '') {
                const regex = new RegExp(`(${query.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                displayName = displayName.replace(regex, '<span class="typeahead-highlight">$1</span>');
            }

            row.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi ${item.icon || 'bi-bookmark'} text-primary"></i>
                    <span class="small text-dark">${displayName}</span>
                </div>
                <span class="badge bg-light text-secondary border small" style="font-size: 0.72rem;">${item.category || 'Topic'}</span>
            `;

            row.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectTopic(item.name);
            });

            dropdownItems.appendChild(row);
        });

        dropdown.style.display = 'block';
    }

    function selectTopic(topicName) {
        topicInput.value = topicName;
        dropdown.style.display = 'none';
        topicInput.focus();
    }

    topicInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        debounceTimer = setTimeout(() => {
            fetchSuggestions(query);
        }, 150);
    });

    topicInput.addEventListener('focus', function () {
        fetchSuggestions(this.value.trim());
    });

    topicInput.addEventListener('blur', function () {
        setTimeout(() => {
            dropdown.style.display = 'none';
        }, 200);
    });

    // Keyboard navigation in typeahead
    topicInput.addEventListener('keydown', function (e) {
        const items = dropdownItems.querySelectorAll('.typeahead-item');
        if (dropdown.style.display === 'none' || items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedSuggestionIndex = (selectedSuggestionIndex + 1) % items.length;
            highlightItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedSuggestionIndex = (selectedSuggestionIndex - 1 + items.length) % items.length;
            highlightItem(items);
        } else if (e.key === 'Enter') {
            if (selectedSuggestionIndex >= 0 && items[selectedSuggestionIndex]) {
                e.preventDefault();
                selectTopic(items[selectedSuggestionIndex].dataset.topic);
            }
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
        }
    });

    function highlightItem(items) {
        items.forEach((it, idx) => {
            if (idx === selectedSuggestionIndex) {
                it.classList.add('active');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('active');
            }
        });
    }

    // Quick Topic Chips click handler
    document.querySelectorAll('.topic-chip').forEach(chip => {
        chip.addEventListener('click', function () {
            selectTopic(this.dataset.topic);
        });
    });

    // ── Generate AI Practice Questions ─────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const topic = topicInput.value.trim();
        if (!topic) return;

        startGeneration(topic, countSelect.value, difficultySelect.value);
    });

    function startGeneration(topic, count, difficulty) {
        errorAlert.style.display = 'none';
        practiceContainer.style.display = 'none';
        completionCard.style.display = 'none';
        loadingContainer.style.display = 'block';
        document.getElementById('loadingTopicText').textContent = `Asking Google Gemini AI to generate ${count} ${difficulty} questions for "${topic}"...`;
        generateBtn.disabled = true;

        const formData = new FormData();
        formData.append('topic', topic);
        formData.append('count', count);
        formData.append('difficulty', difficulty);

        fetch(`${basePath}/ai-practice`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            loadingContainer.style.display = 'none';
            generateBtn.disabled = false;

            if (!data.success) {
                errorMessage.textContent = data.error || 'Failed to generate questions. Please try another topic.';
                errorAlert.style.display = 'flex';
                return;
            }

            renderPracticeSession(data);
        })
        .catch(err => {
            loadingContainer.style.display = 'none';
            generateBtn.disabled = false;
            errorMessage.textContent = 'Network error or service unavailable. Please check your connection and try again.';
            errorAlert.style.display = 'flex';
        });
    }

    function renderPracticeSession(data) {
        currentQuestions = data.questions || [];
        answeredCount = 0;
        correctCount = 0;

        document.getElementById('activeTopicBadge').textContent = data.topic;
        document.getElementById('activeDifficultyBadge').textContent = data.difficulty;
        document.getElementById('activeCountBadge').textContent = `${currentQuestions.length} Questions`;
        document.getElementById('totalQuestionsCount').textContent = currentQuestions.length;
        document.getElementById('answeredCount').textContent = '0';
        document.getElementById('correctScoreCount').textContent = '0';

        questionsContainer.innerHTML = '';

        currentQuestions.forEach((q, qIndex) => {
            const card = document.createElement('div');
            card.className = 'card shadow-sm border-0 question-block p-4 bg-white';
            card.dataset.index = qIndex;

            let optionsHtml = '';
            q.options.forEach((opt, optIndex) => {
                optionsHtml += `
                    <div class="ai-option-card d-flex align-items-center gap-3 mb-2" data-correct="${opt.correct ? '1' : '0'}" data-opt-index="${optIndex}">
                        <div class="badge bg-light text-secondary border rounded-circle" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                            ${String.fromCharCode(65 + optIndex)}
                        </div>
                        <div class="flex-grow-1 small">${escapeHtml(opt.text)}</div>
                    </div>
                `;
            });

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-light text-primary border small fw-bold">Question ${qIndex + 1} of ${currentQuestions.length}</span>
                    <span class="badge bg-light text-muted border small">1 Mark</span>
                </div>
                <h6 class="fw-bold text-dark mb-3">${escapeHtml(q.question)}</h6>
                <div class="options-wrapper">
                    ${optionsHtml}
                </div>
                <div class="feedback-msg mt-2 fw-semibold small" style="display: none;"></div>
            `;

            // Option selection click handler
            card.querySelectorAll('.ai-option-card').forEach(optEl => {
                optEl.addEventListener('click', function () {
                    if (card.dataset.answered === '1') return;
                    card.dataset.answered = '1';

                    const isCorrect = this.dataset.correct === '1';
                    answeredCount++;
                    if (isCorrect) correctCount++;

                    document.getElementById('answeredCount').textContent = answeredCount;
                    document.getElementById('correctScoreCount').textContent = correctCount;

                    // Highlight options
                    card.querySelectorAll('.ai-option-card').forEach(item => {
                        item.classList.add('locked');
                        if (item.dataset.correct === '1') {
                            item.classList.add('correct-opt');
                            item.querySelector('.badge').className = 'badge bg-success text-white rounded-circle';
                            item.querySelector('.badge').innerHTML = '✓';
                        } else if (item === this && !isCorrect) {
                            item.classList.add('wrong-opt');
                            item.querySelector('.badge').className = 'badge bg-danger text-white rounded-circle';
                            item.querySelector('.badge').innerHTML = '✗';
                        }
                    });

                    // Feedback banner
                    const feedback = card.querySelector('.feedback-msg');
                    feedback.style.display = 'block';
                    if (isCorrect) {
                        feedback.className = 'feedback-msg mt-2 fw-semibold small text-success';
                        feedback.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Correct answer!';
                    } else {
                        feedback.className = 'feedback-msg mt-2 fw-semibold small text-danger';
                        feedback.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Incorrect! The correct answer is highlighted in green.';
                    }

                    // Check if all answered
                    if (answeredCount === currentQuestions.length) {
                        showCompletionSummary();
                    }
                });
            });

            questionsContainer.appendChild(card);
        });

        practiceContainer.style.display = 'block';
        practiceContainer.scrollIntoView({ behavior: 'smooth' });
    }

    function showCompletionSummary() {
        const total = currentQuestions.length;
        const wrong = total - correctCount;
        const accuracy = Math.round((correctCount / total) * 100);

        document.getElementById('finalCorrectCount').textContent = correctCount;
        document.getElementById('finalWrongCount').textContent = wrong;
        document.getElementById('finalAccuracyPercent').textContent = `${accuracy}%`;

        if (accuracy >= 80) {
            document.getElementById('completionEmoji').textContent = '🏆';
            document.getElementById('completionTitle').textContent = 'Outstanding Performance!';
            document.getElementById('completionSubtitle').textContent = 'You have strong mastery over this topic.';
        } else if (accuracy >= 60) {
            document.getElementById('completionEmoji').textContent = '👍';
            document.getElementById('completionTitle').textContent = 'Good Effort!';
            document.getElementById('completionSubtitle').textContent = 'Solid foundation, with room for minor refinement.';
        } else {
            document.getElementById('completionEmoji').textContent = '💡';
            document.getElementById('completionTitle').textContent = 'Keep Practicing!';
            document.getElementById('completionSubtitle').textContent = 'Review key concepts and test yourself again.';
        }

        completionCard.style.display = 'block';
        completionCard.scrollIntoView({ behavior: 'smooth' });
    }

    // Reset / Retry buttons
    document.getElementById('newSessionBtn').addEventListener('click', function () {
        generatorCard.scrollIntoView({ behavior: 'smooth' });
        topicInput.focus();
    });

    document.getElementById('retrySameTopicBtn').addEventListener('click', function () {
        startGeneration(topicInput.value.trim(), countSelect.value, difficultySelect.value);
    });

    document.getElementById('tryAnotherTopicBtn').addEventListener('click', function () {
        topicInput.value = '';
        practiceContainer.style.display = 'none';
        completionCard.style.display = 'none';
        generatorCard.scrollIntoView({ behavior: 'smooth' });
        topicInput.focus();
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // If initial topic passed via query param, auto-populate
    if (topicInput.value.trim() !== '') {
        // Ready for one-click generate
    }
});
</script>
