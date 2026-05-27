<?php
// api/db.php

// ==========================================
// DEBUG MODE
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// TIMEZONE
// ==========================================
date_default_timezone_set('Asia/Kolkata');

// ==========================================
// SUPABASE DATABASE CONFIG
// ==========================================
$host     = 'aws-1-ap-south-1.pooler.supabase.com';
$port     = '5432';
$dbname   = 'postgres';
$username = 'postgres.vskgixwbsivstogqadxj';
$password = 'AgentOSX@2026';

try {

    // ==========================================
    // DATABASE CONNECTION
    // ==========================================
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password
    );

    // ==========================================
    // PDO SETTINGS
    // ==========================================
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ==========================================
    // SESSION START
    // ==========================================
    if (session_status() === PHP_SESSION_NONE) {

        session_set_cookie_params([
            'domain'   => '.novairaglobal.com',
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // ==========================================
    // DATABASE TEST
    // ==========================================
    $testQuery = $pdo->query("SELECT NOW()");
    $serverTime = $testQuery->fetchColumn();

    echo "
    <div style='
        background:#111;
        color:#00ff88;
        padding:20px;
        margin:20px;
        border-radius:10px;
        font-family:Arial;
    '>
        <h2>✅ SUPABASE DATABASE CONNECTED</h2>

        <p><b>Host:</b> $host</p>

        <p><b>Database:</b> $dbname</p>

        <p><b>PostgreSQL Time:</b> $serverTime</p>
    </div>
    ";

    // ==========================================
    // LAST ACTIVE TRACKING
    // ==========================================
    if (
        isset($_SESSION['user_id']) &&
        isset($_SESSION['user_type'])
    ) {

        try {

            $current_time = date('Y-m-d H:i:s');
            $logged_in_id = $_SESSION['user_id'];

            if ($_SESSION['user_type'] === 'admin') {

                $stmt = $pdo->prepare(
                    "UPDATE admin
                    SET last_active = :time
                    WHERE id = :id"
                );

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE users
                    SET last_active = :time
                    WHERE id = :id"
                );
            }

            $stmt->execute([
                ':time' => $current_time,
                ':id'   => $logged_in_id
            ]);

        } catch (PDOException $e) {

            error_log(
                'Last Active Update Failed: ' .
                $e->getMessage()
            );
        }
    }

    // ==========================================
    // FCM TOKEN SAVE
    // ==========================================
    if (
        isset($_SESSION['user_id']) &&
        !empty($_COOKIE['fcm_token'])
    ) {

        $fcm_token = $_COOKIE['fcm_token'];

        if (
            !isset($_SESSION['saved_fcm_token']) ||
            $_SESSION['saved_fcm_token'] !== $fcm_token
        ) {

            try {

                $userId = $_SESSION['user_id'];

                $table = (
                    isset($_SESSION['user_type']) &&
                    $_SESSION['user_type'] === 'admin'
                ) ? 'admin' : 'users';

                $stmtToken = $pdo->prepare(
                    "UPDATE $table
                    SET fcm_token = :token
                    WHERE id = :id"
                );

                $stmtToken->execute([
                    ':token' => $fcm_token,
                    ':id'    => $userId
                ]);

                $_SESSION['saved_fcm_token'] = $fcm_token;

            } catch (PDOException $e) {

                error_log(
                    'FCM Token Update Failed: ' .
                    $e->getMessage()
                );
            }
        }
    }

    // ==========================================
    // SYSTEM ERROR LOGGER
    // ==========================================
    function logSystemError(
        $pdo,
        $error_type,
        $error_message,
        $user_id = null
    ) {

        try {

            $current_time = date('Y-m-d H:i:s');

            $page_url =
                "https://" .
                ($_SERVER['HTTP_HOST'] ?? '') .
                ($_SERVER['REQUEST_URI'] ?? '');

            $ip_address =
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

            $stmt = $pdo->prepare("
                INSERT INTO system_error_logs
                (
                    user_id,
                    error_type,
                    error_message,
                    page_url,
                    ip_address,
                    status,
                    created_at
                )
                VALUES
                (
                    :user_id,
                    :error_type,
                    :error_message,
                    :page_url,
                    :ip_address,
                    'unresolved',
                    :created_at
                )
            ");

            $stmt->execute([
                ':user_id'       => $user_id,
                ':error_type'    => $error_type,
                ':error_message' => $error_message,
                ':page_url'      => $page_url,
                ':ip_address'    => $ip_address,
                ':created_at'    => $current_time
            ]);

        } catch (Exception $e) {

            error_log(
                'System Error Log Failed'
            );
        }
    }

} catch (PDOException $e) {

    // ==========================================
    // CONNECTION FAILED
    // ==========================================

    echo "
    <div style='
        background:#111;
        color:red;
        padding:20px;
        margin:20px;
        border-radius:10px;
        font-family:Arial;
    '>
        <h2>❌ DATABASE CONNECTION FAILED</h2>

        <p><b>Error:</b></p>

        <pre style='white-space:pre-wrap;color:#ff5555;'>
" . $e->getMessage() . "
        </pre>
    </div>
    ";

    exit();
}
?>