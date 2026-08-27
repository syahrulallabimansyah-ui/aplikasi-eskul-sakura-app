<?php
require_once 'config.php';
require_once 'exam_helper.php';
requireAdmin();

$user = getCurrentUser();
$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Ujian</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #a1781e; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">

    <section class="welcome-section fade-up">
      <span class="welcome-kanji">試験</span>
      <h1 class="welcome-title">Kelola Ujian</h1>
      <p class="welcome-sub">Buat, atur, dan pantau ujian pilihan ganda untuk pengguna.</p>
      <div class="section-divider"></div>
    </section>

    <div class="profile-card admin-card fade-up delay-1">
      <div class="section-header">
        <h2>Daftar Ujian</h2>
        <button class="btn-primary btn-sm" style="width:auto;" onclick="openCreateExamModal()">+ Buat Ujian Baru</button>
      </div>
      <div id="examListContainer">
        <div class="empty-state"><span class="icon">⏳</span>Memuat data ujian...</div>
      </div>
    </div>

  </main>

  <!-- ═══ MODAL: Buat / Edit Ujian ═══ -->
  <div class="modal-overlay" id="examModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('examModal')">×</button>
      <h3 id="examModalTitle">Buat Ujian Baru</h3>
      <form id="examForm">
        <input type="hidden" id="examId" value="">
        <div class="form-group">
          <label class="form-label">Judul Ujian</label>
          <input type="text" class="form-input" id="examTitle" placeholder="Contoh: Ujian Akhir Semester" required>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea class="form-input" id="examDescription" placeholder="Deskripsi singkat tentang ujian ini..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Durasi Ujian (menit)</label>
          <input type="number" class="form-input" id="examDuration" min="1" value="30" required>
        </div>
        <div class="form-group">
          <label class="form-label">Batas Waktu Token (opsional)</label>
          <input type="datetime-local" class="form-input" id="examTokenExpiry">
          <small style="color:var(--mist); font-size:0.78rem; display:block; margin-top:6px; line-height:1.5;">
            Setelah waktu ini, token ujian tidak bisa lagi dipakai untuk memulai ujian baru, dan kartu ujian ini akan otomatis berpindah ke bagian "Ujian yang Telah Kadaluarsa" di sisi user (jika belum mulai mengerjakan). Kosongkan jika tidak ada batas waktu.
          </small>
        </div>
        <button type="submit" class="btn-primary">Simpan</button>
      </form>
    </div>
  </div>

  <!-- ═══ MODAL: Buat Token Baru ═══ -->
  <div class="modal-overlay" id="regenTokenModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('regenTokenModal')">×</button>
      <h3>Buat Token Baru</h3>
      <p style="color:var(--mist); font-size:0.88rem; margin-bottom:20px; line-height:1.6;">
        Token lama tidak akan berlaku lagi. Atur juga batas waktu token baru ini jika perlu (opsional).
      </p>
      <input type="hidden" id="regenExamId" value="">
      <div class="form-group">
        <label class="form-label">Batas Waktu Token (opsional)</label>
        <input type="datetime-local" class="form-input" id="regenTokenExpiry">
      </div>
      <button type="button" class="btn-primary" onclick="submitRegenerateToken()">🔄 Buat Token Baru</button>
    </div>
  </div>

  <!-- ═══ MODAL: Kelola Soal ═══ -->
  <div class="modal-overlay" id="questionsModal">
    <div class="modal-box modal-wide">
      <button class="modal-close" onclick="closeModal('questionsModal')">×</button>
      <h3 id="questionsModalTitle">Pertanyaan Ujian</h3>

      <div class="section-header">
        <div style="font-size:0.85rem; color:var(--mist);" id="examTokenInfo"></div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button class="btn-secondary btn-sm" style="width:auto;" onclick="openImportModal()">📥 Import Excel</button>
          <button class="btn-primary btn-sm" style="width:auto;" onclick="openQuestionForm()">+ Tambah Pertanyaan</button>
        </div>
      </div>

      <div id="questionsListContainer">
        <div class="empty-state"><span class="icon">⏳</span>Memuat soal...</div>
      </div>
    </div>
  </div>

  <!-- ═══ MODAL: Tambah / Edit Pertanyaan ═══ -->
  <div class="modal-overlay" id="questionFormModal">
    <div class="modal-box modal-wide">
      <button class="modal-close" onclick="closeModal('questionFormModal')">×</button>
      <h3 id="questionFormTitle">Tambah Pertanyaan</h3>
      <form id="questionForm">
        <input type="hidden" id="qExamId" value="">
        <input type="hidden" id="qQuestionId" value="">

        <div class="form-group">
          <label class="form-label">Pertanyaan</label>
          <textarea class="form-input" id="qText" placeholder="Tulis pertanyaan di sini..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Gambar Pertanyaan (opsional)</label>
          <div class="file-upload-area" id="qImageUploadArea" onclick="document.getElementById('qImageInput').click()">
            <span id="qImageUploadText">📷 Klik untuk unggah gambar (JPG, PNG, GIF, WEBP — maks 5MB)</span>
            <img id="qImagePreview" class="question-image-preview" style="display:none;">
          </div>
          <input type="file" id="qImageInput" accept="image/*" style="display:none;">
          <input type="hidden" id="qRemoveImage" value="0">
          <button type="button" class="icon-btn danger" id="qRemoveImageBtn" style="display:none; margin-top:8px;" onclick="removeQuestionImage()">🗑 Hapus Gambar</button>
        </div>

        <div class="form-group">
          <label class="form-label">Pilihan Jawaban (pilih bulatan untuk jawaban benar)</label>
          <div id="optionsContainer">
            <div class="option-row" data-letter="a">
              <input type="radio" name="correctOption" value="a">
              <div class="option-letter">A</div>
              <input type="text" class="form-input" id="optA" placeholder="Pilihan A" required>
            </div>
            <div class="option-row" data-letter="b">
              <input type="radio" name="correctOption" value="b">
              <div class="option-letter">B</div>
              <input type="text" class="form-input" id="optB" placeholder="Pilihan B" required>
            </div>
            <div class="option-row" data-letter="c">
              <input type="radio" name="correctOption" value="c">
              <div class="option-letter">C</div>
              <input type="text" class="form-input" id="optC" placeholder="Pilihan C" required>
            </div>
            <div class="option-row" data-letter="d">
              <input type="radio" name="correctOption" value="d">
              <div class="option-letter">D</div>
              <input type="text" class="form-input" id="optD" placeholder="Pilihan D" required>
            </div>
            <div class="option-row" data-letter="e">
              <input type="radio" name="correctOption" value="e">
              <div class="option-letter">E</div>
              <input type="text" class="form-input" id="optE" placeholder="Pilihan E (opsional)">
            </div>
            <div class="option-row" data-letter="f">
              <input type="radio" name="correctOption" value="f">
              <div class="option-letter">F</div>
              <input type="text" class="form-input" id="optF" placeholder="Pilihan F (opsional)">
            </div>
          </div>
        </div>

        <button type="submit" class="btn-primary">Simpan Pertanyaan</button>
      </form>
    </div>
  </div>

  <!-- ═══ MODAL: Hasil Ujian ═══ -->
  <div class="modal-overlay" id="resultsModal">
    <div class="modal-box modal-wide">
      <button class="modal-close" onclick="closeModal('resultsModal')">×</button>
      <h3>Hasil Peserta</h3>
      <div id="resultsContainer">
        <div class="empty-state"><span class="icon">⏳</span>Memuat hasil...</div>
      </div>
    </div>
  </div>

  <div class="image-lightbox" id="imageLightbox" onclick="this.classList.remove('active')">
    <img id="lightboxImg" src="">
  </div>

  <!-- ═══ MODAL: Import Soal dari Excel ═══ -->
  <div class="modal-overlay" id="importModal">
    <div class="modal-box modal-wide">
      <button class="modal-close" onclick="closeModal('importModal')">×</button>
      <h3>📥 Import Soal dari Excel</h3>

      <div style="background:var(--card-bg2, #f9f0f0); border:1px solid var(--border,#e8d5d5); border-radius:10px; padding:16px; margin-bottom:20px; font-size:0.875rem; line-height:1.7; color:var(--text);">
        <strong>📋 Format Template Excel:</strong>
        <table style="width:100%; margin-top:10px; border-collapse:collapse; font-size:0.82rem;">
          <thead>
            <tr style="background:var(--sakura,#C0392B); color:#fff;">
              <th style="padding:6px 10px; border-radius:4px 0 0 4px;">Kolom</th>
              <th style="padding:6px 10px;">Keterangan</th>
              <th style="padding:6px 10px; border-radius:0 4px 4px 0;">Wajib?</th>
            </tr>
          </thead>
          <tbody>
            <tr><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">No</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Nomor urut soal</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee); color:var(--mist);">Opsional</td></tr>
            <tr><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Pertanyaan</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Teks soal</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee); color:#C0392B; font-weight:bold;">Wajib</td></tr>
            <tr><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Pilihan A–D</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Teks opsi jawaban A, B, C, D</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee); color:#C0392B; font-weight:bold;">Wajib</td></tr>
            <tr><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Pilihan E & F</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee);">Teks opsi E dan F</td><td style="padding:5px 10px; border-bottom:1px solid var(--border,#eee); color:var(--mist);">Opsional</td></tr>
            <tr><td style="padding:5px 10px;">Jawaban Benar</td><td style="padding:5px 10px;">Huruf kecil: a / b / c / d / e / f</td><td style="padding:5px 10px; color:#C0392B; font-weight:bold;">Wajib</td></tr>
          </tbody>
        </table>
        <div style="margin-top:12px;">
          <a id="downloadTemplateBtn" href="template_soal_ujian.xlsx" download style="display:inline-flex; align-items:center; gap:6px; color:#C0392B; font-weight:bold; text-decoration:none; font-size:0.875rem;">
            ⬇️ Download Template Excel
          </a>
        </div>
      </div>

      <div id="importDropZone" style="border:2px dashed var(--sakura,#C0392B); border-radius:12px; padding:36px 20px; text-align:center; cursor:pointer; transition:background 0.2s; margin-bottom:16px;"
           onclick="document.getElementById('importFileInput').click()"
           ondragover="event.preventDefault(); this.style.background='rgba(192,57,43,0.07)'"
           ondragleave="this.style.background=''"
           ondrop="handleImportDrop(event)">
        <div style="font-size:2.5rem; margin-bottom:8px;">📊</div>
        <div style="font-weight:600; color:var(--text);">Klik atau seret file Excel ke sini</div>
        <div style="font-size:0.8rem; color:var(--mist); margin-top:4px;">Format: .xlsx atau .xls — Maks. 5 MB</div>
        <div id="importFileName" style="margin-top:10px; font-size:0.85rem; color:#C0392B; font-weight:600;"></div>
      </div>
      <input type="file" id="importFileInput" accept=".xlsx,.xls" style="display:none;" onchange="handleImportFileSelect(this)">

      <div id="importPreviewContainer" style="display:none; margin-bottom:16px;">
        <div style="font-weight:600; font-size:0.9rem; margin-bottom:8px; color:var(--text);">Preview Soal <span id="importPreviewCount" style="color:#C0392B;"></span></div>
        <div id="importPreviewTable" style="max-height:220px; overflow-y:auto; border:1px solid var(--border,#e8d5d5); border-radius:8px;"></div>
      </div>

      <div id="importErrorContainer" style="display:none; background:#fff5f5; border:1px solid #ffcdd2; border-radius:8px; padding:12px; margin-bottom:16px; font-size:0.82rem; color:#b71c1c; max-height:120px; overflow-y:auto;"></div>

      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button class="btn-primary" id="importSubmitBtn" onclick="submitImport()" disabled style="width:auto;">
          📥 Import Soal
        </button>
        <button class="btn-secondary" onclick="closeModal('importModal')" style="width:auto;">Batal</button>
        <div id="importProgress" style="display:none; font-size:0.85rem; color:var(--mist);">⏳ Mengimport soal...</div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script src="js/ujian_admin.js"></script>
  <script src="js/ujian_import.js"></script>

  <style>
    .btn-secondary {
      background: transparent;
      border: 1.5px solid var(--sakura, #C0392B);
      color: var(--sakura, #C0392B);
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 600;
      transition: background 0.2s, color 0.2s;
    }
    .btn-secondary:hover {
      background: var(--sakura, #C0392B);
      color: #fff;
    }
    .import-preview-row {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr 1fr 0.5fr;
      gap: 4px;
      padding: 8px 10px;
      font-size: 0.78rem;
      border-bottom: 1px solid var(--border, #eee);
      align-items: start;
    }
    .import-preview-row.header {
      background: var(--sakura, #C0392B);
      color: #fff;
      font-weight: 600;
      position: sticky;
      top: 0;
      border-radius: 7px 7px 0 0;
    }
    .import-preview-row:last-child { border-bottom: none; }
    .import-preview-row:not(.header):hover { background: rgba(192,57,43,0.04); }
    .badge-expired {
      background: #fdecea;
      color: #C0392B;
      border: 1px solid #f5c6cb;
    }
  </style>
</body>
</html>