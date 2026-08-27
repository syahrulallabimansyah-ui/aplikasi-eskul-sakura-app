// =============================================
// SAKURA APP — Kelola Ujian (Admin)
// =============================================

let currentExamId = null;

document.addEventListener('DOMContentLoaded', () => {
  loadExamList();

  document.getElementById('examForm').addEventListener('submit', handleExamFormSubmit);
  document.getElementById('questionForm').addEventListener('submit', handleQuestionFormSubmit);

  document.getElementById('qImageInput').addEventListener('change', handleImagePreview);

  // Highlight selected radio row
  document.querySelectorAll('input[name="correctOption"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.option-row').forEach(row => row.classList.remove('is-correct'));
      radio.closest('.option-row').classList.add('is-correct');
    });
  });

  // Page loader fade
  setTimeout(() => {
    const loader = document.getElementById('pageLoader');
    if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 500); }
  }, 300);
});

// ─── TOAST ───────────────────────────────────
function showToast(message, type = '') {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = 'toast show ' + type;
  setTimeout(() => toast.classList.remove('show'), 3000);
}

// ─── MODAL HELPERS ───────────────────────────
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// ─── LOAD EXAM LIST ──────────────────────────
async function loadExamList() {
  const container = document.getElementById('examListContainer');
  try {
    const fd = new FormData();
    fd.append('action', 'admin_list_exams');
    const res = await fetch('exam_api.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.success) {
      container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>${escapeHtml(data.message || 'Gagal memuat data.')}</div>`;
      return;
    }

    if (data.exams.length === 0) {
      container.innerHTML = `<div class="empty-state"><span class="icon">📋</span>Belum ada ujian. Klik "Buat Ujian Baru" untuk memulai.</div>`;
      return;
    }

    container.innerHTML = data.exams.map(exam => `
      <div class="exam-card" style="cursor:default;">
        <span class="exam-status-badge badge-${exam.status}">${statusLabel(exam.status)}</span>
        ${exam.token_expired ? `<span class="exam-status-badge badge-expired" style="margin-left:6px;">⏳ Token Kadaluarsa</span>` : ''}
        <div class="exam-card-title">${escapeHtml(exam.title)}</div>
        <div class="exam-card-desc">${escapeHtml(exam.description || 'Tidak ada deskripsi.')}</div>
        <div class="exam-card-meta">
          <span>⏱ ${exam.duration_minutes} menit</span>
          <span>📝 ${exam.total_questions} soal</span>
          <span>👥 ${exam.total_finished} selesai</span>
          <span>🔑 Token: <strong style="color:var(--gold)">${escapeHtml(exam.token)}</strong></span>
          ${exam.token_expires_at ? `<span>⏳ Token s/d: ${formatDateTime(exam.token_expires_at)}</span>` : '<span>⏳ Token: tanpa batas waktu</span>'}
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button class="btn-primary btn-sm btn-outline" onclick="openQuestionsModal(${exam.id}, '${escapeJs(exam.title)}', '${escapeJs(exam.token)}')">📝 Kelola Soal</button>
          <button class="btn-primary btn-sm btn-outline" onclick="openEditExamModal(${exam.id}, '${escapeJs(exam.title)}', '${escapeJs(exam.description || '')}', ${exam.duration_minutes}, '${escapeJs(exam.token_expires_at || '')}')">✏ Edit</button>
          <button class="btn-primary btn-sm btn-outline" onclick="regenerateToken(${exam.id})">🔄 Token</button>
          <button class="btn-primary btn-sm btn-outline" onclick="openResultsModal(${exam.id})">📊 Hasil</button>
          ${statusActionButtons(exam)}
          <button class="btn-primary btn-sm btn-danger" onclick="deleteExam(${exam.id})">🗑 Hapus</button>
        </div>
      </div>
    `).join('');
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>Terjadi kesalahan jaringan.</div>`;
  }
}

function statusLabel(status) {
  return { draft: 'Draft', published: 'Dipublikasikan', closed: 'Ditutup' }[status] || status;
}

function statusActionButtons(exam) {
  if (exam.status === 'draft') {
    return `<button class="btn-primary btn-sm btn-bamboo" onclick="setExamStatus(${exam.id}, 'published')">🚀 Publikasikan</button>`;
  }
  if (exam.status === 'published') {
    return `<button class="btn-primary btn-sm btn-danger" onclick="setExamStatus(${exam.id}, 'closed')">⏹ Tutup Ujian</button>`;
  }
  if (exam.status === 'closed') {
    return `<button class="btn-primary btn-sm btn-bamboo" onclick="setExamStatus(${exam.id}, 'published')">🚀 Buka Kembali</button>`;
  }
  return '';
}

// ─── CREATE / EDIT EXAM ──────────────────────
function openCreateExamModal() {
  document.getElementById('examModalTitle').textContent = 'Buat Ujian Baru';
  document.getElementById('examId').value = '';
  document.getElementById('examTitle').value = '';
  document.getElementById('examDescription').value = '';
  document.getElementById('examDuration').value = 30;
  document.getElementById('examTokenExpiry').value = '';
  openModal('examModal');
}

function openEditExamModal(id, title, description, duration, tokenExpiresAt) {
  document.getElementById('examModalTitle').textContent = 'Edit Ujian';
  document.getElementById('examId').value = id;
  document.getElementById('examTitle').value = title;
  document.getElementById('examDescription').value = description;
  document.getElementById('examDuration').value = duration;
  document.getElementById('examTokenExpiry').value = toDatetimeLocalValue(tokenExpiresAt);
  openModal('examModal');
}

async function handleExamFormSubmit(e) {
  e.preventDefault();
  const id = document.getElementById('examId').value;
  const fd = new FormData();
  fd.append('action', id ? 'admin_update_exam' : 'admin_create_exam');
  if (id) fd.append('exam_id', id);
  fd.append('title', document.getElementById('examTitle').value.trim());
  fd.append('description', document.getElementById('examDescription').value.trim());
  fd.append('duration_minutes', document.getElementById('examDuration').value);
  fd.append('token_expires_at', document.getElementById('examTokenExpiry').value);

  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    closeModal('examModal');
    loadExamList();
  } else {
    showToast(data.message, 'error');
  }
}

