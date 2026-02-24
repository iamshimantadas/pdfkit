# 📄 PHP PDF Toolkit

A simple PHP-based PDF toolkit built for Linux servers.  
This project allows users to merge, split, compress, extract text, and protect PDF files using open-source Linux tools.

---

## 🚀 Features

### 1. merge.php
- Merge 2 or more PDF files
- Drag & drop reordering before merge

### 2. extract-page.php
- Extract any single page from a PDF file

### 3. pdf-to-text.php
- Extract full text from a PDF

### 4. compress-pdf.php
- Compress PDF file  
- Example: 10MB → 2MB / 500KB (depends on content)

### 5. protect-pdf.php
- Protect PDF with password (256-bit encryption using qpdf)

### 6. image-to-pdf.php
- We can convert multiple images to pdf file. Also able to arrange them. 

### 7. word-to-pdf.php
- We can convert word file(doc/docx) to pdf file. Underhood it will use libreoffice. Make sure libreoffice installed.

---

# 🐧 Required Linux Tools

Install the following tools on Ubuntu/Debian:

```bash
sudo apt update
sudo apt install ghostscript
sudo apt install poppler-utils
sudo apt install qpdf -y
sudo apt install libreoffice
```

---

# ⚙️ Server Configuration (Important)

Edit your PHP configuration file:

```bash
sudo nano /etc/php/YOUR_PHP_VERSION/apache2/php.ini
```

Update these values:

```
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 300
memory_limit = 256M
```

After editing:

```bash
sudo systemctl restart apache2
```

---

# 📁 Required Folder Structure

Create required folders:

```bash
mkdir merge split uploads text_output compressed protected
```

Set proper permissions:

```bash
sudo chmod 755 merge/ split/ uploads/ text_output/ compressed/ protected/
```

---

# 🛠 Technologies Used

- PHP
- Ghostscript
- Poppler-utils (pdfunite, pdftotext, etc.)
- qpdf

---

# 🔐 Security Note

- Always validate uploaded files
- Restrict file types to PDF only
- Use `escapeshellarg()` when executing shell commands
- In production, avoid using `777` permissions — use proper ownership instead

---

# 📌 Recommended Production Setup

Instead of 777 permissions:

```bash
sudo chown -R www-data:www-data merge split uploads text_output compressed protected
sudo chmod -R 755 merge split uploads text_output compressed protected
```

---

# 💡 Future Improvements

- Add automatic file cleanup (cron job)
- Add progress bars for large uploads
- Add drag & drop UI improvements
- Add combined compress + protect tool
- Add Docker support

---

# 👨‍💻 Author

Built with ❤️ using open-source Linux tools.

---

⭐ If you like this project, give it a star on GitHub!