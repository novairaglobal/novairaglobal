<?php
// api/db.php

// ==========================================
// SUPABASE POSTGRESQL DATABASE CONFIG
// ==========================================

$host     = 'db.vskgixwbsivstogqadxj.supabase.co';
$port     = '5432';
$dbname   = 'postgres';
$username = 'postgres';
$password = 'AgentOSX@2026';

try {

    // ==========================================
    // CONNECT TO SUPABASE POSTGRESQL
    // ==========================================
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password
    );

    // Error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // TIMEZONE
    // ==========================================
    date_default_timezone_set('Asia/Kolkata');

    // ==========================================
    // SESSION START
    // ==========================================
    if (session_status() === PHP_SESSION_NONE) {

        session_set_cookie_params([
            'domain' => '.novairaglobal.com',
            'path' => '/',
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // ==========================================
    // LAST ACTIVE TRACKING
    // ==========================================
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {

        $current_time = date('Y-m-d H:i:s');
        $logged_in_id = $_SESSION['user_id'];

        try {

            if ($_SESSION['user_type'] === 'admin') {

                $stmtLastActive = $pdo->prepare(
                    "UPDATE admin SET last_active = :time WHERE id = :id"
                );

            } else {

                $stmtLastActive = $pdo->prepare(
                    "UPDATE users SET last_active = :time WHERE id = :id"
                );
            }

            $stmtLastActive->execute([
                ':time' => $current_time,
                ':id'   => $logged_in_id
            ]);

        } catch (PDOException $e) {

            error_log("Last Active Update Failed: " . $e->getMessage());
        }
    }

    // ==========================================
    // MAINTENANCE MODE CHECK
    // ==========================================
    try {

        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM system_settings
            WHERE setting_name = 'maintenance_mode'
            LIMIT 1
        ");

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['setting_value'] == '1') {

            $is_admin = isset($_SESSION['user_type']) &&
                        $_SESSION['user_type'] === 'admin';

            $is_user_65 = isset($_SESSION['user_id']) &&
                          $_SESSION['user_id'] == 65;

            if (!$is_admin && !$is_user_65) {

                $currentPage = basename($_SERVER['PHP_SELF']);

                if (
                    $currentPage !== 'maintenance.php' &&
                    $currentPage !== 'maintenance.html'
                ) {

                    header("Location: /maintenance.php");
                    exit();
                }
            }
        }

    } catch (PDOException $e) {

        error_log("Maintenance Check Failed: " . $e->getMessage());
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

            $userId = $_SESSION['user_id'];

            $table = (
                isset($_SESSION['user_type']) &&
                $_SESSION['user_type'] === 'admin'
            ) ? 'admin' : 'users';

            try {

                $stmtToken = $pdo->prepare(
                    "UPDATE $table SET fcm_token = :token WHERE id = :id"
                );

                $stmtToken->execute([
                    ':token' => $fcm_token,
                    ':id'    => $userId
                ]);

                $_SESSION['saved_fcm_token'] = $fcm_token;

            } catch (PDOException $e) {

                error_log(
                    "FCM Token Silent Update Failed: " .
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

            $page_url = "https://" .
                ($_SERVER['HTTP_HOST'] ?? '') .
                ($_SERVER['REQUEST_URI'] ?? '');

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

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
                ':user_id'      => $user_id,
                ':error_type'   => $error_type,
                ':error_message'=> $error_message,
                ':page_url'     => $page_url,
                ':ip_address'   => $ip_address,
                ':created_at'   => $current_time
            ]);

        } catch (Exception $e) {

            error_log("System Error Log Failed");
        }
    }

} catch (PDOException $e) {

    error_log("Database Connection Failed: " . $e->getMessage());

    $currentPage = basename($_SERVER['PHP_SELF']);

    if (
        $currentPage !== 'maintenance.php' &&
        $currentPage !== 'maintenance.html'
    ) {

        header("Location: /maintenance.php");
        exit();
    }
}
?>