// Konversi "YYYY-MM-DD HH:MM:SS" (dari server) -> "YYYY-MM-DDTHH:MM" (untuk input datetime-local)
function toDatetimeLocalValue(str) {
  if (!str) return '';
  return str.replace(' ', 'T').substring(0, 16);
}

// ─── TOKEN / STATUS / DELETE ─────────────────
function regenerateToken(examId) {
  document.getElementById('regenExamId').value = examId;
  document.getElementById('regenTokenExpiry').value = '';
  openModal('regenTokenModal');
}

async function submitRegenerateToken() {
  const examId = document.getElementById('regenExamId').value;
  const fd = new FormData();
  fd.append('action', 'admin_regenerate_token');
  fd.append('exam_id', examId);
  fd.append('token_expires_at', document.getElementById('regenTokenExpiry').value);
  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    closeModal('regenTokenModal');
    showToast('Token baru: ' + data.token, 'success');
    loadExamList();
  } else {
    showToast(data.message || 'Gagal.', 'error');
  }
}

async function setExamStatus(examId, status) {
  const labels = { published: 'mempublikasikan', closed: 'menutup', draft: 'mengubah ke draft' };
  if (!confirm(`Yakin ingin ${labels[status] || 'mengubah status'} ujian ini?`)) return;
  const fd = new FormData();
  fd.append('action', 'admin_set_status');
  fd.append('exam_id', examId);
  fd.append('status', status);
  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    loadExamList();
  } else {
    showToast(data.message, 'error');
  }
}

async function deleteExam(examId) {
  if (!confirm('Yakin ingin menghapus ujian ini beserta semua soal dan hasilnya? Tindakan ini tidak dapat dibatalkan.')) return;
  const fd = new FormData();
  fd.append('action', 'admin_delete_exam');
  fd.append('exam_id', examId);
  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    loadExamList();
  } else {
    showToast(data.message, 'error');
  }
}

// ─── QUESTIONS MODAL ─────────────────────────
function openQuestionsModal(examId, title, token) {
  currentExamId = examId;
  document.getElementById('questionsModalTitle').textContent = 'Pertanyaan: ' + title;
  document.getElementById('examTokenInfo').innerHTML = `Token Ujian: <strong style="color:var(--gold)">${escapeHtml(token)}</strong>`;
  openModal('questionsModal');
  loadQuestions();
}

