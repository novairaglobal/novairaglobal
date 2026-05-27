<?php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized. Please login.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ==========================================
// 1. CREATE POST
// ==========================================
if ($action === 'create_post') {
    try {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $poll_options = json_decode($_POST['poll_options'] ?? '[]', true);
        $post_type = 'post'; 
        
        if (empty($content) && empty($title)) {
            throw new Exception("Post content or title cannot be empty.");
        }

        // Prepare Poll Data
        $poll_json = null;
        if (!empty($poll_options) && count($poll_options) >= 2) {
            $pollData = [];
            foreach ($poll_options as $opt) {
                if (!empty(trim($opt))) {
                    $pollData[] = ['option' => trim($opt), 'votes' => 0];
                }
            }
            if(count($pollData) >= 2) {
                $poll_json = json_encode($pollData);
            }
        }

        $post_id = strtoupper(substr(md5(uniqid()), 0, 10));
        $now = date('Y-m-d H:i:s');

        // Insert as 'pending' for admin approval
        $stmt = $pdo->prepare("INSERT INTO community_posts (post_id, user_id, post_type, title, content, poll_data, tags, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([$post_id, $user_id, $post_type, $title, $content, $poll_json, $tags, $now]);

        echo json_encode(['status' => 'success', 'msg' => 'Post submitted for admin review!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 2. ADD COMMENT
// ==========================================
if ($action === 'add_comment') {
    $post_id = $_POST['post_id'] ?? '';
    $comment = trim($_POST['comment'] ?? '');
    
    if(!empty($post_id) && !empty($comment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO community_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$post_id, $user_id, $comment]);
            echo json_encode(['status' => 'success', 'msg' => 'Comment added!']);
        } catch(Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => 'Database error.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Comment cannot be empty.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
?>