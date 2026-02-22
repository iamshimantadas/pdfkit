<?php
// Create directories if not exist with proper permissions
$uploadDir = "uploads/";
$splitDir  = "split/";


$message = "";
$downloadLinks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    
    // Check if file was uploaded
    if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Upload error occurred: " . uploadErrorMessage($_FILES['pdf']['error']);
    } else {
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
        finfo_close($finfo);
        
        if ($fileType !== "application/pdf") {
            $message = "❌ Only PDF files are allowed.";
        } else {
            // Upload the PDF
            $originalName = basename($_FILES['pdf']['name']);
            $uploadedPath = $uploadDir . uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
            
            if (move_uploaded_file($_FILES['pdf']['tmp_name'], $uploadedPath)) {
                
                $totalPages = getTotalPages($uploadedPath);
                
                // Handle single page extraction
                $pageNum = $_POST['page_number'] ?? '';
                $format = $_POST['format'] ?? 'pdf';
                
                if (!is_numeric($pageNum) || $pageNum < 1) {
                    $message = "❌ Please enter a valid page number";
                } else {
                    // Convert to integer
                    $pageNum = (int)$pageNum;
                    
                    if ($totalPages === 0) {
                        $message = "❌ Could not determine the number of pages in the PDF. The file might be corrupted or not a valid PDF.";
                    } else if ($pageNum > $totalPages) {
                        $message = "❌ Page number {$pageNum} exceeds total pages ({$totalPages})";
                    } else {
                        try {
                            // Ensure split directory is writable
                            if (!is_writable($splitDir)) {
                                throw new Exception("Split directory is not writable");
                            }
                            
                            if ($format === 'pdf') {
                                // Extract as PDF
                                $outputFileName = "page_{$pageNum}_" . date('Y-m-d_H-i-s') . "_" . uniqid() . ".pdf";
                                $outputPath = $splitDir . $outputFileName;
                                
                                // Use real paths
                                $inputFile = escapeshellarg(realpath($uploadedPath));
                                $outputFile = escapeshellarg(realpath($splitDir) . '/' . $outputFileName);
                                
                                $cmd = "gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite " .
                                       "-dFirstPage={$pageNum} -dLastPage={$pageNum} " .
                                       "-sOutputFile={$outputFile} " .
                                       "{$inputFile} 2>&1";
                                
                                exec($cmd, $outputLines, $returnCode);
                                
                                if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                                    $downloadLinks[] = [
                                        'path' => $outputPath,
                                        'name' => "Page_{$pageNum}.pdf",
                                        'desc' => "Page {$pageNum} as PDF"
                                    ];
                                    $message = "✅ Page {$pageNum} extracted as PDF successfully!";
                                } else {
                                    $message = "❌ Failed to extract page {$pageNum}. Ghostscript error: " . implode("\n", $outputLines);
                                }
                                
                            } else {
                                // Extract as PNG
                                $outputFileName = "page_{$pageNum}_" . date('Y-m-d_H-i-s') . "_" . uniqid() . ".png";
                                $outputPath = $splitDir . $outputFileName;
                                
                                // Use real paths
                                $inputFile = escapeshellarg(realpath($uploadedPath));
                                $outputFile = escapeshellarg(realpath($splitDir) . '/' . $outputFileName);
                                
                                // Ghostscript command to convert page to PNG
                                $cmd = "gs -dBATCH -dNOPAUSE -sDEVICE=png16m " .
                                       "-dFirstPage={$pageNum} -dLastPage={$pageNum} " .
                                       "-r150 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 " .
                                       "-sOutputFile={$outputFile} " .
                                       "{$inputFile} 2>&1";
                                
                                exec($cmd, $outputLines, $returnCode);
                                
                                if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                                    $downloadLinks[] = [
                                        'path' => $outputPath,
                                        'name' => "Page_{$pageNum}.png",
                                        'desc' => "Page {$pageNum} as PNG"
                                    ];
                                    $message = "✅ Page {$pageNum} extracted as PNG successfully!";
                                } else {
                                    $message = "❌ Failed to extract page {$pageNum} as PNG. Ghostscript error: " . implode("\n", $outputLines);
                                }
                            }
                            
                        } catch (Exception $e) {
                            $message = "❌ Error splitting PDF: " . $e->getMessage();
                        }
                    }
                }
            } else {
                $message = "❌ Failed to upload file. Check directory permissions.";
            }
        }
    }
}

