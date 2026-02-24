<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['word_file'])) {
        die("No file uploaded.");
    }

    $uploadDir = __DIR__ . "/word_uploads/";
    $outputDir = __DIR__ . "/word_uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExtensions = ['doc', 'docx'];
    $fileExtension = strtolower(pathinfo($_FILES['word_file']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        die("Only DOC and DOCX files are allowed.");
    }

    if ($_FILES['word_file']['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed.");
    }

    $fileName = time() . "_" . basename($_FILES['word_file']['name']);
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['word_file']['tmp_name'], $targetPath)) {
        die("Failed to save file.");
    }

    // Create a temporary home directory for LibreOffice
    $tempHome = $uploadDir . "libreoffice_home_" . time();
    if (!is_dir($tempHome)) {
        mkdir($tempHome, 0777, true);
    }

    // Set environment variables to fix permission issues
    $envVars = "export HOME=" . escapeshellarg($tempHome) . " && " .
               "export XDG_CONFIG_HOME=" . escapeshellarg($tempHome . "/config") . " && " .
               "export XDG_CACHE_HOME=" . escapeshellarg($tempHome . "/cache") . " && ";

    // Run LibreOffice conversion with environment variables
    $command = $envVars . "libreoffice --headless --convert-to pdf --outdir " . 
               escapeshellarg($outputDir) . " " . escapeshellarg($targetPath) . " 2>&1";
    
    exec($command, $output, $returnCode);

    // Clean up temp home directory
    if (is_dir($tempHome)) {
        array_map('unlink', glob("$tempHome/*"));
        rmdir($tempHome);
    }

    if ($returnCode !== 0) {
        $error = "Conversion failed. Please check server configuration.";
        $errorDetails = implode("\n", $output);
        $showError = true;
    } else {
        $pdfFile = pathinfo($fileName, PATHINFO_FILENAME) . ".pdf";
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Word to PDF Converter</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: #2d3748;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px 20px;
        }

        .upload-area {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            background: #f7fafc;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 15px;
        }

        .upload-area:hover {
            border-color: #4299e1;
            background: #ebf8ff;
        }

        .upload-area.dragover {
            border-color: #4299e1;
            background: #ebf8ff;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .upload-text {
            color: #2d3748;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .upload-hint {
            color: #718096;
            font-size: 14px;
        }

        .file-input {
            display: none;
        }

        .file-info {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
        }

        .file-info.show {
            display: flex;
        }

        .file-icon {
            font-size: 24px;
            margin-right: 12px;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #2d3748;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .file-size {
            color: #718096;
            font-size: 12px;
        }

        .remove-file {
            color: #e53e3e;
            cursor: pointer;
            font-size: 20px;
            padding: 0 5px;
        }

        .btn {
            background: #4299e1;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn:hover:not(:disabled) {
            background: #3182ce;
        }

        .btn:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #718096;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .result {
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }

        .success {
            background: #f0fff4;
            border: 1px solid #c6f6d5;
            color: #22543d;
        }

        .error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #742a2a;
        }

        .download-link {
            display: inline-block;
            background: #48bb78;
            color: white;
            text-decoration: none;
            padding: 10px 25px;
            border-radius: 6px;
            margin: 15px 0;
        }

        .download-link:hover {
            background: #38a169;
        }

        .loader {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .features {
            display: flex;
            justify-content: space-around;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📄 Word to PDF</h1>
                <p>Convert Word documents to PDF instantly</p>
            </div>
            
            <div class="content">
                <?php if (isset($success) && $success): ?>
                    <div class="result success">
                        <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                        <h3 style="margin-bottom: 10px;">Success!</h3>
                        <p style="margin-bottom: 15px;">Your PDF is ready to download</p>
                        <a href="word_uploads/<?php echo htmlspecialchars($pdfFile); ?>" class="download-link" download>📥 Download PDF</a>
                        <button onclick="window.location.href=''" class="btn btn-secondary">Convert Another</button>
                    </div>
                <?php elseif (isset($showError) && $showError): ?>
                    <div class="result error">
                        <div style="font-size: 48px; margin-bottom: 10px;">❌</div>
                        <h3 style="margin-bottom: 10px;">Conversion Failed</h3>
                        <p style="margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></p>
                        <?php if (isset($errorDetails)): ?>
                            <details style="text-align: left; background: #fee; padding: 10px; border-radius: 4px; font-size: 12px; margin-bottom: 15px;">
                                <summary>Error Details</summary>
                                <pre style="white-space: pre-wrap;"><?php echo htmlspecialchars($errorDetails); ?></pre>
                            </details>
                        <?php endif; ?>
                        <button onclick="window.location.href=''" class="btn btn-secondary">Try Again</button>
                    </div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">📄</div>
                            <div class="upload-text">Drop your Word file here</div>
                            <div class="upload-hint">or click to browse (DOC, DOCX)</div>
                        </div>
                        
                        <input type="file" name="word_file" id="fileInput" class="file-input" accept=".doc,.docx" required>
                        
                        <div class="file-info" id="fileInfo">
                            <div class="file-icon">📄</div>
                            <div class="file-details">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-size" id="fileSize"></div>
                            </div>
                            <div class="remove-file" onclick="removeFile()">✕</div>
                        </div>

                        <button type="submit" class="btn" id="submitBtn" disabled>
                            <span id="btnText">Convert to PDF</span>
                        </button>
                    </form>

                    <div class="features">
                        <span>⚡ Fast</span>
                        <span>🔒 Secure</span>
                        <span>📱 Mobile</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');

        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFileSelect();
        });

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            
            if (file) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['doc', 'docx'].includes(ext)) {
                    alert('Please select a valid Word document (DOC or DOCX)');
                    fileInput.value = '';
                    return;
                }

                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.add('show');
                submitBtn.disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function removeFile() {
            fileInput.value = '';
            fileInfo.classList.remove('show');
            submitBtn.disabled = true;
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a file first');
                return;
            }

            submitBtn.disabled = true;
            btnText.innerHTML = '<span class="loader"></span>Converting...';
        });
    </script>
</body>
</html>