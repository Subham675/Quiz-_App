/* =============================================
   QuizApp — Premium Quiz JS
   Features: timer, answer ripple, progress,
   keyboard nav, auto-submit, smooth transitions
   ============================================= */

(function () {
  'use strict';

  /* ── Timer ─────────────────────────────────── */
  const timerEl = document.getElementById('quiz-timer');
  const formEl  = document.getElementById('quiz-form');

  function startTimer(seconds) {
    if (!timerEl || !seconds) return;

    let remaining = seconds;

    function tick() {
      const m = String(Math.floor(remaining / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      timerEl.textContent = m + ':' + s;

      if (remaining <= 60) {
        timerEl.classList.add('timer-warning');
        timerEl.style.animation = 'timerPulse 1s ease infinite';
      }

      if (remaining <= 0) {
        timerEl.textContent = '00:00';
        autoSubmit('timeout');
        return;
      }

      remaining--;
      setTimeout(tick, 1000);
    }

    tick();
  }

  /* ── Auto submit ────────────────────────────── */
  function autoSubmit(reason) {
    if (!formEl) return;
    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'submit_reason';
    hidden.value = reason;
    formEl.appendChild(hidden);

    showToast(reason === 'timeout' ? '⏰ Time up! Submitting your quiz…' : '✅ Quiz submitted!', 'info');

    setTimeout(() => formEl.submit(), 1200);
  }

  /* ── Answer selection with ripple ───────────── */
  function initAnswers() {
    const options = document.querySelectorAll('.option-label');

    options.forEach(label => {
      label.addEventListener('click', function (e) {
        const group = this.closest('.question-card');
        if (!group) return;

        group.querySelectorAll('.option-label').forEach(l => {
          l.classList.remove('selected');
          l.style.transform = '';
        });

        this.classList.add('selected');
        this.style.transform = 'scale(1.015)';
        setTimeout(() => { this.style.transform = ''; }, 200);

        createRipple(this, e);
        updateProgress();
      });
    });
  }

  /* ── Ripple effect ──────────────────────────── */
  function createRipple(el, e) {
    const ripple = document.createElement('span');
    const rect   = el.getBoundingClientRect();
    const size   = Math.max(rect.width, rect.height);
    const x      = e.clientX - rect.left - size / 2;
    const y      = e.clientY - rect.top  - size / 2;

    ripple.style.cssText = `
      position:absolute;width:${size}px;height:${size}px;
      left:${x}px;top:${y}px;
      background:rgba(24,95,165,0.12);
      border-radius:50%;transform:scale(0);
      animation:rippleAnim 0.5s ease-out forwards;
      pointer-events:none;
    `;

    el.style.position = 'relative';
    el.style.overflow = 'hidden';
    el.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  }

  /* ── Progress bar ───────────────────────────── */
  function updateProgress() {
    const total    = document.querySelectorAll('.question-card').length;
    const answered = document.querySelectorAll('.option-label.selected').length;
    const bar      = document.getElementById('quiz-progress-fill');
    const label    = document.getElementById('quiz-progress-label');

    if (!bar || total === 0) return;

    const pct = Math.round((answered / total) * 100);
    bar.style.width = pct + '%';
    if (label) label.textContent = answered + ' of ' + total + ' answered';
  }

  /* ── Keyboard navigation ────────────────────── */
  function initKeyboard() {
    const cards = Array.from(document.querySelectorAll('.question-card'));
    let current = 0;

    function scrollToCard(index) {
      if (!cards[index]) return;
      cards[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
      cards[index].classList.add('card-focus');
      setTimeout(() => cards[index].classList.remove('card-focus'), 600);
    }

    document.addEventListener('keydown', function (e) {
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

      if (e.key === 'ArrowDown' || e.key === 'j') {
        current = Math.min(current + 1, cards.length - 1);
        scrollToCard(current);
      } else if (e.key === 'ArrowUp' || e.key === 'k') {
        current = Math.max(current - 1, 0);
        scrollToCard(current);
      } else if (e.key >= '1' && e.key <= '4') {
        const opts = cards[current]?.querySelectorAll('.option-label');
        if (opts && opts[parseInt(e.key) - 1]) opts[parseInt(e.key) - 1].click();
      }
    });
  }

  /* ── Toast notification ─────────────────────── */
  function showToast(message, type) {
    let toast = document.getElementById('qa-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'qa-toast';
      toast.style.cssText = `
        position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(80px);
        background:#111318;color:#fff;padding:12px 24px;
        border-radius:50px;font-size:14px;font-weight:500;
        box-shadow:0 8px 32px rgba(0,0,0,0.22);
        z-index:9999;opacity:0;
        transition:all 0.35s cubic-bezier(0.22,1,0.36,1);
        white-space:nowrap;
      `;
      document.body.appendChild(toast);
    }

    toast.textContent = message;
    if (type === 'info') toast.style.background = '#185FA5';

    requestAnimationFrame(() => {
      toast.style.opacity  = '1';
      toast.style.transform = 'translateX(-50%) translateY(0)';
    });

    setTimeout(() => {
      toast.style.opacity   = '0';
      toast.style.transform = 'translateX(-50%) translateY(80px)';
    }, 3000);
  }

  /* ── Question card entrance animation ───────── */
  function animateCards() {
    const cards = document.querySelectorAll('.question-card');
    cards.forEach((card, i) => {
      card.style.opacity   = '0';
      card.style.transform = 'translateY(24px)';
      card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      setTimeout(() => {
        card.style.opacity   = '1';
        card.style.transform = 'translateY(0)';
      }, 80 + i * 60);
    });
  }

  /* ── Submit button confirmation ─────────────── */
  function initSubmit() {
    const btn = document.getElementById('quiz-submit-btn');
    if (!btn || !formEl) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const total    = document.querySelectorAll('.question-card').length;
      const answered = document.querySelectorAll('.option-label.selected').length;
      const skipped  = total - answered;

      if (skipped > 0) {
        showToast(`⚠️ ${skipped} question${skipped > 1 ? 's' : ''} unanswered. Submit anyway?`, 'warning');
        btn.textContent = 'Yes, submit';
        btn.dataset.confirmed = '1';
        btn.style.background = '#E24B4A';
        setTimeout(() => {
          btn.textContent = 'Submit quiz';
          btn.dataset.confirmed = '';
          btn.style.background = '';
        }, 4000);
      } else {
        autoSubmit('manual');
      }

      if (btn.dataset.confirmed === '1') autoSubmit('manual');
    });
  }

  /* ── Inject premium keyframe CSS ────────────── */
  function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
      @keyframes rippleAnim {
        to { transform: scale(2.5); opacity: 0; }
      }
      @keyframes timerPulse {
        0%,100% { transform: scale(1); }
        50%      { transform: scale(1.06); }
      }
      @keyframes cardFocus {
        0%,100% { box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        50%      { box-shadow: 0 0 0 3px rgba(24,95,165,.25); }
      }
      .option-label {
        transition: all 0.18s cubic-bezier(0.22,1,0.36,1) !important;
        cursor: pointer;
      }
      .option-label.selected {
        border-color: #185FA5 !important;
        background: #E6F1FB !important;
        color: #185FA5 !important;
        font-weight: 500;
      }
      .option-label:hover {
        transform: translateX(4px);
      }
      .card-focus {
        animation: cardFocus 0.6s ease;
      }
      .timer-warning {
        color: #E24B4A !important;
        background: #FCEBEB !important;
      }
      #quiz-progress-fill {
        transition: width 0.4s cubic-bezier(0.22,1,0.36,1);
      }
    `;
    document.head.appendChild(style);
  }

  /* ── Boot ───────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    injectStyles();
    animateCards();
    initAnswers();
    initKeyboard();
    initSubmit();

    const timerSeconds = parseInt(timerEl?.dataset?.seconds || '0', 10);
    if (timerSeconds > 0) startTimer(timerSeconds);
  });

  window.QuizApp = { showToast };

})();