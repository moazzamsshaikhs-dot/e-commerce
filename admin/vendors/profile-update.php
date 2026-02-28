<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if (!$action) {
    $_SESSION['error'] = 'Invalid action';
    header('Location: profile.php');
    exit();
}

$db = getDB();

switch($action) {
    case 'update_profile':
        // Update basic info
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $vendor_bio = trim($_POST['vendor_bio'] ?? '');
        
        if (empty($full_name)) {
            $_SESSION['error'] = 'Full name is required';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users SET 
                        full_name = ?, phone = ?, address = ?, city = ?, 
                        country = ?, postal_code = ?, vendor_bio = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $phone, $address, $city, $country, $postal_code, $vendor_bio, $vendor_id]);
                $_SESSION['success'] = 'Profile updated successfully';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
            }
        }
        break;
        
    case 'update_social':
        // Update social links
        $social_facebook = trim($_POST['social_facebook'] ?? '');
        $social_twitter = trim($_POST['social_twitter'] ?? '');
        $social_instagram = trim($_POST['social_instagram'] ?? '');
        $social_linkedin = trim($_POST['social_linkedin'] ?? '');
        
        try {
            $stmt = $db->prepare("
                UPDATE users SET 
                    social_facebook = ?, social_twitter = ?, 
                    social_instagram = ?, social_linkedin = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$social_facebook, $social_twitter, $social_instagram, $social_linkedin, $vendor_id]);
            $_SESSION['success'] = 'Social links updated';
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
        }
        break;
        
    case 'update_avatar':
        // Upload avatar
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (in_array($file_type, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/profiles/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'vendor_' . $vendor_id . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    // Get old file and delete
                    $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
                    $stmt->execute([$vendor_id]);
                    $old = $stmt->fetchColumn();
                    
                    if ($old && $old != 'default.png' && file_exists($upload_dir . $old)) {
                        unlink($upload_dir . $old);
                    }
                    
                    // Update database
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$filename, $vendor_id]);
                    
                    $_SESSION['profile_pic'] = $filename;
                    $_SESSION['success'] = 'Profile picture updated';
                } else {
                    $_SESSION['error'] = 'Failed to upload file';
                }
            } else {
                $_SESSION['error'] = 'Invalid file type or too large';
            }
        } else {
            $_SESSION['error'] = 'No file uploaded';
        }
        break;
        
    case 'upload_document':
        // Upload document
        $doc_type = $_POST['document_type'] ?? '';
        $doc_number = $_POST['document_number'] ?? '';
        $expiry = $_POST['expiry_date'] ?? null;
        
        if (!$doc_type) {
            $_SESSION['error'] = 'Document type required';
            break;
        }
        
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document_file'];
            $allowed = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (in_array($file_type, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'doc_' . $vendor_id . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $stmt = $db->prepare("
                        INSERT INTO vendor_documents (vendor_id, document_type, document_number, document_file, expiry_date, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$vendor_id, $doc_type, $doc_number, $filename, $expiry]);
                    
                    $_SESSION['success'] = 'Document uploaded for verification';
                } else {
                    $_SESSION['error'] = 'Failed to upload file';
                }
            } else {
                $_SESSION['error'] = 'Invalid file type or too large';
            }
        } else {
            $_SESSION['error'] = 'No file uploaded';
        }
        break;
        
    default:
        $_SESSION['error'] = 'Invalid action';
}

redirect('profile.php');
exit();