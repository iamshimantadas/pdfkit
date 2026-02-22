<?php
// Create directories if not exist
$uploadDir = "uploads/";
$mergeDir  = "merge/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!is_dir($mergeDir)) {
    mkdir($mergeDir, 0777, true);
}

$message = "";
$downloadLink = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdfs'])) {

    $uploadedFiles = [];
    
    // Check if any files were actually uploaded
    if (empty($_FILES['pdfs']['tmp_name'][0])) {
        $message = "❌ No files were uploaded.";
    } else {
        
        // Check if order is specified
        $fileOrder = isset($_POST['file_order']) ? explode(',', $_POST['file_order']) : [];
        
        // Reorder files based on user preference
        $reordered_files = [];
        if (!empty($fileOrder) && count($fileOrder) == count($_FILES['pdfs']['name'])) {
            foreach ($fileOrder as $index) {
                if (is_numeric($index) && isset($_FILES['pdfs']['tmp_name'][$index])) {
                    $reordered_files[] = [
                        'tmp_name' => $_FILES['pdfs']['tmp_name'][$index],
                        'name' => $_FILES['pdfs']['name'][$index],
                        'error' => $_FILES['pdfs']['error'][$index]
                    ];
                }
            }
        } else {
            // Use original order
            foreach ($_FILES['pdfs']['tmp_name'] as $key => $tmpName) {
                $reordered_files[] = [
                    'tmp_name' => $tmpName,
                    'name' => $_FILES['pdfs']['name'][$key],
                    'error' => $_FILES['pdfs']['error'][$key]
                ];
            }
        }
        
        foreach ($reordered_files as $key => $file) {
            if (empty($file['tmp_name'])) continue;
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = "❌ Upload error occurred";
                break;
            }

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($fileType !== "application/pdf") {
                $message = "❌ Only PDF files are allowed.";
                break;
            }

            $originalName = basename($file['name']);
            $newPath = $uploadDir . uniqid() . "_" . $originalName;

            if (move_uploaded_file($file['tmp_name'], $newPath)) {
                $uploadedFiles[] = $newPath;
            } else {
                $message = "❌ Failed to move uploaded file";
                break;
            }
        }

        $fileCount = count($uploadedFiles);
        
        if (empty($message)) {
            if ($fileCount < 2) {
                $message = "❌ Please upload at least 2 PDF files.";
            } else {
                try {
                    $mergedFileName = "merged_" . date('Y-m-d_H-i-s') . ".pdf";
                    $mergedFilePath = $mergeDir . $mergedFileName;
                    
                    // Build Ghostscript command
                    $files = implode(' ', array_map('escapeshellarg', $uploadedFiles));
                    $output = escapeshellarg($mergedFilePath);
                    
                    // Ghostscript command for merging PDFs
                    $cmd = "gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=$output $files 2>&1";
                    
                    // Execute command
                    exec($cmd, $outputLines, $returnCode);
                    
                    if ($returnCode === 0 && file_exists($mergedFilePath) && filesize($mergedFilePath) > 0) {
                        $message = "✅ PDF Merged Successfully! (" . $fileCount . " files merged)";
                        $downloadLink = $mergedFilePath;
                        
                        // Clean up uploaded files (optional)
                        foreach ($uploadedFiles as $file) {
                            unlink($file);
                        }
                    } else {
                        throw new Exception("Server got some error");
                    }
                    
                } catch (Exception $e) {
                    $message = "❌ Error merging PDFs: " . $e->getMessage();
                    
                    // Clean up uploaded files on error
                    // foreach ($uploadedFiles as $file) {
                    //     if (file_exists($file)) {
                    //         unlink($file);
                    //     }
                    // }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Merge PDF with Drag & Drop Ordering</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            line-height: 1.6;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        input[type="file"] {
            padding: 10px;
            width: 100%;
            border: 2px dashed #ccc;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        input[type="file"]:hover {
            border-color: #4CAF50;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            width: 100%;
            transition: background 0.3s;
        }
        button:hover:not(:disabled) {
            background: #45a049;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            font-weight: bold;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        a.download {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 25px;
            border: 2px solid #4CAF50;
            color: #4CAF50;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: all 0.3s;
        }
        a.download:hover {
            background: #4CAF50;
            color: white;
        }
        .info {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
            padding: 10px;
            background: #e9ecef;
            border-radius: 4px;
        }
        
        /* File list styling */
        .file-list-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: none;
        }
        .file-list-header {
            background: #4CAF50;
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-count {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        .file-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
        }
        .file-item {
            padding: 12px 15px;
            background: white;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            cursor: move;
            transition: background 0.2s;
        }
        .file-item:hover {
            background: #f5f5f5;
        }
        .file-item.dragging {
            opacity: 0.5;
            background: #e3f2fd;
        }
        .file-item.drag-over {
            border-top: 2px solid #4CAF50;
        }
        .file-number {
            width: 25px;
            height: 25px;
            background: #4CAF50;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 12px;
        }
        .file-name {
            flex: 1;
            font-size: 14px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-size {
            font-size: 12px;
            color: #999;
            margin-left: 10px;
        }
        .drag-handle {
            color: #999;
            margin-left: 10px;
            font-size: 18px;
            cursor: grab;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .remove-file {
            color: #ff4444;
            margin-left: 10px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }
        .remove-file:hover {
            color: #cc0000;
        }
        .clear-all {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .clear-all:hover {
            background: #c82333;
        }
        .file-input-info {
            text-align: center;
            padding: 10px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<h2>📄 Merge PDF Files with Drag & Drop Ordering</h2>

<div class="form-group">
    <form method="POST" enctype="multipart/form-data" id="pdfForm">
        <input type="file" name="pdfs[]" id="pdfInput" multiple accept=".pdf,application/pdf">
        <input type="hidden" name="file_order" id="file_order" value="">
        <br><br>
        <small>Select at least 2 PDF files (hold Ctrl/Cmd to select multiple)</small>
        
        <!-- File list container for drag & drop -->
        <div class="file-list-container" id="fileListContainer">
            <div class="file-list-header">
                <span>Selected Files (Drag to reorder)</span>
                <span>
                    <span class="file-count" id="fileCount">0 files</span>
                    <button type="button" class="clear-all" id="clearAllBtn" onclick="clearAllFiles()">Clear All</button>
                </span>
            </div>
            <ul class="file-list" id="fileList"></ul>
        </div>
        
        <div class="file-input-info" id="fileInputInfo">
            No files selected
        </div>
        
        <br>
        <button type="submit" id="mergeBtn" disabled>🔄 Merge PDFs</button>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (!empty($downloadLink)): ?>
    <a class="download" href="<?= htmlspecialchars($downloadLink) ?>" download>
        ⬇️ Download Merged PDF
    </a>
<?php endif; ?>

<script>
let files = [];
let dragSrcIndex = null;

document.getElementById('pdfInput').addEventListener('change', function(e) {
    const newFiles = Array.from(e.target.files);
    
    // Add new files to the array
    newFiles.forEach(file => {
        if (file.type === 'application/pdf') {
            files.push(file);
        }
    });
    
    updateFileList();
    updateFileOrder();
});

function updateFileList() {
    const fileList = document.getElementById('fileList');
    const container = document.getElementById('fileListContainer');
    const mergeBtn = document.getElementById('mergeBtn');
    const fileCount = document.getElementById('fileCount');
    const fileInputInfo = document.getElementById('fileInputInfo');
    
    if (files.length > 0) {
        container.style.display = 'block';
        fileInputInfo.innerHTML = `${files.length} file(s) selected. Drag to reorder.`;
        
        // Enable merge button if at least 2 files
        mergeBtn.disabled = files.length < 2;
        
        // Update file count
        fileCount.textContent = files.length + ' file' + (files.length !== 1 ? 's' : '');
        
        // Clear and rebuild list
        fileList.innerHTML = '';
        files.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'file-item';
            li.draggable = true;
            li.dataset.index = index;
            
            // Add drag events
            li.addEventListener('dragstart', handleDragStart);
            li.addEventListener('dragenter', handleDragEnter);
            li.addEventListener('dragover', handleDragOver);
            li.addEventListener('dragleave', handleDragLeave);
            li.addEventListener('drop', handleDrop);
            li.addEventListener('dragend', handleDragEnd);
            
            // Format file size
            let fileSize = file.size;
            let sizeStr = '';
            if (fileSize < 1024) {
                sizeStr = fileSize + ' B';
            } else if (fileSize < 1024 * 1024) {
                sizeStr = (fileSize / 1024).toFixed(1) + ' KB';
            } else {
                sizeStr = (fileSize / (1024 * 1024)).toFixed(1) + ' MB';
            }
            
            li.innerHTML = `
                <span class="file-number">${index + 1}</span>
                <span class="file-name" title="${file.name}">${file.name}</span>
                <span class="file-size">(${sizeStr})</span>
                <span class="drag-handle">⋮⋮</span>
                <span class="remove-file" onclick="removeFile(${index})" title="Remove file">✕</span>
            `;
            
            fileList.appendChild(li);
        });
    } else {
        container.style.display = 'none';
        fileInputInfo.innerHTML = 'No files selected';
        mergeBtn.disabled = true;
        fileCount.textContent = '0 files';
    }
}

function removeFile(index) {
    files.splice(index, 1);
    updateFileList();
    updateFileOrder();
    
    // Reset file input
    document.getElementById('pdfInput').value = '';
}

function clearAllFiles() {
    files = [];
    updateFileList();
    updateFileOrder();
    document.getElementById('pdfInput').value = '';
}

function handleDragStart(e) {
    dragSrcIndex = parseInt(e.target.dataset.index);
    e.target.classList.add('dragging');
    e.dataTransfer.setData('text/plain', dragSrcIndex);
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    const target = e.target.closest('.file-item');
    if (target && target.dataset.index != dragSrcIndex) {
        target.classList.add('drag-over');
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragLeave(e) {
    const target = e.target.closest('.file-item');
    if (target) {
        target.classList.remove('drag-over');
    }
}

function handleDrop(e) {
    e.preventDefault();
    const target = e.target.closest('.file-item');
    if (!target) return;
    
    target.classList.remove('drag-over');
    
    const dropIndex = parseInt(target.dataset.index);
    
    // Reorder files
    if (dragSrcIndex !== null && dragSrcIndex !== dropIndex) {
        const [reorderedItem] = files.splice(dragSrcIndex, 1);
        files.splice(dropIndex, 0, reorderedItem);
        updateFileList();
        updateFileOrder();
    }
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    document.querySelectorAll('.file-item').forEach(item => {
        item.classList.remove('drag-over');
    });
    dragSrcIndex = null;
}

function updateFileOrder() {
    // Create a DataTransfer object to rebuild the FileList
    const dataTransfer = new DataTransfer();
    files.forEach(file => {
        dataTransfer.items.add(file);
    });
    
    // Update the file input
    document.getElementById('pdfInput').files = dataTransfer.files;
    
    // Update hidden input with order
    const orderIndices = files.map((_, index) => index);
    document.getElementById('file_order').value = orderIndices.join(',');
}

// Prevent default drag behavior on document
document.addEventListener('dragover', function(e) {
    e.preventDefault();
});

document.addEventListener('drop', function(e) {
    e.preventDefault();
});
</script>

</body>
</html>