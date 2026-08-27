/**
 * ujian_import.js
 * Logika front-end untuk fitur Import Soal dari Excel
 */

// ─── State ────────────────────────────────────────────────────────────────────
let importExamId   = null;
let importFile     = null;
let importParsed   = []; // Hasil parse preview dari file

// ─── Buka modal import ────────────────────────────────────────────────────────
function openImportModal() {
  // Ambil exam_id dari context yang sedang aktif di modal soal
  // variabel currentExamId didefinisikan di ujian_admin.js
  if (typeof currentExamId === 'undefined' || !currentExamId) {
    showToast('Pilih ujian terlebih dahulu.', 'error');
    return;
  }
  importExamId = currentExamId;
  resetImportModal();
  document.getElementById('importModal').classList.add('active');
}

// ─── Reset state modal ────────────────────────────────────────────────────────
function resetImportModal() {
  importFile   = null;
  importParsed = [];

  document.getElementById('importFileInput').value      = '';
  document.getElementById('importFileName').textContent  = '';
  document.getElementById('importSubmitBtn').disabled   = true;
  document.getElementById('importPreviewContainer').style.display = 'none';
  document.getElementById('importErrorContainer').style.display  = 'none';
  document.getElementById('importProgress').style.display        = 'none';
  document.getElementById('importDropZone').style.background     = '';
}

// ─── Handle drag-and-drop ─────────────────────────────────────────────────────
function handleImportDrop(event) {
  event.preventDefault();
  document.getElementById('importDropZone').style.background = '';
  const dt   = event.dataTransfer;
  const file = dt.files && dt.files[0];
  if (file) processImportFile(file);
}

// ─── Handle pilih file via klik ───────────────────────────────────────────────
function handleImportFileSelect(input) {
  const file = input.files && input.files[0];
  if (file) processImportFile(file);
}

// ─── Validasi dan preview file ────────────────────────────────────────────────
function processImportFile(file) {
  const allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                   'application/vnd.ms-excel'];
  const ext     = file.name.split('.').pop().toLowerCase();

  if (!['xlsx', 'xls'].includes(ext)) {
    showImportError(['Format file harus .xlsx atau .xls']);
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showImportError(['Ukuran file melebihi batas 5 MB.']);
    return;
  }

  importFile = file;
  document.getElementById('importFileName').textContent = '✅ ' + file.name;
  document.getElementById('importErrorContainer').style.display = 'none';

  // Preview parsial menggunakan SheetJS jika tersedia, atau tampilkan info file
  parseExcelPreview(file);
}

// ─── Parse preview dengan SheetJS (CDN) ──────────────────────────────────────
function parseExcelPreview(file) {
  // Coba gunakan SheetJS jika sudah dimuat
  if (typeof XLSX !== 'undefined') {
    const reader = new FileReader();
    reader.onload = function(e) {
      try {
        const data   = new Uint8Array(e.target.result);
        const wb     = XLSX.read(data, { type: 'array' });
        const ws     = wb.Sheets[wb.SheetNames[0]];
        const rows   = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

        // Skip baris header (baris 0), ambil maksimal 10 baris preview
        const dataRows = rows.slice(1).filter(r => r.some(c => String(c).trim() !== ''));
        renderImportPreview(dataRows);
      } catch(err) {
        // Tidak bisa parse di client — biarkan server yang baca
        showPreviewFallback(file);
      }
    };
    reader.readAsArrayBuffer(file);
  } else {
    // SheetJS tidak tersedia — tampilkan pesan fallback
    showPreviewFallback(file);
  }
}

