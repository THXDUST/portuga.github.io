<?php
/**
 * Resume Submission API
 * Handles public resume/CV submissions with file uploads
 */

// Don't set Content-Type header too early, as it might interfere with multipart/form-data parsing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    http_response_code(200);
    exit(0);
}

// Define helper functions first
function sendError($message, $code = 400) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function sendSuccess($data, $message = 'Success') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

// Wrap in try-catch to ensure JSON responses
try {
    require_once __DIR__ . '/../config/database.php';
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/resumes/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            sendError('Failed to create upload directory', 500);
        }
    }
    
    // Verify directory is writable
    if (!is_writable($uploadDir)) {
        sendError('Upload directory is not writable', 500);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }
    
    // Debug logging
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

    // Validate required fields
    $requiredFields = ['full_name', 'email', 'phone', 'desired_position', 'agreed_terms'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            sendError("Field '$field' is required");
        }
    }

    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email address');
    }

    // Check if terms were agreed
    if ($_POST['agreed_terms'] !== 'true' && $_POST['agreed_terms'] !== '1') {
        sendError('You must agree to the terms');
    }

    // Handle file upload
    $resumeFilePath = null;
    $resumeFileName = null;
    $resumeFileSize = null;
    $resumeFileType = null;

    if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resume_file'];
        
        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $maxSize) {
            sendError('File size exceeds 5MB limit');
        }

        // Validate file type
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $allowedExtensions = ['pdf', 'doc', 'docx'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($mimeType, $allowedTypes) || !in_array($fileExtension, $allowedExtensions)) {
            sendError('Invalid file type. Only PDF, DOC, and DOCX files are allowed');
        }

        // Generate unique filename
        $uniqueName = uniqid('resume_', true) . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $uniqueName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $resumeFilePath = 'uploads/resumes/' . $uniqueName;
            $resumeFileName = $file['name'];
            $resumeFileSize = $file['size'];
            $resumeFileType = $mimeType;
        } else {
            sendError('Failed to upload file');
        }
    } else {
        // File upload is required
        if (!isset($_FILES['resume_file'])) {
            sendError('Resume file is required');
        }
        
        // Handle upload errors
        $uploadError = $_FILES['resume_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        switch ($uploadError) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                sendError('File size exceeds limit');
                break;
            case UPLOAD_ERR_PARTIAL:
                sendError('File was only partially uploaded');
                break;
            case UPLOAD_ERR_NO_FILE:
                sendError('No file was uploaded');
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                sendError('Missing temporary folder');
                break;
            case UPLOAD_ERR_CANT_WRITE:
                sendError('Failed to write file to disk');
                break;
            default:
                sendError('File upload failed');
                break;
        }
    }

    // Save to database
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO resumes 
        (full_name, email, phone, desired_position, resume_file_path, cover_letter, status)
        VALUES (?, ?, ?, ?, ?, ?, 'em_analise')
    ");
    
    $coverLetter = $_POST['cover_letter'] ?? null;
    
    if ($stmt->execute([
        $_POST['full_name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['desired_position'],
        $resumeFilePath,
        $coverLetter
    ])) {
        $resumeId = $conn->lastInsertId();
        
        sendSuccess([
            'id' => $resumeId,
            'full_name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'desired_position' => $_POST['desired_position'],
            'resume_file' => [
                'name' => $resumeFileName,
                'size' => $resumeFileSize,
                'type' => $resumeFileType,
                'path' => $resumeFilePath
            ],
            'cover_letter' => $coverLetter
        ], 'Resume submitted successfully');
    } else {
        // Delete uploaded file if database insert fails
        if ($resumeFilePath && file_exists($targetPath)) {
            unlink($targetPath);
        }
        sendError('Failed to submit resume to database');
    }
    
} catch (PDOException $e) {
    // Delete uploaded file if database error occurs
    if (isset($targetPath) && file_exists($targetPath)) {
        unlink($targetPath);
    }
    error_log('Database error in resumes.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    error_log('Error in resumes.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
    exit;
}

