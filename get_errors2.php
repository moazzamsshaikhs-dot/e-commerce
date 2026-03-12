<?php echo file_get_contents('C:/xampp/apache/logs/error.log', false, null, max(0, filesize('C:/xampp/apache/logs/error.log') - 8000));
