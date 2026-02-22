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
        
        foreach ($_FILES['pdfs']['tmp_name'] as $key => $tmpName) {
            if (empty($tmpName)) continue;
            
            if ($_FILES['pdfs']['error'][$key] !== UPLOAD_ERR_OK) {
                $message = "❌ Upload error occurred";
                break;
            }

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            
            if ($fileType !== "application/pdf") {
                $message = "❌ Only PDF files are allowed.";
                break;
            }

            $originalName = basename($_FILES['pdfs']['name'][$key]);
            $newPath = $uploadDir . uniqid() . "_" . $originalName;

            if (move_uploaded_file($tmpName, $newPath)) {
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
                        throw new Exception("Ghostscript failed with code: $returnCode");
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
    <title>Merge PDF </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 60px auto;
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
        }
        button:hover {
            background: #45a049;
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
    </style>
</head>
<body>

<h2>📄 Merge PDF Files </h2>

<div class="form-group">
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="pdfs[]" multiple required accept=".pdf,application/pdf">
        <br><br>
        <small>Select at least 2 PDF files (hold Ctrl/Cmd to select multiple)</small>
        <br><br>
        <button type="submit">🔄 Merge PDFs </button>
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


</body>
</html>