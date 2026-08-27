// =============================================
// SAKURA APP — Pengerjaan Ujian (Real-Time)
// =============================================

let examData = null;
let currentQuestionIndex = 0;
let userAnswers = {};
let remainingSeconds = 0;
let timerInterval = null;
let syncInterval = null;
let cheatCount = 0;
let examFinished = false;

document.addEventListener('DOMContentLoaded', () => {
  loadExam();
  setupAntiCheat();
});

function showToast(message, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = 'toast show ' + type;
  setTimeout(() => toast.classList.remove('show'), 3000);
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// =================================================================
// MUAT DATA UJIAN
// =================================================================
async function loadExam() {
  try {
    const res = await fetch(`exam_api.php?action=user_get_exam&exam_id=${EXAM_ID}`);
    const data = await res.json();

    if (!data.success) {
      if (data.finished || data.time_up) {
        showToast(data.message, 'error');
        setTimeout(() => window.location.href = `ujian_kerjakan.php?exam_id=${EXAM_ID}`, 1500);
        return;
      }
      showToast(data.message || 'Gagal memuat ujian.', 'error');
      setTimeout(() => window.location.href = 'ujian.php', 1500);
      return;
    }

    examData = data;
    userAnswers = data.answers || {};

    remainingSeconds = Math.floor((new Date(data.ends_at.replace(' ', 'T')) - new Date(data.server_time.replace(' ', 'T'))) / 1000);
    if (remainingSeconds < 0) remainingSeconds = 0;

    renderNavigator();
    renderQuestion(0);
    startTimer();
    startSync();
  } catch (e) {
    showToast('Terjadi kesalahan jaringan saat memuat ujian.', 'error');
  }
}

// =================================================================
// TIMER REAL-TIME
// =================================================================
function startTimer() {
  updateTimerDisplay();
  timerInterval = setInterval(() => {
    remainingSeconds--;
    if (remainingSeconds <= 0) {
      remainingSeconds = 0;
      updateTimerDisplay();
      clearInterval(timerInterval);
      autoFinishExam('Waktu ujian telah habis. Jawabanmu otomatis disimpan dan dinilai.');
      return;
    }
    updateTimerDisplay();
  }, 1000);
}

function updateTimerDisplay() {
  const m = Math.floor(remainingSeconds / 60);
  const s = remainingSeconds % 60;
  const display = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  const timerEl = document.getElementById('examTimer');
  timerEl.innerHTML = `⏱ ${display}`;
  if (remainingSeconds <= 60) {
    timerEl.classList.add('warning');
  }
}

// Sinkronisasi sisa waktu dengan server secara periodik (real-time, anti-manipulasi)
function startSync() {
  syncInterval = setInterval(async () => {
    if (examFinished) return;
    try {
      const res = await fetch(`exam_api.php?action=user_check_time&exam_id=${EXAM_ID}`);
      const data = await res.json();
      if (!data.success) return;

      if (data.finished) {
        clearInterval(timerInterval);
        clearInterval(syncInterval);
        autoFinishExam('Waktu ujian telah habis. Jawabanmu otomatis disimpan dan dinilai.');
        return;
      }

      // Sesuaikan jika ada selisih signifikan dengan server
      if (Math.abs(data.remaining_seconds - remainingSeconds) > 3) {
        remainingSeconds = data.remaining_seconds;
        updateTimerDisplay();
      }
    } catch (e) { /* abaikan kegagalan sementara */ }
  }, 15000);
}

// =================================================================
// RENDER SOAL
// =================================================================
function renderQuestion(index) {
  currentQuestionIndex = index;
  const q = examData.questions[index];
  const total = examData.questions.length;
  const opts = ['a','b','c','d','e','f'];

  let optionsHtml = '';
  opts.forEach(o => {
    const val = q['option_' + o];
    if (!val) return;
    const selected = userAnswers[q.id] === o ? ' selected' : '';
    optionsHtml += `
      <label class="exam-option-label${selected}" data-letter="${o}">
        <input type="radio" name="question_${q.id}" value="${o}" ${userAnswers[q.id] === o ? 'checked' : ''} onchange="selectAnswer(${q.id}, '${o}')">
        <span class="exam-option-letter-badge">${o.toUpperCase()}.</span>
        <span class="exam-option-text">${escapeHtml(val)}</span>
      </label>
    `;
  });

  const imageHtml = q.question_image
    ? `<img src="${escapeHtml(q.question_image)}" class="exam-question-image" onclick="openLightbox('${escapeHtml(q.question_image)}')">`
    : '';

  document.getElementById('questionPanel').innerHTML = `
    <div class="exam-question-label">Soal ${index + 1} dari ${total}</div>
    <div class="exam-question-text">${escapeHtml(q.question_text)}</div>
    ${imageHtml}
    <div class="exam-options-list">${optionsHtml}</div>
    <div class="exam-nav-footer">
      <button class="btn-primary btn-outline" style="width:auto;" onclick="goToQuestion(${index - 1})" ${index === 0 ? 'disabled' : ''}>← Sebelumnya</button>
      <button class="btn-primary" style="width:auto;" onclick="goToQuestion(${index + 1})" ${index === total - 1 ? 'disabled' : ''}>Selanjutnya →</button>
    </div>
  `;

  highlightCurrentNav();
}

function goToQuestion(index) {
  if (index < 0 || index >= examData.questions.length) return;
  renderQuestion(index);
}

function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('imageLightbox').classList.add('active');
}

