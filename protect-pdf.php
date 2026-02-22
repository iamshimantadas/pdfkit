<?php
$uploadDir    = "uploads/";
$protectedDir = "protected/";

// Detect OS for qpdf binary
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === "WIN";
$qpdfBinary = $isWindows ? "qpdf.exe" : "qpdf";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($protectedDir)) mkdir($protectedDir, 0777, true);

$message      = "";
$downloadLink = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["pdf"])) {

    if (!empty($_FILES["pdf"]["tmp_name"])) {

        $file = $_FILES["pdf"];

        if ($file["error"] === UPLOAD_ERR_OK) {

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($finfo, $file["tmp_name"]);
            finfo_close($finfo);

            if ($fileType === "application/pdf") {

                $password = trim($_POST["password"] ?? "");

                if (empty($password)) {
                    $message = "❌ Password is required";
                } else {

                    $originalName = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", basename($file["name"]));
                    $uploadPath   = $uploadDir . uniqid() . "_" . $originalName;

                    if (move_uploaded_file($file["tmp_name"], $uploadPath)) {

                        $protectedFileName = "protected_" . time() . ".pdf";
                        $protectedPath     = $protectedDir . $protectedFileName;

                        $input  = escapeshellarg($uploadPath);
                        $output = escapeshellarg($protectedPath);
                        $pwd    = escapeshellarg($password);

                        // qpdf encryption command (256-bit)
                        $cmd = "$qpdfBinary --encrypt $pwd $pwd 256 -- $input $output 2>&1";

                        exec($cmd, $outputLines, $returnCode);

                        if ($returnCode === 0 && file_exists($protectedPath)) {
                            $message = "✅ PDF Protected Successfully!";
                            $downloadLink = $protectedPath;
                            unlink($uploadPath);
                        } else {
                            $message = "❌ Failed to protect PDF";
                            unlink($uploadPath);
                        }
                    }
                }

            } else {
                $message = "❌ Only PDF files allowed";
            }
        } else {
            $message = "❌ Upload failed";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>PDF Password Protector</title>
<style>
body {
    background: linear-gradient(135deg, #667eea, #764ba2);
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.card {
    background: #fff;
    padding: 35px;
    border-radius: 14px;
    width: 420px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
h2 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
input[type=file], input[type=text] {
    width: 100%;
    padding: 12px;
    margin-top: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
}
button {
    width: 100%;
    padding: 13px;
    margin-top: 20px;
    border: none;
    border-radius: 8px;
    background: #667eea;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {
    background: #5a67d8;
}
.message {
    margin-top: 18px;
    padding: 12px;
    border-radius: 8px;
    background: #f4f4f4;
    text-align: center;
    font-weight: bold;
}
a.download {
    display:block;
    margin-top:15px;
    text-align: center;
    padding: 12px;
    background: #28a745;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
}
a.download:hover {
    background: #218838;
}
</style>
</head>
<body>

<div class="card">
<h2>🔐 Protect Your PDF</h2>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="pdf" accept="application/pdf" required>
    <input type="text" name="password" placeholder="Enter Password" required>
    <button type="submit">Protect PDF</button>
</form>

<?php if(!empty($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if(!empty($downloadLink)): ?>
    <a class="download" href="<?= htmlspecialchars($downloadLink) ?>" download>
        ⬇ Download Protected PDF
    </a>
<?php endif; ?>

</div>

</body>
</html>