async function loadQuestions() {
  const container = document.getElementById('questionsListContainer');
  container.innerHTML = `<div class="empty-state"><span class="icon">⏳</span>Memuat soal...</div>`;
  const res = await fetch(`exam_api.php?action=admin_list_questions&exam_id=${currentExamId}`);
  const data = await res.json();

  if (!data.success) {
    container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>${escapeHtml(data.message || 'Gagal memuat soal.')}</div>`;
    return;
  }

  if (data.questions.length === 0) {
    container.innerHTML = `<div class="empty-state"><span class="icon">📝</span>Belum ada pertanyaan. Klik "+ Tambah Pertanyaan" untuk menambahkan soal.</div>`;
    return;
  }

  container.innerHTML = data.questions.map((q, idx) => {
    const opts = ['a','b','c','d','e','f'];
    let optionsHtml = '';
    opts.forEach(o => {
      const val = q['option_' + o];
      if (!val) return;
      const isCorrect = q.correct_option === o ? ' correct' : '';
      optionsHtml += `<div class="opt-preview${isCorrect}">${o.toUpperCase()}. ${escapeHtml(val)}${isCorrect ? ' ✓' : ''}</div>`;
    });

    const imageHtml = q.question_image
      ? `<img src="${escapeHtml(q.question_image)}" class="question-image-preview" onclick="openLightbox('${escapeHtml(q.question_image)}')">`
      : '';

    return `
      <div class="question-item">
        <div class="question-item-top">
          <div class="question-number">Soal ${idx + 1}</div>
          <div class="question-actions">
            <button class="icon-btn" onclick='editQuestion(${JSON.stringify(q).replace(/'/g, "&#39;")})'>✏ Edit</button>
            <button class="icon-btn danger" onclick="deleteQuestion(${q.id})">🗑</button>
          </div>
        </div>
        <div class="question-text">${escapeHtml(q.question_text)}</div>
        ${imageHtml}
        <div class="question-options-preview">${optionsHtml}</div>
      </div>
    `;
  }).join('');
}

function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('imageLightbox').classList.add('active');
}

async function deleteQuestion(questionId) {
  if (!confirm('Hapus pertanyaan ini?')) return;
  const fd = new FormData();
  fd.append('action', 'admin_delete_question');
  fd.append('question_id', questionId);
  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    loadQuestions();
  } else {
    showToast(data.message, 'error');
  }
}

// ─── QUESTION FORM (ADD / EDIT) ──────────────
function resetQuestionForm() {
  document.getElementById('questionForm').reset();
  document.getElementById('qQuestionId').value = '';
  document.getElementById('qExamId').value = currentExamId;
  document.getElementById('qImagePreview').style.display = 'none';
  document.getElementById('qImagePreview').src = '';
  document.getElementById('qImageUploadText').style.display = 'block';
  document.getElementById('qRemoveImage').value = '0';
  document.getElementById('qRemoveImageBtn').style.display = 'none';
  document.querySelectorAll('.option-row').forEach(row => row.classList.remove('is-correct'));
}

function openQuestionForm() {
  resetQuestionForm();
  document.getElementById('questionFormTitle').textContent = 'Tambah Pertanyaan';
  openModal('questionFormModal');
}

function editQuestion(q) {
  resetQuestionForm();
  document.getElementById('questionFormTitle').textContent = 'Edit Pertanyaan';
  document.getElementById('qQuestionId').value = q.id;
  document.getElementById('qExamId').value = q.exam_id;
  document.getElementById('qText').value = q.question_text;
  document.getElementById('optA').value = q.option_a || '';
  document.getElementById('optB').value = q.option_b || '';
  document.getElementById('optC').value = q.option_c || '';
  document.getElementById('optD').value = q.option_d || '';
  document.getElementById('optE').value = q.option_e || '';
  document.getElementById('optF').value = q.option_f || '';

  const radio = document.querySelector(`input[name="correctOption"][value="${q.correct_option}"]`);
  if (radio) {
    radio.checked = true;
    radio.closest('.option-row').classList.add('is-correct');
  }

  if (q.question_image) {
    document.getElementById('qImagePreview').src = q.question_image;
    document.getElementById('qImagePreview').style.display = 'block';
    document.getElementById('qImageUploadText').style.display = 'none';
    document.getElementById('qRemoveImageBtn').style.display = 'inline-block';
  }

  openModal('questionFormModal');
}

function handleImagePreview(e) {
  const file = e.target.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    showToast('Ukuran gambar maksimal 5MB.', 'error');
    e.target.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = (ev) => {
    document.getElementById('qImagePreview').src = ev.target.result;
    document.getElementById('qImagePreview').style.display = 'block';
    document.getElementById('qImageUploadText').style.display = 'none';
    document.getElementById('qRemoveImageBtn').style.display = 'inline-block';
    document.getElementById('qRemoveImage').value = '0';
  };
  reader.readAsDataURL(file);
}

function removeQuestionImage() {
  document.getElementById('qImageInput').value = '';
  document.getElementById('qImagePreview').style.display = 'none';
  document.getElementById('qImagePreview').src = '';
  document.getElementById('qImageUploadText').style.display = 'block';
  document.getElementById('qRemoveImageBtn').style.display = 'none';
  document.getElementById('qRemoveImage').value = '1';
}

async function handleQuestionFormSubmit(e) {
  e.preventDefault();

  const correctRadio = document.querySelector('input[name="correctOption"]:checked');
  if (!correctRadio) {
    showToast('Pilih jawaban yang benar terlebih dahulu.', 'error');
    return;
  }

  const questionId = document.getElementById('qQuestionId').value;
  const fd = new FormData();
  fd.append('action', questionId ? 'admin_update_question' : 'admin_add_question');
  if (questionId) fd.append('question_id', questionId);
  fd.append('exam_id', document.getElementById('qExamId').value);
  fd.append('question_text', document.getElementById('qText').value.trim());
  fd.append('option_a', document.getElementById('optA').value.trim());
  fd.append('option_b', document.getElementById('optB').value.trim());
  fd.append('option_c', document.getElementById('optC').value.trim());
  fd.append('option_d', document.getElementById('optD').value.trim());
  fd.append('option_e', document.getElementById('optE').value.trim());
  fd.append('option_f', document.getElementById('optF').value.trim());
  fd.append('correct_option', correctRadio.value);
  fd.append('remove_image', document.getElementById('qRemoveImage').value);

  const imageFile = document.getElementById('qImageInput').files[0];
  if (imageFile) fd.append('question_image', imageFile);

  const res = await fetch('exam_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showToast(data.message, 'success');
    closeModal('questionFormModal');
    loadQuestions();
  } else {
    showToast(data.message, 'error');
  }
}

// ─── RESULTS MODAL ───────────────────────────
async function openResultsModal(examId) {
  currentExamId = examId;
  openModal('resultsModal');
  const container = document.getElementById('resultsContainer');
  container.innerHTML = `<div class="empty-state"><span class="icon">⏳</span>Memuat hasil...</div>`;

  const res = await fetch(`exam_api.php?action=admin_exam_results&exam_id=${examId}`);
  const data = await res.json();

  if (!data.success) {
    container.innerHTML = `<div class="empty-state"><span class="icon">⚠</span>${escapeHtml(data.message || 'Gagal memuat hasil.')}</div>`;
    return;
  }

  if (data.results.length === 0) {
    container.innerHTML = `<div class="empty-state"><span class="icon">📊</span>Belum ada peserta yang mengikuti ujian ini.</div>`;
    return;
  }

  const rows = data.results.map(r => `
    <tr>
      <td>${escapeHtml(r.name)}</td>
      <td>${escapeHtml(r.email)}</td>
      <td>${r.status === 'finished' ? `<strong style="color:var(--gold)">${r.score}</strong>` : '-'}</td>
      <td>${r.total_correct !== null ? `${r.total_correct} / ${r.total_questions}` : '-'}</td>
      <td><span class="exam-status-badge ${r.status === 'finished' ? 'badge-published' : 'badge-draft'}">${statusAttemptLabel(r.status)}</span></td>
      <td>${r.finished_at ? formatDateTime(r.finished_at) : '-'}</td>
    </tr>
  `).join('');

  container.innerHTML = `
    <table class="results-table">
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Skor</th><th>Benar</th><th>Status</th><th>Selesai Pada</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
}

function statusAttemptLabel(status) {
  return { not_started: 'Belum Mulai', in_progress: 'Sedang Mengerjakan', finished: 'Selesai' }[status] || status;
}

function formatDateTime(str) {
  const d = new Date(str.replace(' ', 'T'));
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ─── UTILS ────────────────────────────────────
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function escapeJs(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' ');
}
