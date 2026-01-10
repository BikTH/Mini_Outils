/**
 * QCM App - Exam Interface Enhancement
 * Gestion du timer, progression, validation et feedback visuel
 */

(function() {
  'use strict';

  // Configuration
  const CONFIG = {
    WARNING_THRESHOLD: 0.25,  // 25% du temps restant
    DANGER_THRESHOLD: 0.10,   // 10% du temps restant
    AUTO_SAVE_INTERVAL: 30000 // Auto-save toutes les 30 secondes (optionnel)
  };

  /**
   * Timer Management
   */
  class ExamTimer {
    constructor(timeLimit) {
      this.timeLimit = timeLimit; // en secondes
      this.timeLeft = timeLimit;
      this.interval = null;
      this.display = document.getElementById('timeLeftDisplay');
      this.timerContainer = null;
      this.isRunning = false;
    }

    start() {
      if (this.isRunning) return;

      this.isRunning = true;
      this.updateDisplay();
      this.updateTimerStyle();

      this.interval = setInterval(() => {
        this.timeLeft--;
        this.updateDisplay();
        this.updateTimerStyle();

        if (this.timeLeft <= 0) {
          this.stop();
          this.handleTimeout();
        }
      }, 1000);
    }

    stop() {
      if (this.interval) {
        clearInterval(this.interval);
        this.interval = null;
        this.isRunning = false;
      }
    }

    updateDisplay() {
      if (!this.display) return;
      this.display.textContent = this.formatTime(this.timeLeft);
    }

    updateTimerStyle() {
      this.timerContainer = this.timerContainer || document.querySelector('.timer-display');
      if (!this.timerContainer) return;

      const percentage = this.timeLeft / this.timeLimit;

      // Enlever les classes existantes
      this.timerContainer.classList.remove('warning', 'danger');

      // Appliquer la classe appropriée
      if (percentage <= CONFIG.DANGER_THRESHOLD) {
        this.timerContainer.classList.add('danger');
      } else if (percentage <= CONFIG.WARNING_THRESHOLD) {
        this.timerContainer.classList.add('warning');
      }
    }

    formatTime(seconds) {
      const h = Math.floor(seconds / 3600);
      const m = Math.floor((seconds % 3600) / 60);
      const s = seconds % 60;
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    handleTimeout() {
      // Marquer la soumission comme forcée
      const forcedInput = document.getElementById('forced_submit');
      if (forcedInput) {
        forcedInput.value = '1';
      }

      // Afficher une notification
      this.showTimeoutNotification();

      // Soumettre automatiquement le formulaire après 2 secondes
      setTimeout(() => {
        const form = document.getElementById('examForm');
        if (form) {
          form.submit();
        }
      }, 2000);
    }

    showTimeoutNotification() {
      const notification = document.createElement('div');
      notification.className = 'alert alert-warning';
      notification.style.position = 'fixed';
      notification.style.top = '100px';
      notification.style.left = '50%';
      notification.style.transform = 'translateX(-50%)';
      notification.style.zIndex = '9999';
      notification.style.minWidth = '300px';
      notification.style.textAlign = 'center';
      notification.style.animation = 'fadeIn 0.3s ease';
      notification.innerHTML = '<strong>⏰ Temps écoulé !</strong><br>Soumission automatique en cours...';

      document.body.appendChild(notification);
    }
  }

  /**
   * Progress Tracker
   */
  class ProgressTracker {
    constructor() {
      this.questions = document.querySelectorAll('fieldset[data-question-id]');
      this.totalQuestions = this.questions.length;
      this.answeredQuestions = 0;
      this.progressBar = null;
      this.progressText = null;

      this.init();
    }

    init() {
      this.createProgressBar();
      this.attachEventListeners();
      this.updateProgress();
    }

    createProgressBar() {
      const examForm = document.getElementById('examForm');
      if (!examForm) return;

      const progressContainer = document.createElement('div');
      progressContainer.className = 'card';
      progressContainer.style.position = 'sticky';
      progressContainer.style.top = 'calc(var(--header-height) + 1rem)';
      progressContainer.style.zIndex = '100';
      progressContainer.style.marginBottom = 'var(--spacing-xl)';

      progressContainer.innerHTML = `
        <div style="margin-bottom: var(--spacing-sm);">
          <strong>Progression</strong>
          <span id="progressText" style="float: right; color: var(--color-gray-600);">0 / ${this.totalQuestions}</span>
        </div>
        <div class="progress-bar-container">
          <div id="progressBar" class="progress-bar" style="width: 0%;">0%</div>
        </div>
      `;

      examForm.insertBefore(progressContainer, examForm.firstChild);

      this.progressBar = document.getElementById('progressBar');
      this.progressText = document.getElementById('progressText');
    }

    attachEventListeners() {
      const inputs = document.querySelectorAll('.answer-input');
      inputs.forEach(input => {
        input.addEventListener('change', () => {
          this.updateProgress();
          this.markQuestionAsAnswered(input);
        });
      });
    }

    updateProgress() {
      this.answeredQuestions = this.countAnsweredQuestions();
      const percentage = (this.answeredQuestions / this.totalQuestions) * 100;

      if (this.progressBar) {
        this.progressBar.style.width = percentage + '%';
        this.progressBar.textContent = Math.round(percentage) + '%';

        // Changer la couleur selon le pourcentage
        this.progressBar.classList.remove('success', 'warning', 'danger');
        if (percentage >= 80) {
          this.progressBar.classList.add('success');
        } else if (percentage >= 40) {
          this.progressBar.classList.add('warning');
        } else {
          this.progressBar.classList.add('danger');
        }
      }

      if (this.progressText) {
        this.progressText.textContent = `${this.answeredQuestions} / ${this.totalQuestions}`;
      }
    }

    countAnsweredQuestions() {
      let count = 0;
      this.questions.forEach(fieldset => {
        const inputs = fieldset.querySelectorAll('input[type="radio"], input[type="checkbox"]');
        const hasAnswer = Array.from(inputs).some(input => input.checked);
        if (hasAnswer) count++;
      });
      return count;
    }

    markQuestionAsAnswered(input) {
      const questionId = input.getAttribute('data-question-id');
      const fieldset = document.querySelector(`fieldset[data-question-id="${questionId}"]`);

      if (fieldset) {
        fieldset.classList.add('answered');
        const errorSpan = fieldset.querySelector('.error-message');
        if (errorSpan) {
          errorSpan.style.display = 'none';
        }
      }
    }
  }

  /**
   * Form Validation
   */
  class FormValidator {
    constructor(formId) {
      this.form = document.getElementById(formId);
      this.errorContainer = document.getElementById('formError');

      if (this.form) {
        this.form.addEventListener('submit', (e) => this.validate(e));
      }
    }

    validate(event) {
      const fieldsets = this.form.querySelectorAll('fieldset[data-question-id]');
      let hasError = false;
      const unansweredQuestions = [];

      // Réinitialiser les messages d'erreur
      if (this.errorContainer) {
        this.errorContainer.style.display = 'none';
      }

      fieldsets.forEach(fieldset => {
        const errorSpan = fieldset.querySelector('.error-message');
        if (errorSpan) {
          errorSpan.style.display = 'none';
        }
        fieldset.classList.remove('error');
      });

      // Vérifier chaque question
      fieldsets.forEach(fieldset => {
        const questionId = fieldset.getAttribute('data-question-id');
        const inputs = fieldset.querySelectorAll('input[type="radio"], input[type="checkbox"]');
        const answered = Array.from(inputs).some(input => input.checked);

        if (!answered) {
          hasError = true;
          unansweredQuestions.push(questionId);

          const errorSpan = fieldset.querySelector('.error-message');
          if (errorSpan) {
            errorSpan.style.display = 'inline';
          }

          fieldset.classList.add('error');
        }
      });

      if (hasError) {
        event.preventDefault();

        const errorMsg = `Veuillez répondre à toutes les questions avant de valider. Questions non répondues : ${unansweredQuestions.length}`;

        if (this.errorContainer) {
          this.errorContainer.textContent = errorMsg;
          this.errorContainer.style.display = 'block';
          this.errorContainer.className = 'alert alert-error fade-in';
        }

        // Scroll vers le haut
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Scroll vers la première question non répondue après 1 seconde
        setTimeout(() => {
          const firstError = this.form.querySelector('fieldset.error');
          if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }, 1000);

        return false;
      }

      return true;
    }
  }

  /**
   * Confirmation avant de quitter la page
   */
  class NavigationGuard {
    constructor() {
      this.isExamInProgress = document.getElementById('examForm') !== null;

      if (this.isExamInProgress) {
        window.addEventListener('beforeunload', this.confirmExit.bind(this));
      }
    }

    confirmExit(event) {
      // Vérifier si l'utilisateur a commencé à répondre
      const hasAnswers = document.querySelectorAll('.answer-input:checked').length > 0;

      if (hasAnswers) {
        event.preventDefault();
        event.returnValue = 'Êtes-vous sûr de vouloir quitter ? Vos réponses seront perdues.';
        return event.returnValue;
      }
    }

    disable() {
      window.removeEventListener('beforeunload', this.confirmExit);
    }
  }

  /**
   * Keyboard Shortcuts
   */
  class KeyboardShortcuts {
    constructor() {
      document.addEventListener('keydown', this.handleKeyPress.bind(this));
    }

    handleKeyPress(event) {
      // Alt + S : Submit form
      if (event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        const form = document.getElementById('examForm');
        if (form) {
          form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
      }

      // Alt + N : Next question (scroll to next unanswered)
      if (event.altKey && event.key.toLowerCase() === 'n') {
        event.preventDefault();
        this.scrollToNextUnanswered();
      }
    }

    scrollToNextUnanswered() {
      const unanswered = document.querySelector('fieldset[data-question-id]:not(.answered)');
      if (unanswered) {
        unanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  }

  /**
   * Amélioration des options (labels cliquables)
   */
  class OptionEnhancer {
    constructor() {
      this.enhanceOptions();
    }

    enhanceOptions() {
      const labels = document.querySelectorAll('label');
      labels.forEach(label => {
        const input = label.querySelector('input[type="radio"], input[type="checkbox"]');
        if (input) {
          // Ajouter la classe option-label si pas déjà présente
          if (!label.classList.contains('option-label')) {
            label.classList.add('option-label');

            // Créer un span pour le texte
            const textContent = label.textContent || label.innerText;
            label.innerHTML = '';
            label.appendChild(input);

            const textSpan = document.createElement('span');
            textSpan.className = 'option-text';
            textSpan.textContent = textContent;
            label.appendChild(textSpan);
          }
        }
      });
    }
  }

  /**
   * Initialize everything when DOM is ready
   */
  function initExamInterface() {
    // Timer
    const timerElement = document.getElementById('timeLeftDisplay');
    if (timerElement) {
      const timeLimitAttr = timerElement.getAttribute('data-time-limit');
      if (timeLimitAttr) {
        const timeLimit = parseInt(timeLimitAttr, 10);
        const timer = new ExamTimer(timeLimit);
        timer.start();
      }
    }

    // Progress Tracker
    const examForm = document.getElementById('examForm');
    if (examForm) {
      new ProgressTracker();
      new FormValidator('examForm');
      new NavigationGuard();
      new KeyboardShortcuts();
      new OptionEnhancer();
    }
  }

  // Init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamInterface);
  } else {
    initExamInterface();
  }

})();
