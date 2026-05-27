<?php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

$action = $_GET['action'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$current_user_id = $_SESSION['user_id'] ?? 0;

// Function to format post data
function formatPostData($row, $current_user_id) {
    $author_name = $row['first_name'] . ' ' . $row['last_name'];
    $time_ago = strtotime($row['created_at']);
    $diff = time() - $time_ago;
    if ($diff < 60) $time_str = "JUST NOW";
    elseif ($diff < 3600) $time_str = floor($diff / 60) . " MINS AGO";
    elseif ($diff < 86400) $time_str = floor($diff / 3600) . " HOURS AGO";
    else $time_str = date('d M Y', $time_ago);

    $tags_array = !empty($row['tags']) ? explode(',', $row['tags']) : [];

    return [
        'id' => $row['post_id'],
        'title' => $row['title'],
        'author' => $author_name,
        'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($author_name) . '&background=random&color=fff',
        'time' => $time_str,
        'content' => nl2br(htmlspecialchars($row['content'])),
        'image' => $row['image_url'],
        'poll_data' => $row['poll_data'],
        'tags' => array_map('trim', $tags_array),
        'likes' => (int)$row['total_likes'],
        'comments' => (int)$row['total_comments'],
        'liked' => $row['user_liked'] > 0
    ];
}

// 1. Fetch Feed / News
if ($action === 'get_feed' || $action === 'get_news') {
    $post_type = ($action === 'get_news') ? 'news' : 'post';
    
    $query = "
        SELECT p.*, u.first_name, u.last_name, 
               (SELECT COUNT(*) FROM community_likes WHERE post_id = p.post_id) as total_likes,
               (SELECT COUNT(*) FROM community_comments WHERE post_id = p.post_id) as total_comments,
               (SELECT COUNT(*) FROM community_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.post_type = ? AND p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_user_id, $post_type]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_data = [];
    foreach ($results as $row) {
        $formatted_data[] = formatPostData($row, $current_user_id);
    }
    
    echo json_encode(['status' => 'success', 'data' => $formatted_data]);
    exit;
}

// 2. Fetch Single Post with Comments
if ($action === 'get_single_post') {
    $post_id = $_GET['id'] ?? '';
    
    $query = "
        SELECT p.*, u.first_name, u.last_name, 
               (SELECT COUNT(*) FROM community_likes WHERE post_id = p.post_id) as total_likes,
               (SELECT COUNT(*) FROM community_comments WHERE post_id = p.post_id) as total_comments,
               (SELECT COUNT(*) FROM community_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.post_id = ? AND p.status = 'active'
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_user_id, $post_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $postData = formatPostData($row, $current_user_id);
        
        // Fetch Comments
        $commentQuery = "
            SELECT c.comment, c.created_at, u.first_name, u.last_name 
            FROM community_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at DESC
        ";
        $commentStmt = $pdo->prepare($commentQuery);
        $commentStmt->execute([$post_id]);
        $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $postData['all_comments'] = array_map(function($c) {
            $name = $c['first_name'] . ' ' . $c['last_name'];
            return [
                'author' => $name,
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random&color=fff',
                'comment' => nl2br(htmlspecialchars($c['comment'])), // htmlspecialchars protects from XSS, allows emojis
                'time' => date('d M, h:i A', strtotime($c['created_at']))
            ];
        }, $comments);

        echo json_encode(['status' => 'success', 'data' => [$postData]]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Post not found or pending approval']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid Action']);
?>