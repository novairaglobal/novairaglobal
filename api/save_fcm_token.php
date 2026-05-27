<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php'; 

header('Content-Type: application/json');

// Check jodi user logged in thake ebong token eshe thake
if (isset($_SESSION['user_id']) && isset($_POST['token'])) {
    $token = $_POST['token'];
    $userId = $_SESSION['user_id'];

    try {
        // User er database row-te token ta update kore din
        $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);
        
        echo json_encode(['status' => 'success', 'msg' => 'Token saved!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in or no token provided.']);
}
?>