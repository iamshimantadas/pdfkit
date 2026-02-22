merge.php => responsible for merge 2 or more pdf files. user can able to do pdf ordering by dragging one above another.

extract-page.php => It helps to extract any single page from the whole pdf file.

pdf-to-text.php => It helps to extract whole text from the pdf.

compress-pdf.php => Helps to compress pdf file, ex: 10MB -> 2MB/500K.B

linux tools needed:
sudo apt install ghostscript
sudo apt-get install poppler-utils

server configuration need to do:
sudo nano /etc/php/YOUR_PHP_VERSION/apache2/php.ini
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 300
memory_limit = 256M