// ─── Render tabel preview ─────────────────────────────────────────────────────
function renderImportPreview(dataRows) {
  importParsed = dataRows;
  const count  = dataRows.length;

  document.getElementById('importPreviewCount').textContent = `(${count} soal terdeteksi)`;
  document.getElementById('importPreviewContainer').style.display = 'block';

  const previewRows = dataRows.slice(0, 8); // Tampilkan maks 8 baris preview
  let html = `<div class="import-preview-row header">
    <div>Pertanyaan</div>
    <div>Opsi A</div>
    <div>Opsi B</div>
    <div>Opsi C</div>
    <div>Opsi D</div>
    <div>Jwb</div>
  </div>`;

  previewRows.forEach(row => {
    const q  = escHtml(String(row[1] || ''));
    const a  = escHtml(String(row[2] || ''));
    const b  = escHtml(String(row[3] || ''));
    const c  = escHtml(String(row[4] || ''));
    const d  = escHtml(String(row[5] || ''));
    const ans= escHtml(String(row[8] || '').toLowerCase());
    html += `<div class="import-preview-row">
      <div title="${q}">${truncate(q, 60)}</div>
      <div>${truncate(a, 30)}</div>
      <div>${truncate(b, 30)}</div>
      <div>${truncate(c, 30)}</div>
      <div>${truncate(d, 30)}</div>
      <div style="font-weight:700; color:#C0392B;">${ans}</div>
    </div>`;
  });

  if (count > 8) {
    html += `<div style="text-align:center; padding:8px; font-size:0.8rem; color:var(--mist);">... dan ${count - 8} soal lainnya</div>`;
  }

  document.getElementById('importPreviewTable').innerHTML = html;

  if (count > 0) {
    document.getElementById('importSubmitBtn').disabled = false;
  }
}

// ─── Fallback jika SheetJS tidak tersedia ─────────────────────────────────────
function showPreviewFallback(file) {
  document.getElementById('importPreviewCount').textContent = '';
  document.getElementById('importPreviewContainer').style.display = 'block';
  document.getElementById('importPreviewTable').innerHTML =
    `<div style="padding:16px; text-align:center; color:var(--mist); font-size:0.85rem;">
       📄 File <strong>${escHtml(file.name)}</strong> (${(file.size/1024).toFixed(1)} KB) siap diupload.<br>
       Preview tidak tersedia — soal akan divalidasi saat proses import.
     </div>`;
  document.getElementById('importSubmitBtn').disabled = false;
}

// ─── Tampilkan error di modal ─────────────────────────────────────────────────
function showImportError(errors) {
  const el = document.getElementById('importErrorContainer');
  el.innerHTML = '<strong>⚠️ Perhatian:</strong><ul style="margin:6px 0 0 16px; padding:0;">'
    + errors.map(e => `<li>${escHtml(e)}</li>`).join('')
    + '</ul>';
  el.style.display = 'block';
}

// ─── Submit import ke server ──────────────────────────────────────────────────
async function submitImport() {
  if (!importFile || !importExamId) return;

  const btn      = document.getElementById('importSubmitBtn');
  const progress = document.getElementById('importProgress');
  btn.disabled   = true;
  progress.style.display = 'block';
  document.getElementById('importErrorContainer').style.display = 'none';

  const formData = new FormData();
  formData.append('exam_id',     importExamId);
  formData.append('excel_file',  importFile);

  try {
    const res  = await fetch('api_import_soal.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      let msg = data.message;
      if (data.skipped > 0) {
        msg += ` (${data.skipped} baris dilewati karena error)`;
      }
      showToast(msg, 'success');

      // Tampilkan warning jika ada baris yang di-skip
      if (data.errors && data.errors.length > 0) {
        showImportError(data.errors);
      } else {
        closeModal('importModal');
      }

      // Refresh daftar soal di modal questionsModal
      if (typeof loadQuestions === 'function') {
        loadQuestions(importExamId);
      }
    } else {
      showToast(data.message || 'Import gagal.', 'error');
      if (data.errors && data.errors.length > 0) {
        showImportError(data.errors);
      }
    }
  } catch (err) {
    showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
  } finally {
    btn.disabled = false;
    progress.style.display = 'none';
  }
}

// ─── Utility ─────────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function truncate(str, maxLen) {
  if (str.length <= maxLen) return str;
  return str.slice(0, maxLen - 1) + '…';
}

// ─── Lazy-load SheetJS dari CDN untuk preview client-side ────────────────────
(function loadSheetJS() {
  if (typeof XLSX !== 'undefined') return;
  const script = document.createElement('script');
  script.src   = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
  script.async = true;
  document.head.appendChild(script);
})();
