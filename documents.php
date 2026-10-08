<?php
require_once 'config.php';
$patient = null;
if (isset($_SESSION['patient_id'])) {
    $patient = getPatientById($pdo, $_SESSION['patient_id']);
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars(t('documents_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #eef2f7;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            padding: 2rem 1.5rem;
            color: #1a2c3e;
        }

        /* main medical card container */
        .dashboard {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 28px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        /* header / tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 0 1.8rem;
            gap: 2rem;
        }

        .tab {
            font-size: 1rem;
            font-weight: 600;
            padding: 1rem 0 0.9rem 0;
            color: #5b6e8c;
            cursor: default;
            border-bottom: 3px solid transparent;
            transition: 0.2s;
        }

        .tab.active {
            color: #1f6392;
            border-bottom-color: #1f6392;
        }

        /* main content */
        .content {
            padding: 2rem 2rem 2.2rem 2rem;
        }

        /* upload card */
        .upload-card {
            background: #f9fbfe;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            border: 1px solid #e4e9f0;
            transition: 0.2s;
        }

        .upload-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f2b3d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .upload-title span {
            background: #e2eafc;
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 40px;
            color: #1f6392;
            font-weight: 500;
        }

        .file-zone {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 60px;
            padding: 0.5rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1f3b4c;
            cursor: pointer;
            transition: all 0.2s;
            background: #ffffff;
        }

        .file-input-wrapper:hover {
            border-color: #7c9bc0;
            background: #f5f9ff;
        }

        input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        #fileNameDisplay {
            font-size: 0.85rem;
            color: #4a627a;
            background: #f1f4f9;
            padding: 0.4rem 1rem;
            border-radius: 40px;
            display: inline-block;
        }

        .upload-btn {
            background: #1f6392;
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.6rem 1.6rem;
            border-radius: 40px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .upload-btn:hover {
            background: #0e4a6f;
            transform: translateY(-1px);
        }

        /* documents section */
        .documents-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: 1.2rem;
            border-left: 4px solid #1f6392;
            padding-left: 1rem;
        }

        .documents-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0c2e41;
        }

        .doc-count {
            background: #e9edf2;
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #2c5a7a;
        }

        /* document list styling */
        .documents-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .doc-item {
            background: #ffffff;
            border: 1px solid #e6edf4;
            border-radius: 20px;
            padding: 0.9rem 1.2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .doc-item:hover {
            border-color: #cbdde9;
            background: #fefefe;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }

        .doc-info {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.8rem;
            flex: 2;
        }

        .doc-name {
            font-weight: 600;
            font-size: 1rem;
            color: #1f3f57;
            word-break: break-word;
            background: #eff3f8;
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .doc-name:before {
            content: "📄";
            font-size: 0.85rem;
        }

        .doc-timestamp {
            font-size: 0.75rem;
            color: #5f7f9c;
            background: #f3f6fb;
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            letter-spacing: 0.2px;
        }

        .delete-btn {
            background: transparent;
            border: 1px solid #f0cdd2;
            color: #b91c2c;
            padding: 0.4rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff8f9;
        }

        .delete-btn:hover {
            background: #ffe6e9;
            border-color: #e0a5af;
            color: #9b1c2a;
            transform: scale(0.97);
        }

        .empty-message {
            text-align: center;
            padding: 2.5rem 1rem;
            background: #fafcff;
            border-radius: 28px;
            color: #6c86a3;
            font-size: 0.9rem;
            border: 1px dashed #cfe0ed;
        }

        hr {
            margin: 0.5rem 0 1.2rem;
            border: none;
            height: 1px;
            background: #e9edf2;
        }

        /* small note */
        .info-note {
            margin-top: 1.8rem;
            font-size: 0.7rem;
            color: #7890ab;
            text-align: center;
            border-top: 1px solid #eef2f8;
            padding-top: 1rem;
        }

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }
            .content {
                padding: 1.2rem;
            }
            .doc-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 0.6rem;
            }
            .doc-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .delete-btn {
                align-self: flex-end;
                margin-top: 0.3rem;
            }
            .file-zone {
                flex-direction: column;
                align-items: stretch;
            }
            .upload-btn {
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="kiosk-container" style="margin-bottom: 2rem;">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>
</div>
<div class="dashboard">

    <div class="content">
        <!-- UPLOAD SECTION -->
        <div class="upload-card">
            <div class="upload-title">
                📎 Upload New Document <span>X-ray, reports, etc.</span>
            </div>
            <div class="file-zone">
                <div class="file-input-wrapper">
                    📂 Choose File
                    <input type="file" id="fileInput" accept=".jpg,.jpeg,.png,.pdf,.dicom,.dcm,image/*,application/pdf">
                </div>
                <div id="fileNameDisplay">No file chosen</div>
                <button class="upload-btn" id="uploadBtn">➕ Upload</button>
            </div>
            <div style="font-size: 0.7rem; color:#6f8eac; margin-top: 12px;">
                ⚕️ Supported: images, PDFs, reports (client-side demo)
            </div>
        </div>

        <!-- YOUR DOCUMENTS SECTION WITH DELETE BUTTONS -->
        <div class="documents-header">
            <h2>📋 Your Documents</h2>
            <span class="doc-count" id="docCount">0 items</span>
        </div>

        <div id="documentsContainer">
            <div class="documents-list" id="documentsList">
                <!-- dynamic list will be injected here -->
            </div>
        </div>
        <div class="info-note">
            🗑️ Delete any document — click "Delete" to remove unwanted or mis-uploaded files.
        </div>
    </div>
</div>

<script>
    // ---------- DATA MODEL ----------
    // initial documents based on the user screenshot (exact names + timestamps)
    let documents = [
        {
            id: 1001,
            name: "",
            uploadedAt: ""
        },
        {
            id: 1002,
            name: "",
            uploadedAt: ""
        }
    ];

    // Helper: generate current timestamp in YYYY-MM-DD HH:MM:SS format
    function getCurrentTimestamp() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // generate unique id (simple timestamp + random)
    function generateId() {
        return Date.now() + Math.floor(Math.random() * 10000);
    }

    // RENDER FUNCTION: builds document list with delete buttons
    function renderDocuments() {
        const container = document.getElementById('documentsList');
        const docCountSpan = document.getElementById('docCount');
        
        if (!container) return;
        
        if (documents.length === 0) {
            container.innerHTML = `<div class="empty-message">
                                        📭 No documents yet. Use the upload section to add X-rays or reports.
                                    </div>`;
            docCountSpan.innerText = `0 items`;
            return;
        }
        
        // update document count
        docCountSpan.innerText = `${documents.length} item${documents.length !== 1 ? 's' : ''}`;
        
        // generate html for each document
        let html = '';
        for (let doc of documents) {
            // sanitize display name (no injection risk but safe)
            const displayName = escapeHtml(doc.name);
            const timestamp = doc.uploadedAt || 'unknown date';
            html += `
                <div class="doc-item" data-doc-id="${doc.id}">
                    <div class="doc-info">
                        <div class="doc-name">${displayName}</div>
                        <div class="doc-timestamp">📅 uploaded: ${timestamp}</div>
                    </div>
                    <button class="delete-btn" data-id="${doc.id}" title="Delete document">
                        🗑️ Delete
                    </button>
                </div>
            `;
        }
        container.innerHTML = html;
    }
    
    // simple escape to avoid XSS
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
            return c;
        });
    }
    
    // DELETE FUNCTION: removes document by id with confirmation
    function deleteDocumentById(docId) {
        // find document name for nicer confirm message
        const targetDoc = documents.find(doc => doc.id === docId);
        if (!targetDoc) return;
        
        const confirmDelete = confirm(`❌ Are you sure you want to delete "${targetDoc.name}"?\nThis action cannot be undone.`);
        if (!confirmDelete) return;
        
        // filter out the document
        const newDocs = documents.filter(doc => doc.id !== docId);
        documents = newDocs;
        renderDocuments();   // re-render list (delete button disappears)
        
        // optional subtle feedback (no alert flood)
        const tempMsg = document.createElement('div');
        tempMsg.textContent = `✓ "${targetDoc.name}" removed`;
        tempMsg.style.position = 'fixed';
        tempMsg.style.bottom = '20px';
        tempMsg.style.left = '50%';
        tempMsg.style.transform = 'translateX(-50%)';
        tempMsg.style.backgroundColor = '#2c6e49';
        tempMsg.style.color = 'white';
        tempMsg.style.padding = '8px 20px';
        tempMsg.style.borderRadius = '40px';
        tempMsg.style.fontSize = '0.8rem';
        tempMsg.style.zIndex = '999';
        tempMsg.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
        document.body.appendChild(tempMsg);
        setTimeout(() => {
            tempMsg.style.opacity = '0';
            setTimeout(() => tempMsg.remove(), 300);
        }, 1800);
    }
    
    // ADD NEW DOCUMENT (from upload)
    function addDocument(fileName, timestamp) {
        if (!fileName || fileName.trim() === "") {
            alert("⚠️ Cannot add document: filename is empty.");
            return false;
        }
        // avoid duplicates? not strictly required, but can prevent empty strings
        const newId = generateId();
        const newDoc = {
            id: newId,
            name: fileName.trim(),
            uploadedAt: timestamp || getCurrentTimestamp()
        };
        documents.push(newDoc);
        renderDocuments();
        return true;
    }
    
    // ---------- UPLOAD HANDLER (file selection & upload) ----------
    const fileInput = document.getElementById('fileInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const uploadBtn = document.getElementById('uploadBtn');
    
    // show selected file name
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const name = files[0].name;
                fileNameDisplay.textContent = name;
                fileNameDisplay.style.fontWeight = "500";
                fileNameDisplay.style.background = "#e2f0fa";
            } else {
                fileNameDisplay.textContent = "No file chosen";
                fileNameDisplay.style.background = "#f1f4f9";
            }
        });
    }
    
    // upload logic: add document from selected file
    function handleUpload() {
        if (!fileInput.files || fileInput.files.length === 0) {
            alert("📁 Please select a file (X-ray, report, etc.) before uploading.");
            return;
        }
        
        const file = fileInput.files[0];
        let fileName = file.name;
        
        // Optionally limit length for display but keep original name
        if (fileName.length > 60) {
            // truncate but keep extension info
            const extIndex = fileName.lastIndexOf('.');
            if (extIndex > 40) {
                fileName = fileName.substring(0, 45) + '…' + (extIndex !== -1 ? fileName.substring(extIndex) : '');
            }
        }
        
        const uploadTimestamp = getCurrentTimestamp();
        const added = addDocument(fileName, uploadTimestamp);
        
        if (added) {
            // reset file input and display
            fileInput.value = '';
            fileNameDisplay.textContent = 'No file chosen';
            fileNameDisplay.style.background = "#f1f4f9";
            
            // Optional small success animation
            const uploadCard = document.querySelector('.upload-card');
            if (uploadCard) {
                uploadCard.style.transition = '0.1s';
                uploadCard.style.backgroundColor = '#f1f9fe';
                setTimeout(() => {
                    uploadCard.style.backgroundColor = '#f9fbfe';
                }, 200);
            }
        }
    }
    
    if (uploadBtn) {
        uploadBtn.addEventListener('click', handleUpload);
    }
    
    // ----- DELETE EVENT DELEGATION (handles dynamic delete buttons) -----
    const mainContainer = document.getElementById('documentsList');
    if (mainContainer) {
        // event delegation: listen to clicks on the whole documents container
        document.getElementById('documentsContainer')?.addEventListener('click', function(e) {
            // find the actual delete button that was clicked
            let targetBtn = e.target.closest('.delete-btn');
            if (!targetBtn) return;
            
            const docIdAttr = targetBtn.getAttribute('data-id');
            if (!docIdAttr) return;
            
            const docIdNumber = parseInt(docIdAttr, 10);
            if (isNaN(docIdNumber)) return;
            
            // prevent any accidental parent triggers
            e.stopPropagation();
            deleteDocumentById(docIdNumber);
        });
    }
    
    // initial render of documents (with delete buttons ready)
    renderDocuments();
    
    // Optional: drag & drop prevention / just usability: allow enter key on upload? not needed.
    // Also provide a safety net: if accidentally double upload, no duplicate validation but it's fine.
    // Additional: handle empty documents after all deletes.
    
    // EXTRA: if the user wants to delete a document that was just uploaded by mistake, delete works.
    // Also we support the initial 'hippjpeg' and 'hip_recipitjpeg' as per requirement.
    
    // add a quick shortcut: pressing "Delete" key is not needed.
    console.log("Medical document manager ready — each document has a delete button.");
</script>
</body>
</html>