// Helper function for upload errors
function uploadErrorMessage($error) {
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

// Helper function to get total pages in PDF
function getTotalPages($pdfPath) {
    // Try multiple methods to get page count
    
    // Method 1: Using pdfinfo (poppler-utils)
    $cmd = "pdfinfo " . escapeshellarg($pdfPath) . " 2>&1 | grep 'Pages:' | awk '{print $2}'";
    $output = shell_exec($cmd);
    $pages = (int)trim($output);
    
    if ($pages > 0) {
        return $pages;
    }
    
    // Method 2: Using Ghostscript
    $cmd = "gs -q -dNODISPLAY -c \"(" . escapeshellarg($pdfPath) . ") (r) file runpdfbegin pdfpagecount = quit\" 2>&1";
    $output = shell_exec($cmd);
    $pages = (int)trim($output);
    
    if ($pages > 0) {
        return $pages;
    }
    
    // Method 3: Using ImageMagick
    $cmd = "identify -format '%n\n' " . escapeshellarg($pdfPath) . " 2>&1 | head -1";
    $output = shell_exec($cmd);
    $pages = (int)trim($output);
    
    if ($pages > 0) {
        return $pages;
    }
    
    return 0;
}

// Function to check if Ghostscript is available
function checkGhostscript() {
    $cmd = "gs --version 2>&1";
    $output = shell_exec($cmd);
    return !empty($output);
}

// Get real paths for display
$splitRealPath = realpath($splitDir);
$uploadRealPath = realpath($uploadDir);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Extract PDF Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            line-height: 1.6;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        input[type="file"] {
            padding: 10px;
            width: 100%;
            border: 2px dashed #ccc;
            border-radius: 4px;
            background: white;
            margin-bottom: 20px;
        }
        input[type="file"]:hover {
            border-color: #4CAF50;
        }
        input[type="number"] {
            padding: 10px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            margin: 10px 0;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
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
        button:hover {
            background: #45a049;
        }
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            font-weight: bold;
            white-space: pre-line;
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
        .download-section {
            margin-top: 20px;
            padding: 20px;
            background: #e8f5e9;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }
        .download-section h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        a.download {
            display: inline-block;
            padding: 12px 25px;
            background: white;
            border: 2px solid #4CAF50;
            color: #4CAF50;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: all 0.3s;
            text-align: center;
        }
        a.download:hover {
            background: #4CAF50;
            color: white;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h4 {
            margin-top: 0;
            color: #1976D2;
        }
        .radio-group {
            margin: 20px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .radio-group label {
            margin-right: 20px;
            cursor: pointer;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
        }
        .back-link:hover {
            color: #4CAF50;
        }
        .system-check {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .dir-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
        }
        .input-group {
            margin: 15px 0;
        }
        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>

<a href="merge.php" class="back-link">PDF Merge</a>

<h2>📄 Extract Single Page from PDF</h2>

<div class="dir-info">
    📁 Output directory: <?= htmlspecialchars($splitRealPath ?: $splitDir) ?>
</div>


<div class="form-group">
    <form method="POST" enctype="multipart/form-data" id="extractForm">
        <input type="file" name="pdf" id="pdfInput" accept=".pdf,application/pdf" required>
        
        <div class="info-box">
            <h4>📌 Extract a Single Page</h4>
            <p>Extract a specific page from your PDF as either PDF or PNG image</p>
        </div>
        
        <div class="input-group">
            <label for="page_number">Page Number:</label>
            <input type="number" id="page_number" name="page_number" min="1" placeholder="Enter page number" required>
        </div>
        
        <div class="radio-group">
            <label><input type="radio" name="format" value="pdf" checked> PDF Document</label>
            <label><input type="radio" name="format" value="png"> PNG Image</label>
        </div>
        
        <button type="submit">✂️ Extract Page</button>
    </form>
</div>

<?php if (!empty($message)): ?>
    <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= nl2br(htmlspecialchars($message)) ?>
    </div>
<?php endif; ?>

<?php if (!empty($downloadLinks)): ?>
    <div class="download-section">
        <h3>📥 Download Extracted File:</h3>
        <?php foreach ($downloadLinks as $link): ?>
            <a class="download" href="<?= htmlspecialchars($link['path']) ?>" download="<?= htmlspecialchars($link['name']) ?>">
                ⬇️ <?= htmlspecialchars($link['desc']) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Form validation
document.getElementById('extractForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('pdfInput');
    if (!fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a PDF file');
        return;
    }
    
    const pageNumber = document.getElementById('page_number');
    if (!pageNumber.value) {
        e.preventDefault();
        alert('Please enter a page number');
        pageNumber.focus();
        return;
    }
    
    const pageNum = parseInt(pageNumber.value);
    if (isNaN(pageNum) || pageNum < 1) {
        e.preventDefault();
        alert('Page number must be a valid number greater than 0');
        pageNumber.focus();
        return;
    }
});

// Show file name when selected
document.getElementById('pdfInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const fileName = this.files[0].name;
        const fileSize = (this.files[0].size / 1024).toFixed(2);
        // You could add a preview element here if desired
    }
});
</script>

</body>
</html>