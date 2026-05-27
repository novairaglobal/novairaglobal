<?php
// ==========================================
// CROSS-SUBDOMAIN SESSION CONFIGURATION
// Required to keep users logged in when moving 
// from accounts.novairasolution.com to dashboard.novairasolution.com
// ==========================================
session_set_cookie_params([
    'domain' => '.novairasolution.com', // The leading dot allows subdomains
    'path' => '/',
    'secure' => true,      // Requires HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set Timezone to Kolkata
date_default_timezone_set('Asia/Kolkata');

// Include Database Connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php'; 

// ==========================================
// SUBDOMAIN REDIRECT LOGIC
// ==========================================
function getRedirectUrl($type) {
    switch(strtolower($type)) {
        case 'admin': return 'https://admin.novairasolution.com/';
        case 'bulker': return 'https://bulker.novairasolution.com/';
        case 'commercial': return 'https://commercial.novairasolution.com/';
        case 'provider': return 'https://provider.novairasolution.com/';
        default: return 'https://dashboard.novairasolution.com/'; // individual
    }
}

// ==========================================
// ALREADY LOGGED IN CHECK
// ==========================================
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    header("Location: " . getRedirectUrl($_SESSION['user_type']));
    exit;
}

// ==========================================
// URL PARAMETER LOGIC (Login vs Registration)
// ==========================================
$mode = 'default'; // Shows login by default, allows switching
$preselected_type = '';
$assign_bulker_id = '';
$assigned_bulker_name = '';
$bulker_error = '';

if (array_key_exists('login', $_GET)) {
    $mode = 'login_only'; // Locks to login view
} elseif (array_key_exists('registration', $_GET)) {
    $mode = 'register_only'; // Locks to registration view
    
    // Safely check if a specific role was passed (e.g., ?registration=bulker)
    $val = isset($_GET['registration']) && is_string($_GET['registration']) ? trim(strtolower($_GET['registration'])) : '';
    
    if ($val === 'individual' || $val === 'indivisual') {
        $preselected_type = 'individual';
    } elseif (in_array($val, ['bulker', 'commercial', 'provider'])) {
        $preselected_type = $val;
    }
    
    // Check for assigned bulker ID (e.g., &assign_bulker=1)
    if (isset($_GET['assign_bulker']) && is_numeric($_GET['assign_bulker'])) {
        $check_id = (int)$_GET['assign_bulker'];
        
        // Verify against database that this is an active bulker
        $stmtCheckB = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND user_type = 'bulker' AND status = 'active'");
        $stmtCheckB->execute([$check_id]);
        $bData = $stmtCheckB->fetch(PDO::FETCH_ASSOC);
        
        if ($bData) {
            $assign_bulker_id = $check_id;
            $assigned_bulker_name = trim($bData['first_name'] . ' ' . $bData['last_name']);
        } else {
            $bulker_error = "The referral link is invalid or the bulker account does not exist.";
        }
    }
}

// ==========================================
// AJAX API HANDLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $auth_mode = $_POST['auth_mode'] ?? 'default';

    try {
        // 1. Google Auth Check
        if ($action === 'google_auth') {
            $jwt = $_POST['credential'] ?? '';
            if (empty($jwt)) throw new Exception("Invalid Google credentials.");

            $parts = explode('.', $jwt);
            
            // FIX: URL-safe decoding for Android JWT Tokens
            $base64 = isset($parts[1]) ? str_replace(['-', '_'], ['+', '/'], $parts[1]) : '';
            $payload = json_decode(base64_decode($base64), true);
            
            $email = $payload['email'] ?? '';
            $fname = $payload['given_name'] ?? '';
            $lname = $payload['family_name'] ?? '';

            if (empty($email)) throw new Exception("Could not retrieve email from Google.");

            // --- STEP 1: CHECK ADMIN TABLE FIRST ---
            $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
            $stmtAdmin->execute([$email]);
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                if (strtolower($admin['status']) !== 'active') {
                    echo json_encode(['status' => 'error', 'msg' => "Account restricted. Status: " . ucfirst($admin['status'])]);
                    exit;
                }

                $stmtUpdateAdmin = $pdo->prepare("UPDATE admin SET last_login = ? WHERE id = ?");
                $stmtUpdateAdmin->execute([date('Y-m-d H:i:s'), $admin['id']]);

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_email'] = $admin['email'];
                $_SESSION['first_name'] = $admin['first_name'] ?? 'Admin';
                $_SESSION['user_type'] = 'admin'; 
                
                echo json_encode([
                    'status' => 'success', 
                    'msg' => 'Admin logged in successfully!',
                    'redirect' => getRedirectUrl('admin')
                ]);
                exit;
            }

            // --- STEP 2: IF NOT ADMIN, CHECK USERS TABLE ---
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (strtolower($user['status']) !== 'active') {
                    echo json_encode(['status' => 'error', 'msg' => "Account restricted. Status: " . ucfirst($user['status'])]);
                    exit;
                }

                $stmtUpdateUser = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                $stmtUpdateUser->execute([date('Y-m-d H:i:s'), $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                echo json_encode([
                    'status' => 'success', 
                    'msg' => 'Logged in successfully!',
                    'redirect' => getRedirectUrl($user['user_type'])
                ]);
            } else {
                if ($auth_mode === 'login_only') {
                    echo json_encode([
                        'status' => 'error', 
                        'msg' => 'Account not found. Please register an account first.'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'new_user', 
                        'data' => ['email' => $email, 'first_name' => $fname, 'last_name' => $lname]
                    ]);
                }
            }
            exit;
        }

        // 2. Google Complete Registration
        if ($action === 'google_complete') {
            $fname = $_POST['first_name'] ?? '';
            $lname = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $type  = strtolower($_POST['user_type'] ?? 'individual');
            $pass_raw = $_POST['password'] ?? '';
            $posted_bulker_id = $_POST['assign_bulker'] ?? '';
            
            if (empty($pass_raw)) throw new Exception("Password is required.");
            $pass = password_hash($pass_raw, PASSWORD_DEFAULT);

            $stmtAdminCheck = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
            $stmtAdminCheck->execute([$email]);
            if ($stmtAdminCheck->fetch()) {
                echo json_encode(['status' => 'error', 'msg' => 'This email belongs to an Admin. Please login directly.']);
                exit;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'msg' => 'Email already registered!']);
                exit;
            }

            $allowed_types = ['individual', 'bulker', 'commercial', 'provider'];
            if (!in_array($type, $allowed_types)) {
                $type = 'individual';
            }
            
            $formattedType = strtoupper($type);
            $isUnique = false;
            $customUserId = '';

            while (!$isUnique) {
                $randomChars = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8));
                $customUserId = "NOVAIRA/" . $formattedType . "/" . $randomChars;
                
                $checkIdStmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
                $checkIdStmt->execute([$customUserId]);
                if (!$checkIdStmt->fetch()) {
                    $isUnique = true;
                }
            }

            $valid_bulker_id = null;
            if ($type === 'individual') {
                if (!empty($posted_bulker_id) && is_numeric($posted_bulker_id)) {
                    $stmtBulkerCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'bulker' AND status = 'active'");
                    $stmtBulkerCheck->execute([(int)$posted_bulker_id]);
                    if ($bulker_row = $stmtBulkerCheck->fetch(PDO::FETCH_ASSOC)) {
                        $valid_bulker_id = $bulker_row['id'];
                    }
                }
                if (empty($valid_bulker_id)) {
                    $valid_bulker_id = 10;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO users (user_id, first_name, last_name, email, phone, password, user_type, auth_provider, status, bulkers, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, 'google', 'active', ?, ?)");
            $stmt->execute([$customUserId, $fname, $lname, $email, $phone, $pass, $type, $valid_bulker_id, date('Y-m-d H:i:s')]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_email'] = $email;
            $_SESSION['first_name'] = $fname;
            $_SESSION['user_type'] = $type;

            echo json_encode([
                'status' => 'success', 
                'msg' => 'Registration completed successfully!',
                'redirect' => getRedirectUrl($type)
            ]);
            exit;
        }

        // 3. Local Standard Login
        if ($action === 'login') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
            $stmtAdmin->execute([$email]);
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                if (strtolower($admin['status']) !== 'active') {
                    echo json_encode(['status' => 'error', 'msg' => "Admin account is " . ucfirst($admin['status']) . "."]);
                    exit;
                }

                $stmtUpdateAdmin = $pdo->prepare("UPDATE admin SET last_login = ? WHERE id = ?");
                $stmtUpdateAdmin->execute([date('Y-m-d H:i:s'), $admin['id']]);

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_email'] = $admin['email'];
                $_SESSION['first_name'] = $admin['first_name'] ?? 'Admin';
                $_SESSION['user_type'] = 'admin';

                echo json_encode([
                    'status' => 'success', 
                    'msg' => 'Admin Access Granted!',
                    'redirect' => getRedirectUrl('admin')
                ]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (strtolower($user['status']) !== 'active') {
                    echo json_encode(['status' => 'error', 'msg' => "Account restricted. Status: " . ucfirst($user['status'])]);
                    exit;
                }

                $stmtUpdateUser = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                $stmtUpdateUser->execute([date('Y-m-d H:i:s'), $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['user_type'] = $user['user_type'];

                echo json_encode([
                    'status' => 'success', 
                    'msg' => 'Welcome back, ' . htmlspecialchars($user['first_name']) . '!',
                    'redirect' => getRedirectUrl($user['user_type'])
                ]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Invalid email or password.']);
            }
            exit;
        }

    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'msg' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title>Authentication Portal | Novaira Solution</title>
    <meta name="description" content="Secure login and registration portal for Novaira Solution. Access your corporate, provider, bulker, or individual dashboard to manage campaigns and track growth.">
    <meta name="keywords" content="Novaira Solution, Secure Login, Corporate Dashboard, User Authentication">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://accounts.novairasolution.com/">
    
    <meta property="og:title" content="Authentication | Novaira Solution">
    <meta property="og:description" content="Securely log in to manage your Novaira ecosystem tools.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://accounts.novairasolution.com/">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://accounts.google.com">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" as="style">

    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media', // Automatic dark/light mode detection
            theme: { 
                extend: { 
                    colors: { 
                        primary: '#4F46E5', 
                        secondary: '#1E293B', 
                        darkbg: '#0F172A',
                        darkpanel: '#1E293B',
                        accent: '#F3F4F6' 
                    } 
                } 
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
        }
        
        /* New Smooth Cinematic Animations */
        @keyframes fadeSlideUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .animate-enter {
            animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Responsive Mobile Full Screen Panel */
        .panel-container { position: relative; width: 100%; min-height: 100%; }
        @media (min-width: 1024px) {
            .panel-container { min-height: 550px; }
        }
        
        /* GPU Accelerated Form Panels */
        .form-panel { 
            width: 100%; 
            transition: opacity 0.4s ease-out, transform 0.4s ease-out; 
            position: absolute; top: 0; left: 0; 
            will-change: transform, opacity; 
            backface-visibility: hidden;
        }
        .form-hidden { opacity: 0; transform: translateY(20px) scale(0.98); pointer-events: none; z-index: -1; }
        .form-visible { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; z-index: 10; position: relative; }
        
        /* SweetAlert Customizations */
        .cute-swal-popup { width: auto !important; min-width: 280px; border-radius: 20px !important; padding: 1.25rem !important; box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important; }
        .cute-swal-title { font-size: 1.1rem !important; font-weight: 700 !important; color: #1E293B !important; margin-bottom: 0.5rem !important; }
        .cute-swal-icon { transform: scale(0.65) !important; margin: 0 auto !important; }
        .cute-toast { border-radius: 50px !important; padding: 10px 20px !important; box-shadow: 0 4px 15px rgba(0,0,0,0.08) !important; font-weight: 600 !important; font-size: 0.9rem !important; }

        /* Dark Mode Fixes for SweetAlert */
        @media (prefers-color-scheme: dark) {
            .cute-swal-popup { background-color: #1E293B !important; }
            .cute-swal-title { color: #F1F5F9 !important; }
            .cute-toast { background-color: #334155 !important; color: #F8FAFC !important; }
            .swal2-html-container { color: #CBD5E1 !important; }
        }
    </style>
</head>
<body class="bg-white dark:bg-darkbg text-gray-800 dark:text-gray-100 antialiased h-screen h-[100dvh] overflow-hidden flex flex-col lg:flex-row transition-colors duration-300">

    <main class="w-full lg:w-1/2 h-[100dvh] flex flex-col justify-center items-center p-6 lg:p-12 overflow-y-auto relative z-10 animate-enter">
        <div class="w-full max-w-[100%] sm:max-w-md panel-container flex flex-col justify-center lg:justify-start">
            
            <div class="lg:hidden flex items-center gap-2 mb-10 justify-center">
                <div class="w-10 h-10 bg-primary text-white rounded-lg flex items-center justify-center font-bold text-xl shadow-lg">N</div>
                <span class="font-bold text-2xl tracking-tight text-secondary dark:text-white">Novaira</span>
            </div>

            <div id="panel-login" class="form-panel <?= ($mode !== 'register_only') ? 'form-visible' : 'form-hidden' ?>">
                <div class="mb-8 text-center lg:text-left">
                    <h1 class="text-3xl font-extrabold text-secondary dark:text-white mb-2">Welcome Back</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Log in to access your growth dashboard.</p>
                </div>

                <div id="g_id_onload"
                     data-client_id="754171446556-2pjkhjfbpfbl6ucpg23j4s5di4n08323.apps.googleusercontent.com"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleCallback"
                     data-auto_prompt="true"> 
                </div>
                
                <div id="google-btn-container" class="mb-6 flex justify-center w-full max-w-[350px] mx-auto lg:mx-0"></div>
                
                <button type="button" class="app-google-auth-btn w-full flex items-center justify-center gap-3 bg-white dark:bg-slate-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-bold text-lg py-3 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-all shadow-sm mb-6" style="display: none;" onclick="AndroidBridge.startGoogleLogin()">
                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>
                    Continue with Google
                </button>

                <div class="relative flex items-center justify-center mb-6">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
                    <span class="relative px-4 bg-white dark:bg-darkbg text-sm text-gray-400 dark:text-gray-500 font-medium uppercase tracking-wider">Or email login</span>
                </div>

                <form id="form-login" onsubmit="submitForm(event, 'login')" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="email" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary/50 outline-none transition-all bg-gray-50 dark:bg-darkpanel focus:bg-white dark:focus:bg-slate-800 text-gray-900 dark:text-white" placeholder="example@gmail.com">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="login-password" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Password</label>
                            <a href="/auth/forgot_password" class="text-sm font-semibold text-primary hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <input type="password" name="password" id="login-password" required autocomplete="current-password" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary/50 outline-none transition-all bg-gray-50 dark:bg-darkpanel focus:bg-white dark:focus:bg-slate-800 text-gray-900 dark:text-white" placeholder="••••••••">
                            <button type="button" aria-label="Toggle Password Visibility" onclick="togglePassword('login-password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-eye" id="login-password-icon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white font-bold text-lg py-3.5 rounded-xl transition-all shadow-lg mt-4">Sign In</button>
                </form>

                <?php if ($mode !== 'login_only'): ?>
                <p class="text-center text-gray-500 dark:text-gray-400 font-medium mt-8">
                    Don't have an account? 
                    <button type="button" onclick="switchPanel('panel-register-init')" class="text-primary dark:text-indigo-400 font-bold hover:underline">Create one</button>
                </p>
                <?php endif; ?>
            </div>

            <div id="panel-register-init" class="form-panel <?= ($mode === 'register_only') ? 'form-visible' : 'form-hidden' ?> text-center">
                <div class="mb-8">
                    <div class="w-16 h-16 bg-indigo-50 dark:bg-indigo-900/30 text-primary dark:text-indigo-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-4 border border-indigo-100 dark:border-indigo-800">
                        <i class="fab fa-google"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-secondary dark:text-white mb-2">Secure Registration</h2>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">For security purposes, we require all new users to verify their identity via Google.</p>
                    
                    <?php if(!empty($preselected_type)): ?>
                        <div class="mt-4 inline-block bg-primary/10 border border-primary/20 text-primary dark:text-indigo-300 font-bold px-4 py-2 rounded-full text-sm">
                            <i class="fas fa-check-circle me-1"></i> Registering as: <?= ucfirst($preselected_type) ?>
                            <?php if(!empty($assign_bulker_id) && !empty($assigned_bulker_name)): ?>
                                <br><i class="fas fa-link mt-1 me-1"></i> Referred by: <span class="text-indigo-700 dark:text-indigo-400"><?= htmlspecialchars($assigned_bulker_name) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="google-btn-container-reg" class="mb-8 flex justify-center w-full max-w-[350px] mx-auto"></div>
                
                <button type="button" class="app-google-auth-btn w-full flex items-center justify-center gap-3 bg-white dark:bg-slate-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-bold text-lg py-3 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-all shadow-sm mb-8" style="display: none;" onclick="AndroidBridge.startGoogleLogin()">
                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>
                    Continue with Google
                </button>

                <?php if ($mode !== 'register_only'): ?>
                <p class="text-center text-gray-500 dark:text-gray-400 font-medium">
                    Already have an account? 
                    <button type="button" onclick="switchPanel('panel-login')" class="text-primary dark:text-indigo-400 font-bold hover:underline">Sign in</button>
                </p>
                <?php endif; ?>
            </div>

            <div id="panel-google-type" class="form-panel form-hidden">
                <div class="mb-6 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold text-secondary dark:text-white mb-2">Select Account Type</h2>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">How will you be using Novaira?</p>
                </div>
                <div class="space-y-3" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                    <button type="button" onclick="selectGoogleRole('individual')" class="w-full p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary dark:hover:border-primary hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition flex items-center gap-4 text-left group bg-white dark:bg-darkpanel">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center text-xl group-hover:bg-primary group-hover:text-white transition"><i class="fas fa-user dark:text-gray-300"></i></div>
                        <div><h4 class="font-bold text-secondary dark:text-white text-sm">Individual</h4><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">For personal projects & indie devs.</p></div>
                    </button>
                    <button type="button" onclick="selectGoogleRole('bulker')" class="w-full p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary dark:hover:border-primary hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition flex items-center gap-4 text-left group bg-white dark:bg-darkpanel">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center text-xl group-hover:bg-primary group-hover:text-white transition"><i class="fas fa-layer-group dark:text-gray-300"></i></div>
                        <div><h4 class="font-bold text-secondary dark:text-white text-sm">Bulker</h4><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">For agencies managing bulk distribution.</p></div>
                    </button>
                    <button type="button" onclick="selectGoogleRole('commercial')" class="w-full p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary dark:hover:border-primary hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition flex items-center gap-4 text-left group bg-white dark:bg-darkpanel">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center text-xl group-hover:bg-primary group-hover:text-white transition"><i class="fas fa-building dark:text-gray-300"></i></div>
                        <div><h4 class="font-bold text-secondary dark:text-white text-sm">Commercial</h4><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">For corporate and enterprise brands.</p></div>
                    </button>
                    <button type="button" onclick="selectGoogleRole('provider')" class="w-full p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary dark:hover:border-primary hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition flex items-center gap-4 text-left group bg-white dark:bg-darkpanel">
                        <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center text-xl group-hover:bg-primary group-hover:text-white transition"><i class="fas fa-truck-fast dark:text-gray-300"></i></div>
                        <div><h4 class="font-bold text-secondary dark:text-white text-sm">Provider</h4><p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">For app providers and logistics managers.</p></div>
                    </button>
                </div>
            </div>

            <div id="panel-google-complete" class="form-panel form-hidden">
                <div class="mb-6 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold text-secondary dark:text-white mb-2">Complete Setup</h2>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Please provide a password and phone number.</p>
                </div>
                
                <form id="form-google-complete" onsubmit="submitForm(event, 'google_complete')" class="space-y-4">
                    <input type="hidden" name="user_type" id="g-user-type" value="<?= htmlspecialchars($preselected_type) ?>">
                    <input type="hidden" name="assign_bulker" value="<?= htmlspecialchars($assign_bulker_id) ?>">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                            <input type="text" name="first_name" id="g-fname" readonly class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-slate-800/50 text-gray-500 dark:text-gray-400 outline-none cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="g-lname" readonly class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-slate-800/50 text-gray-500 dark:text-gray-400 outline-none cursor-not-allowed">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" id="g-email" readonly class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-slate-800/50 text-gray-500 dark:text-gray-400 outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                        <input type="tel" name="phone" required class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary/50 outline-none bg-gray-50 dark:bg-darkpanel focus:bg-white dark:focus:bg-slate-800 text-gray-900 dark:text-white" placeholder="e.g. +91 9876543210">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Set Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="g-password" required autocomplete="new-password" class="w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-primary/50 outline-none transition-all bg-gray-50 dark:bg-darkpanel focus:bg-white dark:focus:bg-slate-800 text-gray-900 dark:text-white" placeholder="Choose a secure password">
                            <button type="button" aria-label="Toggle Password Visibility" onclick="togglePassword('g-password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-eye" id="g-password-icon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-6">
                        <?php if (empty($preselected_type)): ?>
                            <button type="button" onclick="switchPanel('panel-google-type')" class="w-1/3 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 font-bold py-3.5 rounded-xl transition-all">Back</button>
                            <button type="submit" class="w-2/3 bg-primary hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg">Finish</button>
                        <?php else: ?>
                            <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg">Finish Registration</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <aside class="hidden lg:flex w-1/2 bg-secondary dark:bg-slate-900 relative flex-col justify-between p-12 overflow-hidden border-l border-gray-800/10">
        <div class="absolute top-[-10%] right-[-10%] w-96 h-96 bg-primary rounded-full mix-blend-screen filter blur-[100px] opacity-60 animate-pulse" style="animation-duration: 4s;"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-screen filter blur-[100px] opacity-40 animate-pulse" style="animation-duration: 6s;"></div>
        
        <div class="relative z-10 flex items-center gap-3 animate-enter" style="animation-delay: 0.1s;">
            <div class="w-12 h-12 bg-white text-primary rounded-xl flex items-center justify-center font-black text-2xl shadow-lg">N</div>
            <span class="font-bold text-3xl tracking-tight text-white">Novaira Solution</span>
        </div>

        <div class="relative z-10 max-w-lg mt-10 animate-enter" style="animation-delay: 0.2s;">
            <span class="inline-block py-1 px-3 rounded-full bg-white/10 text-indigo-200 font-semibold text-sm mb-6 border border-white/20 uppercase tracking-wider backdrop-blur-sm">Corporate Dashboard</span>
            <h2 class="text-5xl font-extrabold text-white leading-tight mb-6">Scale Your Reach.<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-300 to-purple-300">Measure Your Growth.</span></h2>
            <p class="text-lg text-gray-300 mb-10 leading-relaxed">Join industry leaders using Novaira's ecosystem to manage campaigns, track ROI, and dominate global markets.</p>
        </div>

        <div class="relative z-10 flex justify-between text-sm text-gray-400 font-medium animate-enter" style="animation-delay: 0.3s;">
            <p>&copy; <?php echo date("Y"); ?> Novaira Business Solution Pvt. Ltd.</p>
        </div>
    </aside>

    <script>
        // Google GSI Load optimization and theme detection
        window.onload = function () {
            const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = isDarkMode ? 'filled_black' : 'outline';
            
            if (typeof google !== 'undefined' && google.accounts) {
                google.accounts.id.initialize({
                    client_id: "754171446556-2pjkhjfbpfbl6ucpg23j4s5di4n08323.apps.googleusercontent.com",
                    callback: handleGoogleCallback,
                    context: "signin",
                    ux_mode: "popup"
                });

                const btnContainer = document.getElementById('google-btn-container');
                if(btnContainer) {
                    google.accounts.id.renderButton(btnContainer, { type: "standard", theme: theme, size: "large", text: "continue_with", shape: "rectangular", width: "350" });
                }

                const regBtnContainer = document.getElementById('google-btn-container-reg');
                if(regBtnContainer) {
                    google.accounts.id.renderButton(regBtnContainer, { type: "standard", theme: theme, size: "large", text: "continue_with", shape: "rectangular", width: "350" });
                }
            }
        };

        // UI Initialization
        document.addEventListener('DOMContentLoaded', () => {
            const checkSwal = setInterval(() => {
                if (typeof Swal !== 'undefined') {
                    clearInterval(checkSwal);
                    initAlerts();
                }
            }, 100);
        });

        let CuteToast, CuteAlert;

        function initAlerts() {
            CuteToast = Swal.mixin({ 
                toast: true, 
                position: 'top-end', 
                showConfirmButton: false, 
                timer: 3000, 
                timerProgressBar: true,
                customClass: { popup: 'cute-toast' }
            });

            CuteAlert = (icon, title, text) => {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    customClass: {
                        popup: 'cute-swal-popup',
                        title: 'cute-swal-title',
                        icon: 'cute-swal-icon',
                        confirmButton: 'bg-primary text-white rounded-xl px-5 py-2 w-full font-bold transition-all'
                    },
                    buttonsStyling: false
                });
            };

            <?php if (!empty($bulker_error)): ?>
                CuteAlert('error', 'Invalid Referral Link', '<?= addslashes($bulker_error) ?>');
            <?php endif; ?>
        }

        const authMode = '<?php echo $mode; ?>';
        const preselectedType = '<?php echo $preselected_type; ?>';

        function switchPanel(panelId) {
            const panels = ['panel-login', 'panel-register-init', 'panel-google-type', 'panel-google-complete'];
            panels.forEach(id => {
                const el = document.getElementById(id);
                if(id === panelId) {
                    el.classList.remove('form-hidden'); el.classList.add('form-visible');
                } else {
                    el.classList.remove('form-visible'); el.classList.add('form-hidden');
                }
            });
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            if (input.type === 'password') {
                input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function handleGoogleCallback(response) {
            const formData = new FormData();
            formData.append('action', 'google_auth');
            formData.append('credential', response.credential);
            formData.append('auth_mode', authMode);

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    if(CuteToast) CuteToast.fire({ icon: 'success', title: data.msg });
                    setTimeout(() => window.location.href = data.redirect, 1000); 
                } else if(data.status === 'new_user') {
                    document.getElementById('g-email').value = data.data.email;
                    document.getElementById('g-fname').value = data.data.first_name;
                    document.getElementById('g-lname').value = data.data.last_name;

                    if (preselectedType !== '') {
                        selectGoogleRole(preselectedType);
                    } else {
                        switchPanel('panel-google-type');
                    }
                } else {
                    if(CuteAlert) CuteAlert('error', 'Access Denied', data.msg);
                }
            }).catch(err => {
                if(CuteToast) CuteToast.fire({ icon: 'error', title: 'Google authentication failed!' });
            });
        }

        function selectGoogleRole(type) {
            document.getElementById('g-user-type').value = type;
            switchPanel('panel-google-complete');
        }

        function submitForm(event, actionStr) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', actionStr);

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
            btn.disabled = true;

            fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;

                if(data.status === 'success') {
                    if(CuteToast) CuteToast.fire({ icon: 'success', title: data.msg });
                    setTimeout(() => window.location.href = data.redirect, 500); 
                } else {
                    if(CuteAlert) CuteAlert('error', 'Authentication Failed', data.msg);
                }
            }).catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                if(CuteToast) CuteToast.fire({ icon: 'error', title: 'Server communication error!' });
            });
        }

        // Native Android App Intercept
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof AndroidBridge !== 'undefined') {
                document.getElementById('google-btn-container').style.display = 'none';
                document.getElementById('google-btn-container-reg').style.display = 'none';
                document.querySelectorAll('.app-google-auth-btn').forEach(el => el.style.display = 'flex');
            }
        });
    </script>
</body>
</html>