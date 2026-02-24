<?php
require(__DIR__ . '/fpdf/fpdf.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['images']) && !isset($_POST['image_order'])) {
        die("No images uploaded.");
    }

    // Handle the reordered images
    if (isset($_POST['image_order']) && !empty($_POST['image_order'])) {
        $uploadDir = __DIR__ . "/uploads/";
        $imageOrder = json_decode($_POST['image_order']);
        
        $pdf = new FPDF();
        $pdf->SetAutoPageBreak(false);

        foreach ($imageOrder as $fileName) {
            $targetPath = $uploadDir . $fileName;
            
            if (!file_exists($targetPath)) {
                continue;
            }

            // Get image dimensions
            list($width, $height) = getimagesize($targetPath);

            // Convert pixels to mm (approx)
            $widthMM  = $width * 0.264583;
            $heightMM = $height * 0.264583;

            // A4 size limit
            $maxWidth  = 210;
            $maxHeight = 297;

            // Scale image to fit A4
            $ratio = min($maxWidth / $widthMM, $maxHeight / $heightMM);
            $newWidth  = $widthMM * $ratio;
            $newHeight = $heightMM * $ratio;

            $pdf->AddPage();
            $pdf->Image($targetPath, 
                        ($maxWidth - $newWidth) / 2, 
                        ($maxHeight - $newHeight) / 2, 
                        $newWidth, 
                        $newHeight);
        }

        $outputFile = $uploadDir . "converted_" . time() . ".pdf";
        $pdf->Output('F', $outputFile);

        echo "PDF Created Successfully!<br>";
        echo "<a href='uploads/" . basename($outputFile) . "' target='_blank'>Download PDF</a>";
        echo "<br><br><a href=''>Convert More Images</a>";
        exit;
    }

    // Initial upload handling
    if (!isset($_FILES['images'])) {
        die("No images uploaded.");
    }

    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $maxFiles = 30;

    $fileCount = count($_FILES['images']['name']);

    if ($fileCount > $maxFiles) {
        die("You can upload maximum 30 images.");
    }

    $uploadedFiles = [];

    for ($i = 0; $i < $fileCount; $i++) {

        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        if (!in_array($_FILES['images']['type'][$i], $allowedTypes)) {
            continue;
        }

        $tmpName = $_FILES['images']['tmp_name'][$i];
        $fileName = time() . "_" . basename($_FILES['images']['name'][$i]);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $uploadedFiles[] = [
                'name' => $fileName,
                'original' => $_FILES['images']['name'][$i]
            ];
        }
    }

    if (empty($uploadedFiles)) {
        die("No valid images were uploaded.");
    }

    // Display preview and reorder interface
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Arrange Images</title>
        <style>
            * {
                box-sizing: border-box;
            }
            body {
                font-family: Arial, sans-serif;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h2 {
                color: #333;
                margin-bottom: 20px;
            }
            .preview-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .preview-item {
                background: #f9f9f9;
                border: 2px solid #ddd;
                border-radius: 8px;
                padding: 10px;
                cursor: move;
                transition: all 0.3s;
                user-select: none;
            }
            .preview-item:hover {
                border-color: #007bff;
                box-shadow: 0 4px 12px rgba(0,123,255,0.2);
                transform: translateY(-2px);
            }
            .preview-item.dragging {
                opacity: 0.5;
                transform: scale(0.95);
            }
            .preview-item .image-container {
                height: 150px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                background: #fff;
                border-radius: 4px;
                margin-bottom: 8px;
            }
            .preview-item img {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
            }
            .preview-item .image-name {
                font-size: 12px;
                color: #666;
                text-align: center;
                word-break: break-all;
                padding: 5px;
            }
            .preview-item .order-number {
                background: #007bff;
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                margin-bottom: 5px;
            }
            .instructions {
                background: #e8f4fd;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                color: #0056b3;
            }
            button {
                background: #007bff;
                color: white;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.3s;
            }
            button:hover {
                background: #0056b3;
            }
            button.secondary {
                background: #6c757d;
                margin-left: 10px;
            }
            button.secondary:hover {
                background: #545b62;
            }
            .button-group {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Arrange Image Order</h2>
            
            <div class="instructions">
                <strong>Drag and drop images to arrange the order (top = first page in PDF)</strong>
            </div>

            <form id="orderForm" method="post">
                <input type="hidden" name="image_order" id="image_order">
                
                <div class="preview-grid" id="previewGrid">
                    <?php foreach ($uploadedFiles as $index => $file): ?>
                        <div class="preview-item" data-filename="<?php echo htmlspecialchars($file['name']); ?>">
                            <div class="order-number"><?php echo $index + 1; ?></div>
                            <div class="image-container">
                                <img src="uploads/<?php echo htmlspecialchars($file['name']); ?>" alt="Preview">
                            </div>
                            <div class="image-name"><?php echo htmlspecialchars($file['original']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="button-group">
                    <button type="submit">Create PDF with Current Order</button>
                    <button type="button" class="secondary" onclick="window.location.href=''">Upload More Images</button>
                </div>
            </form>
        </div>

        <script>
            const previewGrid = document.getElementById('previewGrid');
            const imageOrder = document.getElementById('image_order');
            let draggedItem = null;

            // Update order numbers
            function updateOrderNumbers() {
                const items = document.querySelectorAll('.preview-item');
                items.forEach((item, index) => {
                    const orderNumber = item.querySelector('.order-number');
                    orderNumber.textContent = index + 1;
                });
                updateOrderInput();
            }

            // Update hidden input with current order
            function updateOrderInput() {
                const items = document.querySelectorAll('.preview-item');
                const filenames = Array.from(items).map(item => item.dataset.filename);
                imageOrder.value = JSON.stringify(filenames);
            }

            // Drag and drop functionality
            previewGrid.addEventListener('dragstart', (e) => {
                if (e.target.closest('.preview-item')) {
                    draggedItem = e.target.closest('.preview-item');
                    draggedItem.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', draggedItem.innerHTML);
                }
            });

            previewGrid.addEventListener('dragend', (e) => {
                if (draggedItem) {
                    draggedItem.classList.remove('dragging');
                    draggedItem = null;
                }
            });

            previewGrid.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                const afterElement = getDragAfterElement(previewGrid, e.clientY);
                const draggable = document.querySelector('.dragging');
                
                if (afterElement == null) {
                    previewGrid.appendChild(draggedItem);
                } else {
                    previewGrid.insertBefore(draggedItem, afterElement);
                }
            });

            previewGrid.addEventListener('drop', (e) => {
                e.preventDefault();
                updateOrderNumbers();
            });

            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.preview-item:not(.dragging)')];

                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    
                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }

            // Make items draggable
            document.querySelectorAll('.preview-item').forEach(item => {
                item.setAttribute('draggable', 'true');
            });

            // Initialize order input
            updateOrderInput();

            // Form submit handler
            document.getElementById('orderForm').addEventListener('submit', function(e) {
                updateOrderInput();
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Image to PDF Converter</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        input[type="file"] {
            width: 100%;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 6px;
            background: #f9f9f9;
            cursor: pointer;
        }
        input[type="file"]:hover {
            border-color: #007bff;
            background: #e8f4fd;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #0056b3;
        }
        .info {
            color: #666;
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload Image(s) to Convert into PDF</h2>

        <form method="post" enctype="multipart/form-data">
            <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png" required>
            <br><br>
            <button type="submit">Upload & Arrange Images</button>
        </form>

        <div class="info">
            <p><strong>Instructions:</strong></p>
            <ul>
                <li>You can upload up to 30 images</li>
                <li>Supported formats: JPG, JPEG, PNG</li>
                <li>After upload, you'll be able to drag and drop to arrange the order</li>
                <li>Images will be automatically resized to fit A4 pages</li>
            </ul>
        </div>
    </div>
</body>
</html>