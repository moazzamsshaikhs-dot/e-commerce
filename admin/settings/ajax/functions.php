<?php
/**
 * Get content from a file inside a tar.gz archive using phar:// wrapper
 * 
 * @param string $archive_path Path to the tar.gz file
 * @param string $file_name Name of the file to extract
 * @return string|null File content or null if not found
 */
function getContent($archive_path, $file_name) {
    if (!file_exists($archive_path)) {
        return null;
    }
    
    try {
        // Create phar:// wrapper path
        $full_path = 'phar://' . realpath($archive_path) . '/' . $file_name;
        
        // Check if file exists
        if (file_exists($full_path)) {
            return file_get_contents($full_path);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("getContent error: " . $e->getMessage());
        return null;
    }
}
?>