<?php
$uploadDir = "uploads/";
$compressedDir = "compressed/";

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$gsBinary = $isWindows ? "gswin64c" : "gs";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($compressedDir)) mkdir($compressedDir, 0777, true);

$message = "";
$downloadLink = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {

    if (!empty($_FILES['pdf']['tmp_name'])) {

        $file = $_FILES['pdf'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($fileType === "application/pdf") {

                $compressionLevel = intval($_POST['compression'] ?? 70);
                if ($compressionLevel < 50 || $compressionLevel > 90) {
                    $compressionLevel = 70;
                }

                $originalName = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", basename($file['name']));
                $uploadPath = $uploadDir . uniqid() . "_" . $originalName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {

                    $originalSize = filesize($uploadPath);

                    // Compression mapping
                    if ($compressionLevel <= 65) {
                        $pdfSettings = "/ebook";
                    } elseif ($compressionLevel <= 80) {
                        $pdfSettings = "/screen";
                    } else {
                        $pdfSettings = "/screen";
                    }

                    $compressedFileName = "compressed_" . time() . ".pdf";
                    $compressedFilePath = $compressedDir . $compressedFileName;

                    $input = escapeshellarg($uploadPath);
                    $output = escapeshellarg($compressedFilePath);

                    $cmd = "$gsBinary -sDEVICE=pdfwrite ".
                           "-dCompatibilityLevel=1.4 ".
                           "-dPDFSETTINGS=$pdfSettings ".
                           "-dNOPAUSE -dQUIET -dBATCH ".
                           "-sOutputFile=$output $input 2>&1";

                    exec($cmd, $outputLines, $returnCode);

                    if ($returnCode === 0 && file_exists($compressedFilePath)) {

                        $compressedSize = filesize($compressedFilePath);
                        $ratio = 100 - round(($compressedSize / $originalSize) * 100);

                        $message = "✅ Compression Successful<br>";
                        $message .= "Original: " . formatSize($originalSize);
                        $message .= " → Compressed: " . formatSize($compressedSize);
                        $message .= "<br>Reduction: {$ratio}%";

                        $downloadLink = $compressedFilePath;

                        unlink($uploadPath);

                    } else {
                        $message = "❌ Compression failed.";
                        unlink($uploadPath);
                    }
                }
            } else {
                $message = "❌ Only PDF files allowed.";
            }
        } else {
            $message = "❌ Upload error.";
        }
    }
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Modern PDF Compressor</title>
<style>
body {
    margin:0;
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.card {
    background:white;
    padding:35px;
    width:420px;
    border-radius:15px;
    box-shadow:0 20px 40px rgba(0,0,0,0.15);
}
h2 {
    text-align:center;
    margin-bottom:25px;
}
input[type=file] {
    width:100%;
    padding:10px;
    border:2px dashed #ccc;
    border-radius:8px;
    cursor:pointer;
}
.slider-box {
    margin-top:20px;
}
.slider-value {
    font-size:22px;
    font-weight:bold;
    text-align:center;
    margin:10px 0;
}
input[type=range] {
    width:100%;
}
button {
    width:100%;
    padding:12px;
    margin-top:20px;
    border:none;
    border-radius:8px;
    background:#4e73df;
    color:white;
    font-size:16px;
    cursor:pointer;
    transition:0.3s;
}
button:hover {
    background:#2e59d9;
}
.message {
    margin-top:15px;
    padding:10px;
    background:#f8f9fc;
    border-radius:8px;
    font-size:14px;
}
a.download {
    display:block;
    text-align:center;
    margin-top:15px;
    padding:10px;
    background:#1cc88a;
    color:white;
    text-decoration:none;
    border-radius:8px;
}
.quality {
    text-align:center;
    font-size:14px;
    margin-top:5px;
}
</style>
</head>
<body>

<div class="card">
<h2>📄 PDF Compressor</h2>

<form method="POST" enctype="multipart/form-data" id="form">
<input type="file" name="pdf" required accept="application/pdf">

<div class="slider-box">
    <div class="slider-value">
        Compression: <span id="percent">70%</span>
    </div>

    <input type="range" name="compression" id="slider"
           min="50" max="90" step="5" value="70">

    <div class="quality" id="qualityLabel">
        Balanced Compression
    </div>
</div>

<button type="submit">🗜 Compress PDF</button>
</form>

<?php if($message): ?>
<div class="message"><?= $message ?></div>
<?php endif; ?>

<?php if($downloadLink): ?>
<a class="download" href="<?= htmlspecialchars($downloadLink) ?>" download>
⬇ Download Compressed File
</a>
<?php endif; ?>

</div>

<script>
const slider = document.getElementById('slider');
const percent = document.getElementById('percent');
const qualityLabel = document.getElementById('qualityLabel');

function updateUI() {
    percent.textContent = slider.value + "%";

    if (slider.value <= 65) {
        qualityLabel.textContent = "Balanced Compression";
        qualityLabel.style.color = "#f6c23e";
    } else if (slider.value <= 80) {
        qualityLabel.textContent = "Strong Compression";
        qualityLabel.style.color = "#e74a3b";
    } else {
        qualityLabel.textContent = "Maximum Compression";
        qualityLabel.style.color = "#c0392b";
    }
}

slider.addEventListener('input', updateUI);
updateUI();
</script>

</body>
</html>