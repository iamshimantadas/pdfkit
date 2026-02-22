<?php
$uploadDir = __DIR__ . "/uploads/";
$outputDir = __DIR__ . "/text_output/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$message = "";
$downloadLink = "";
$extractedText = "";
$fileName = "";

// Use absolute path (VERY IMPORTANT)
$pdftotextPath = "/usr/bin/pdftotext";

if (!file_exists($pdftotextPath)) {
    $pdftotextInstalled = false;
} else {
    $pdftotextInstalled = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {

    $uploadedFile = $_FILES['pdf_file'];
    $layout = $_POST['layout'] ?? 'raw';

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Upload error occurred";
    } else {

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $uploadedFile['tmp_name']);
        finfo_close($finfo);

        if ($fileType !== "application/pdf") {
            $message = "❌ Only PDF files are allowed.";
        } else {

            $originalName = basename($uploadedFile['name']);
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $fileName = $safeName;

            $pdfPath = $uploadDir . uniqid() . "_" . $safeName . ".pdf";

            if (move_uploaded_file($uploadedFile['tmp_name'], $pdfPath)) {

                $outputFileName = $safeName . "_" . date('Y-m-d_H-i-s') . ".txt";
                $outputFilePath = $outputDir . $outputFileName;

                // Build command
                $cmd = $pdftotextPath . " -enc UTF-8 ";

                if ($layout === 'layout') {
                    $cmd .= "-layout ";
                } elseif ($layout === 'raw') {
                    $cmd .= "-raw ";
                }

                $cmd .= escapeshellarg($pdfPath) . " " . escapeshellarg($outputFilePath) . " 2>&1";

                exec($cmd, $outputLines, $returnCode);

                if ($returnCode === 0 && file_exists($outputFilePath)) {

                    $extractedText = file_get_contents($outputFilePath);

                    if (trim($extractedText) === "") {
                        $message = "⚠️ PDF contains no selectable text (Maybe scanned PDF?)";
                    } else {
                        $message = "✅ PDF converted successfully!";
                        $downloadLink = "text_output/" . $outputFileName;
                    }

                } else {
                    $message = "❌ Conversion failed.<br><pre>" . implode("\n", $outputLines) . "</pre>";
                }

            } else {
                $message = "❌ Failed to move uploaded file";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PDF to Text Converter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .form-row {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="file"] {
            padding: 10px;
            width: 100%;
            border: 2px dashed #4CAF50;
            border-radius: 4px;
            background: white;
            cursor: pointer;
        }
        input[type="file"]:hover {
            border-color: #45a049;
        }
        select {
            padding: 10px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
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
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .text-output {
            margin-top: 20px;
            padding: 20px;
            background: #f0f0f0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .text-output h3 {
            margin-top: 0;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .text-output pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            background: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            border: 1px solid #ccc;
            margin: 10px 0;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .copy-btn, .download-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            flex: 1;
        }
        .copy-btn {
            background: #2196F3;
            color: white;
        }
        .copy-btn:hover {
            background: #0b7dda;
        }
        .copy-btn.copied {
            background: #4CAF50;
        }
        .download-btn {
            background: #4CAF50;
            color: white;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .download-btn:hover {
            background: #45a049;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .installation-guide {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            border: 1px solid #ddd;
        }
        .file-info {
            background: #e8f5e8;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            border-left: 4px solid #4CAF50;
        }
        .stats {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>

<h2>📄 PDF to Text Converter</h2>
<p style="text-align: center; color: #666;">Using Poppler-utils (pdftotext)</p>

<?php if (!$pdftotextInstalled): ?>
    <div class="message warning">
        ⚠️ Poppler-utils is not installed on your system!
        
        <div class="installation-guide">
            <strong>Installation instructions:</strong><br><br>
            
            <strong>Ubuntu/Debian:</strong><br>
            <code>sudo apt-get update && sudo apt-get install poppler-utils</code><br><br>
            
            <strong>CentOS/RHEL/Fedora:</strong><br>
            <code>sudo yum install poppler-utils</code> or <code>sudo dnf install poppler-utils</code>
        </div>
    </div>
<?php endif; ?>

<div class="form-group">
    <form method="POST" enctype="multipart/form-data" id="pdfForm">
        <div class="form-row">
            <label for="pdf_file">📁 Select PDF File:</label>
            <input type="file" name="pdf_file" id="pdfInput" accept=".pdf,application/pdf" required>
        </div>
        
        <div class="form-row">
            <label for="layout">📐 Text Layout Options:</label>
            <select name="layout" id="layout">
                <option value="raw">Raw text (maintains reading order) - Recommended</option>
                <option value="layout">Physical layout (preserves formatting/columns)</option>
                <option value="simple">Simple text (removes most formatting)</option>
            </select>
            <small style="color: #666; display: block; margin-top: 5px;">Different layout options affect how text is extracted from the PDF</small>
        </div>
        
        <button type="submit" id="convertBtn" <?= !$pdftotextInstalled ? 'disabled' : '' ?>>
            🔄 Convert PDF to Text
        </button>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="message <?= strpos($message, '✅') !== false ? 'success' : (strpos($message, '⚠️') !== false ? 'warning' : 'error') ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (!empty($extractedText)): ?>
    <div class="text-output">
        <h3>
            📝 Extracted Text
            <span class="stats">
                <span>📊 Lines: <?= substr_count($extractedText, "\n") + 1 ?></span>
                <span>📝 Words: <?= str_word_count($extractedText) ?></span>
                <span>📏 Characters: <?= strlen($extractedText) ?></span>
            </span>
        </h3>
        
        <?php if (!empty($fileName)): ?>
            <div class="file-info">
                <strong>File:</strong> <?= htmlspecialchars($fileName) ?>.pdf
            </div>
        <?php endif; ?>
        
        <pre id="textContent"><?= htmlspecialchars($extractedText) ?></pre>
        
        <div class="action-buttons">
            <button class="copy-btn" id="copyBtn" onclick="copyText()">📋 Copy to Clipboard</button>
            <?php if (!empty($downloadLink)): ?>
                <a class="download-btn" href="<?= htmlspecialchars($downloadLink) ?>" download>⬇️ Download Text File</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// File input validation
document.getElementById('pdfInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const convertBtn = document.getElementById('convertBtn');
    
    if (file) {
        if (file.type !== 'application/pdf') {
            alert('Please select a valid PDF file');
            this.value = '';
            convertBtn.disabled = <?= $pdftotextInstalled ? 'false' : 'true' ?>;
        } else {
            convertBtn.disabled = false;
        }
    }
});

// Copy text to clipboard function
function copyText() {
    const textContent = document.getElementById('textContent').innerText;
    const copyBtn = document.getElementById('copyBtn');
    
    navigator.clipboard.writeText(textContent).then(function() {
        // Success feedback
        copyBtn.classList.add('copied');
        copyBtn.innerHTML = '✅ Copied!';
        
        setTimeout(function() {
            copyBtn.classList.remove('copied');
            copyBtn.innerHTML = '📋 Copy to Clipboard';
        }, 2000);
    }).catch(function(err) {
        console.error('Failed to copy text: ', err);
        alert('Failed to copy text. Please try again or download the file.');
    });
}

// Optional: Add drag and drop functionality
const dropZone = document.getElementById('pdfForm');
const fileInput = document.getElementById('pdfInput');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('highlight');
}

function unhighlight(e) {
    dropZone.classList.remove('highlight');
}

dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        fileInput.files = files;
        // Trigger change event
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
}
</script>

</body>
</html>