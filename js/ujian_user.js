// =============================================
// SAKURA APP — Daftar Ujian (User)
// =============================================

let pendingExamId = null;
let pendingToken = null;

document.addEventListener('DOMContentLoaded', () => {
  loadExamList();

  document.getElementById('tokenForm').addEventListener('submit', handleTokenSubmit);

  setTimeout(() => {
    const loader = document.getElementById('pageLoader');
    if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 500); }
  }, 300);
});

function showToast(message, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = 'toast show ' + type;
  setTimeout(() => toast.classList.remove('show'), 3000);
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

async function loadExamList() {
  const container = document.getElementById('examListContainer');
  const expiredSection = document.getElementById('expiredSection');
  const expiredContainer = document.getElementById('examExpiredContainer');
  try {
    const res = await fetch('exam_api.php?action=user_list_exams');
    const data = await res.json();

    if (!data.success) {
      container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>${escapeHtml(data.message || 'Gagal memuat ujian.')}</div>`;
      expiredSection.style.display = 'none';
      expiredContainer.style.display = 'none';
      return;
    }

    // Pisahkan ujian yang token-nya sudah kadaluarsa DAN belum mulai dikerjakan
    // dari ujian yang masih aktif/sedang dikerjakan/sudah selesai.
    const activeExams = [];
    const expiredExams = [];
    data.exams.forEach(exam => {
      const attemptStatus = exam.attempt_status || 'not_started';
      if (exam.token_expired && attemptStatus === 'not_started') {
        expiredExams.push(exam);
      } else {
        activeExams.push(exam);
      }
    });

    if (activeExams.length === 0) {
      container.innerHTML = `<div class="empty-state"><span class="icon">🌸</span>Belum ada ujian yang tersedia saat ini.</div>`;
    } else {
      container.innerHTML = '<div class="exam-grid">' + activeExams.map(renderExamCard).join('') + '</div>';
    }

    if (expiredExams.length === 0) {
      expiredSection.style.display = 'none';
      expiredContainer.style.display = 'none';
    } else {
      expiredSection.style.display = '';
      expiredContainer.style.display = '';
      expiredContainer.innerHTML = '<div class="exam-grid">' + expiredExams.map(renderExpiredExamCard).join('') + '</div>';
    }
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>Terjadi kesalahan jaringan.</div>`;
    expiredSection.style.display = 'none';
    expiredContainer.style.display = 'none';
  }
}

function renderExamCard(exam) {
  const attemptStatus = exam.attempt_status || 'not_started';
  let actionHtml = '';
  let scoreHtml = '';

  if (attemptStatus === 'finished') {
    scoreHtml = `<div class="exam-score-display">${exam.score}<span style="font-size:0.9rem; color:var(--mist);"> / 100</span></div>`;
    actionHtml = `<button class="btn-primary btn-sm btn-outline" style="width:100%;" disabled>✓ Ujian Telah Selesai</button>`;
  } else if (attemptStatus === 'in_progress') {
    actionHtml = `<button class="btn-primary" style="width:100%;" onclick="continueExam(${exam.id})">▶ Lanjutkan Ujian</button>`;
  } else {
    actionHtml = `<button class="btn-primary" style="width:100%;" onclick="openTokenModal(${exam.id})">Mulai Ujian</button>`;
  }

  return `
    <div class="exam-card" style="cursor:default;">
      <span class="exam-card-status status-${attemptStatus}">${statusLabel(attemptStatus)}</span>
      <div class="exam-card-title">${escapeHtml(exam.title)}</div>
      <div class="exam-card-desc">${escapeHtml(exam.description || 'Tidak ada deskripsi tambahan.')}</div>
      <div class="exam-card-meta">
        <span>⏱ ${exam.duration_minutes} menit</span>
        <span>📝 ${exam.total_questions} soal</span>
      </div>
      ${scoreHtml}
      ${actionHtml}
    </div>
  `;
}

function renderExpiredExamCard(exam) {
  return `
    <div class="exam-card" style="cursor:default; opacity:0.8;">
      <span class="exam-card-status" style="background:#fdecea; color:#C0392B; border:1px solid #f5c6cb;">⏳ Kadaluarsa</span>
      <div class="exam-card-title">${escapeHtml(exam.title)}</div>
      <div class="exam-card-desc">${escapeHtml(exam.description || 'Tidak ada deskripsi tambahan.')}</div>
      <div class="exam-card-meta">
        <span>⏱ ${exam.duration_minutes} menit</span>
        <span>📝 ${exam.total_questions} soal</span>
      </div>
      <button class="btn-primary btn-sm btn-outline" style="width:100%;" disabled>🔒 Token Kadaluarsa</button>
    </div>
  `;
}

function statusLabel(status) {
  return { not_started: 'Belum Dikerjakan', in_progress: 'Sedang Berjalan', finished: 'Selesai' }[status] || status;
}

function openTokenModal(examId) {
  pendingExamId = examId;
  document.getElementById('tokenExamId').value = examId;
  document.getElementById('tokenInput').value = '';
  openModal('tokenModal');
  setTimeout(() => document.getElementById('tokenInput').focus(), 100);
}

async function handleTokenSubmit(e) {
  e.preventDefault();
  const examId = document.getElementById('tokenExamId').value;
  const token = document.getElementById('tokenInput').value.trim().toUpperCase();

  const fd = new FormData();
  fd.append('action', 'user_verify_token');
  fd.append('exam_id', examId);
  fd.append('token', token);

  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();

  if (!data.success) {
    showToast(data.message, 'error');
    if (data.already_finished) closeModal('tokenModal');
    return;
  }

  pendingToken = token;
  closeModal('tokenModal');

  document.getElementById('motivationText').textContent = data.motivation;
  openModal('motivationModal');
}

async function proceedToExam() {
  const fd = new FormData();
  fd.append('action', 'user_start_exam');
  fd.append('exam_id', pendingExamId);
  fd.append('token', pendingToken);

  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();

  if (!data.success) {
    showToast(data.message, 'error');
    closeModal('motivationModal');
    return;
  }

  window.location.href = data.redirect;
}

function continueExam(examId) {
  window.location.href = 'ujian_kerjakan.php?exam_id=' + examId;
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