// =================================================================
// NAVIGATOR SOAL
// =================================================================
function renderNavigator() {
  const grid = document.getElementById('navGrid');
  grid.innerHTML = examData.questions.map((q, idx) => {
    const answered = userAnswers[q.id] ? ' answered' : '';
    return `<button class="nav-dot${answered}" data-index="${idx}" onclick="goToQuestion(${idx})">${idx + 1}</button>`;
  }).join('');
  highlightCurrentNav();
}

function highlightCurrentNav() {
  document.querySelectorAll('.nav-dot').forEach((dot, idx) => {
    dot.classList.toggle('current', idx === currentQuestionIndex);
  });
}

function updateNavDot(questionId) {
  const idx = examData.questions.findIndex(q => q.id === questionId);
  if (idx === -1) return;
  const dot = document.querySelector(`.nav-dot[data-index="${idx}"]`);
  if (dot) dot.classList.toggle('answered', !!userAnswers[questionId]);
}

// =================================================================
// SIMPAN JAWABAN (REAL-TIME AUTOSAVE)
// =================================================================
async function selectAnswer(questionId, option) {
  userAnswers[questionId] = option;

  // Update tampilan opsi yang dipilih
  document.querySelectorAll('.exam-option-label').forEach(label => {
    label.classList.toggle('selected', label.dataset.letter === option);
  });

  updateNavDot(questionId);

  const fd = new FormData();
  fd.append('action', 'user_save_answer');
  fd.append('exam_id', EXAM_ID);
  fd.append('question_id', questionId);
  fd.append('selected_option', option);

  try {
    const res = await fetch('exam_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      if (data.time_up) {
        autoFinishExam('Waktu ujian telah habis. Jawabanmu otomatis disimpan dan dinilai.');
      } else {
        showToast(data.message || 'Gagal menyimpan jawaban.', 'error');
      }
    }
  } catch (e) {
    showToast('Jawaban belum tersimpan, periksa koneksi internet.', 'error');
  }
}

// =================================================================
// SELESAIKAN UJIAN
// =================================================================
function confirmFinishExam() {
  const total = examData.questions.length;
  const answered = Object.keys(userAnswers).length;
  const unanswered = total - answered;

  let text = 'Apakah kamu yakin ingin menyelesaikan ujian ini? Setelah disubmit, jawaban tidak dapat diubah kembali.';
  if (unanswered > 0) {
    text = `Kamu masih memiliki ${unanswered} soal yang belum dijawab. ${text}`;
  }
  document.getElementById('finishModalText').textContent = text;
  openModal('finishModal');
}

async function submitFinishExam() {
  closeModal('finishModal');
  await doFinishExam();
}

async function autoFinishExam(message) {
  if (examFinished) return;
  showToast(message, 'error');
  await doFinishExam();
}

async function doFinishExam() {
  if (examFinished) return;
  examFinished = true;
  clearInterval(timerInterval);
  clearInterval(syncInterval);

  const fd = new FormData();
  fd.append('action', 'user_finish_exam');
  fd.append('exam_id', EXAM_ID);

  try {
    const res = await fetch('exam_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = `ujian_kerjakan.php?exam_id=${EXAM_ID}`;
    } else {
      showToast(data.message || 'Gagal menyelesaikan ujian.', 'error');
      examFinished = false;
    }
  } catch (e) {
    showToast('Terjadi kesalahan jaringan.', 'error');
    examFinished = false;
  }
}

// =================================================================
// ANTI-CHEAT
// =================================================================
function setupAntiCheat() {
  // Deteksi pindah tab / minimize
  document.addEventListener('visibilitychange', () => {
    if (document.hidden && !examFinished) {
      triggerCheatWarning();
    }
  });

  // Deteksi kehilangan fokus window
  window.addEventListener('blur', () => {
    if (!examFinished) triggerCheatWarning();
  });

  // Cegah klik kanan
  document.addEventListener('contextmenu', (e) => {
    if (!examFinished) e.preventDefault();
  });

  // Cegah copy/cut
  document.addEventListener('copy', (e) => {
    if (!examFinished) e.preventDefault();
  });
  document.addEventListener('cut', (e) => {
    if (!examFinished) e.preventDefault();
  });

  // Cegah shortcut umum: devtools, refresh, print, save
  document.addEventListener('keydown', (e) => {
    if (examFinished) return;
    const blocked =
      e.key === 'F12' ||
      e.key === 'F5' ||
      (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) ||
      (e.ctrlKey && ['r','R','p','P','s','S','u','U'].includes(e.key));
    if (blocked) {
      e.preventDefault();
      showToast('Tindakan ini tidak diperbolehkan selama ujian.', 'error');
    }
  });

  // Peringatan saat mencoba menutup / refresh halaman
  window.addEventListener('beforeunload', (e) => {
    if (!examFinished) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
}

function triggerCheatWarning() {
  cheatCount++;
  document.getElementById('cheatCount').textContent = cheatCount;
  document.getElementById('cheatWarningOverlay').classList.add('active');
}

function dismissCheatWarning() {
  document.getElementById('cheatWarningOverlay').classList.remove('active');
}

// =================================================================
// UTILS
// =================================================================
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
