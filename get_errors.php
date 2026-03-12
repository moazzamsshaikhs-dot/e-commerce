<?php
print_r(error_get_last());
echo file_get_contents('C:/xampp/apache/logs/error.log', false, null, max(0, filesize('C:/xampp/apache/logs/error.log') - 2000));
