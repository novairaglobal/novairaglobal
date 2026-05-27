<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

// ==========================================
// UNIFIED POST HANDLER (Login only)
// Note: Create Post, Comment & Like are handled in api/post_action.php
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // GOOGLE LOGIN
    if ($action === 'google_login') {
        $id_token = $_POST['credential'] ?? '';
        if (empty($id_token)) { echo json_encode(['status' => 'error', 'msg' => 'No token provided']); exit; }

        $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
        $response = @file_get_contents($url);
        if (!$response) { echo json_encode(['status' => 'error', 'msg' => 'Failed to verify token']); exit; }
        
        $payload = json_decode($response, true);
        if (isset($payload['email'])) {
            $email = $payload['email'];
            $first_name = $payload['given_name'] ?? 'User';
            $last_name = $payload['family_name'] ?? '';
            $now = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, user_type FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stmtUpd = $pdo->prepare("UPDATE users SET last_login = ?, last_active = ? WHERE id = ?");
                $stmtUpd->execute([$now, $now, $user['id']]);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                echo json_encode(['status' => 'success', 'msg' => 'Logged in successfully']);
            } else {
                $unique_hash = strtoupper(substr(md5(uniqid()), 0, 8));
                $generated_user_id = "NOVAIRA/INDIVIDUAL/" . $unique_hash;
                $stmtIns = $pdo->prepare("INSERT INTO users (user_id, first_name, last_name, email, user_type, status, auth_provider, created_at, last_login, last_active) VALUES (?, ?, ?, ?, 'INDIVIDUAL', 'ACTIVE', 'GOOGLE', ?, ?, ?)");
                $stmtIns->execute([$generated_user_id, $first_name, $last_name, $email, $now, $now, $now]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                $_SESSION['user_type'] = 'INDIVIDUAL';
                echo json_encode(['status' => 'success', 'msg' => 'Account created and logged in']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid Google Token Payload']);
        }
        exit;
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: feed");
    exit();
}

// Strictly distinguish between logged in users and guests
$is_logged_in = isset($_SESSION['user_id']) ? 'true' : 'false';
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '') : "";
$user_email = $_SESSION['email'] ?? ""; 

// ==========================================
// DYNAMIC ADVANCED SEO LOGIC
// ==========================================
$current_host = $_SERVER['HTTP_HOST'];
$base_url = "https://" . $current_host . "/community/feed";
$page_title = "Community | Novaira";
$page_description = "Join the Novaira community. Discuss features, review apps, and stay updated with the latest announcements.";
$page_url = $base_url;
$page_image = "https://" . $current_host . "/assets/images/logo.png"; // Default fallback logo
$is_single_view = false;
$single_post_id = null;
$single_post_type = null;

if (isset($_GET['post'])) {
    $is_single_view = true;
    $single_post_id = htmlspecialchars($_GET['post'], ENT_QUOTES, 'UTF-8');
    $single_post_type = 'post';
    $page_url = $base_url . "?post=" . $single_post_id;
    
    // Removed 'image' from SELECT to prevent PDOException crash
    $stmt = $pdo->prepare("SELECT title, content FROM community_posts WHERE post_id = ? AND status='active'");
    $stmt->execute([$single_post_id]);
    
    if ($row = $stmt->fetch()) {
        $raw_title = !empty($row['title']) ? $row['title'] : "Community Discussion";
        $page_title = htmlspecialchars($raw_title, ENT_QUOTES, 'UTF-8') . " | Novaira Community";
        $raw_desc = strip_tags(html_entity_decode($row['content'], ENT_QUOTES, 'UTF-8'));
        $page_description = htmlspecialchars(mb_substr($raw_desc, 0, 155), ENT_QUOTES, 'UTF-8') . "...";
    }
} elseif (isset($_GET['news'])) {
    $is_single_view = true;
    $single_post_id = htmlspecialchars($_GET['news'], ENT_QUOTES, 'UTF-8');
    $single_post_type = 'news';
    $page_url = $base_url . "?news=" . $single_post_id;
    
    // Removed 'image' from SELECT to prevent PDOException crash
    $stmt = $pdo->prepare("SELECT title, content FROM community_posts WHERE post_id = ? AND status='active'");
    $stmt->execute([$single_post_id]);
    
    if ($row = $stmt->fetch()) {
        $page_title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . " | Novaira Official News";
        $raw_desc = strip_tags(html_entity_decode($row['content'], ENT_QUOTES, 'UTF-8'));
        $page_description = htmlspecialchars(mb_substr($raw_desc, 0, 155), ENT_QUOTES, 'UTF-8') . "...";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <title><?= $page_title ?></title>
    <meta name="title" content="<?= $page_title ?>">
    <meta name="description" content="<?= $page_description ?>">
    <link rel="canonical" href="<?= $page_url ?>">

    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= $page_description ?>">
    <meta property="og:image" content="<?= $page_image ?>">
    <meta property="og:site_name" content="Novaira">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= $page_url ?>">
    <meta property="twitter:title" content="<?= $page_title ?>">
    <meta property="twitter:description" content="<?= $page_description ?>">
    <meta property="twitter:image" content="<?= $page_image ?>">
    
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    
    <script src="https://accounts.google.com/gsi/client" async defer></script> 
    <script>
        const savedTheme = localStorage.getItem('novaira_theme') || 'system';
        if (savedTheme === 'dark' || (savedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; transition: background-color 0.3s, color 0.3s; overscroll-behavior-y: none; }
        body { background-color: #f4f4f5; color: #1a1a1a; }
        .border-nothing { border-color: #e5e7eb; }
        .bg-nothing-card { background-color: #ffffff; }
        .dark body { background-color: #050505; color: #e5e5e5; }
        .dark .border-nothing { border-color: #1a1a1a; }
        .dark .bg-nothing-card { background-color: #0a0a0a; }
        .font-dot { font-family: 'DotGothic16', sans-serif; letter-spacing: 0.05em; text-transform: uppercase; }
        .text-nothing-red { color: #f02d2d; }
        .hover-red:hover { color: #f02d2d; border-color: #f02d2d; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .nav-item { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item:active { transform: scale(0.9); }
        .skeleton { background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: skeletonLoading 1.5s infinite; }
        .dark .skeleton { background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%); background-size: 200% 100%; }
        @keyframes skeletonLoading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body x-data="communityApp()" class="overflow-x-hidden no-scrollbar flex flex-col min-h-screen" x-init="initApp()" @touchmove="handleTouchMove" @touchend="handleTouchEnd">

    <nav class="sticky top-0 z-40 bg-white/80 dark:bg-[#050505]/80 backdrop-blur-xl border-b border-nothing border-b-[1px] transition-colors">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between relative">
            
            <div class="flex items-center gap-3 cursor-pointer" @click="goBackToFeed(); activeTab='feed';">
                <div class="w-8 h-8 bg-black text-white dark:bg-white dark:text-black flex items-center justify-center font-bold text-xl rounded-sm transition-colors">N</div>
                <span class="font-dot text-xl font-bold tracking-widest text-black dark:text-white transition-colors">NOVAIRA.</span>
            </div>
            
          

            <div class="flex items-center gap-3 sm:gap-4">
                <button @click="openSearchModal()" class="text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white transition">
                    <i class="fas fa-search text-lg sm:text-base"></i>
                </button>
                
                <template x-if="isLoggedIn">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="relative">
                            <button @click="showNotifications = !showNotifications" @click.outside="showNotifications = false" class="relative text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white transition">
                                <i class="fas fa-bell text-lg sm:text-base"></i>
                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-[#f02d2d] rounded-full"></span>
                            </button>
                            
                            <div x-show="showNotifications" x-transition x-cloak class="absolute right-0 mt-4 w-72 bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl py-3 z-50 overflow-hidden">
                                <div class="px-4 pb-2 border-b border-nothing flex justify-between items-center">
                                    <h3 class="font-bold text-black dark:text-white">Notifications</h3>
                                    <span class="text-xs text-blue-500 hover:underline cursor-pointer font-medium">Mark all read</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto custom-scrollbar p-2">
                                    <div class="p-3 bg-blue-50 dark:bg-blue-500/10 rounded-xl mb-2 flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-500 flex items-center justify-center shrink-0"><i class="fas fa-bullhorn text-sm"></i></div>
                                        <div>
                                            <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-tight font-medium">Welcome to the new Novaira Community!</p>
                                            <p class="text-[10px] text-zinc-500 mt-1 font-dot">Just now</p>
                                        </div>
                                    </div>
                                    <div class="p-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 rounded-xl transition flex items-start gap-3 cursor-pointer">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-500 flex items-center justify-center shrink-0"><i class="fas fa-check text-sm"></i></div>
                                        <div>
                                            <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-tight">Your post was approved by the admin.</p>
                                            <p class="text-[10px] text-zinc-500 mt-1 font-dot">2 hours ago</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <img @click="showProfileSidebar = true" :src="userAvatar" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 cursor-pointer shadow-sm lg:hidden block">
                        
                        <div class="relative hidden lg:block">
                            <img @click="showDesktopProfile = !showDesktopProfile" @click.outside="showDesktopProfile = false" :src="userAvatar" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 cursor-pointer shadow-sm">
                            
                            <div x-show="showDesktopProfile" x-transition x-cloak class="absolute right-0 mt-4 w-52 bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl py-2 z-50">
                                <div class="px-4 py-3 border-b border-nothing mb-2">
                                    <p class="text-sm font-bold text-black dark:text-white leading-tight" x-text="userName"></p>
                                    <p class="text-[10px] text-zinc-500 font-dot mt-1 truncate" x-text="userEmail"></p>
                                </div>
                                <a href="#" @click.prevent="goBackToFeed(); activeTab='feed'; showDesktopProfile=false;" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 text-zinc-700 dark:text-zinc-300 transition-colors">
                                    <i class="fas fa-house w-4 text-center"></i> Home
                                </a>
                                <a href="#" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 text-zinc-700 dark:text-zinc-300 transition-colors">
                                    <i class="fas fa-layer-group w-4 text-center"></i> My Posts
                                </a>
                                <a href="?logout=1" class="w-full text-left px-4 py-2 text-sm font-bold hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center gap-3 text-red-600 dark:text-red-400 transition-colors border-t border-nothing mt-2 pt-3">
                                    <i class="fas fa-sign-out-alt w-4 text-center"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
                
                <template x-if="!isLoggedIn">
                    <button @click="showLogin = true" class="font-dot text-sm border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 px-4 py-1.5 rounded-full hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all">Sign In</button>
                </template>

                <div class="relative hidden sm:block">
                    <button @click="showDesktopSettings = !showDesktopSettings" @click.outside="showDesktopSettings = false" class="text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white transition">
                        <i class="fas fa-cog"></i>
                    </button>
                    
                    <div x-show="showDesktopSettings" x-transition x-cloak class="absolute right-0 mt-4 w-40 bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xl py-2 z-50">
                        <p class="px-4 py-1 text-[10px] font-dot text-zinc-400 dark:text-zinc-500 uppercase tracking-widest mb-1">Theme</p>
                        <button @click="setTheme('light')" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 transition-colors" :class="theme === 'light' ? 'text-[#f02d2d]' : 'text-zinc-700 dark:text-zinc-300'"><i class="fas fa-sun w-4 text-center"></i> Light</button>
                        <button @click="setTheme('dark')" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 transition-colors" :class="theme === 'dark' ? 'text-[#f02d2d]' : 'text-zinc-700 dark:text-zinc-300'"><i class="fas fa-moon w-4 text-center"></i> Dark</button>
                        <button @click="setTheme('system')" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 transition-colors" :class="theme === 'system' ? 'text-[#f02d2d]' : 'text-zinc-700 dark:text-zinc-300'"><i class="fas fa-desktop w-4 text-center"></i> Auto</button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex-grow max-w-6xl mx-auto w-full px-4 py-6 grid grid-cols-1 lg:grid-cols-4 gap-8 relative pb-28 lg:pb-6">
        
        <aside class="hidden lg:block col-span-1 sticky top-24 h-fit space-y-6">
            <div class="bg-nothing-card border border-nothing rounded-2xl p-4 shadow-sm">
                <nav class="space-y-1">
                    <button @click="switchTab('feed')" :class="activeTab === 'feed' ? 'bg-zinc-100 dark:bg-zinc-900 text-black dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 hover:text-black dark:hover:text-white'" class="w-full text-left font-dot flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                        <i class="fas fa-layer-group text-lg w-5 text-center"></i> Community
                    </button>
                    <button @click="switchTab('news')" :class="activeTab === 'news' ? 'bg-red-50 dark:bg-[#f02d2d]/10 text-[#f02d2d]' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 hover:text-black dark:hover:text-white'" class="w-full text-left font-dot flex items-center gap-4 px-4 py-3 rounded-xl transition-all">
                        <i class="fas fa-bullhorn text-lg w-5 text-center"></i> Official News
                    </button>
                </nav>
            </div>

            <div x-show="!isSingleView" class="bg-nothing-card border border-nothing rounded-2xl p-5 shadow-sm" x-transition x-cloak>
                <h3 class="font-dot text-zinc-500 text-sm mb-4 flex items-center gap-2"><i class="fas fa-hashtag text-[#f02d2d]"></i> Trending Topics</h3>
                <div class="flex flex-wrap gap-2">
                    <template x-if="isLoading && posts.length === 0">
                        <div class="flex gap-2 flex-wrap w-full">
                            <div class="skeleton h-6 w-20 rounded-full"></div>
                            <div class="skeleton h-6 w-24 rounded-full"></div>
                            <div class="skeleton h-6 w-16 rounded-full"></div>
                        </div>
                    </template>
                    <template x-if="!isLoading || posts.length > 0">
                        <template x-for="tag in trendingTags" :key="tag.name">
                            <a href="#" class="border border-nothing bg-zinc-50 dark:bg-zinc-900/50 px-3 py-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white hover:border-zinc-400 dark:hover:border-zinc-500 rounded-full transition-all flex items-center gap-1.5 shadow-sm" :class="tag.isHot ? 'border-[#f02d2d]/30 dark:border-[#f02d2d]/30 text-black dark:text-white bg-red-50 dark:bg-red-500/5' : ''">
                                <span x-show="tag.isHot" class="w-1.5 h-1.5 rounded-full bg-[#f02d2d] animate-pulse"></span>
                                <span x-text="tag.name"></span>
                            </a>
                        </template>
                    </template>
                </div>
            </div>
        </aside>

        <main class="col-span-1 lg:col-span-3">
            
            <div class="flex lg:hidden gap-6 border-b border-nothing mb-6 font-dot text-lg overflow-x-auto no-scrollbar px-2" x-show="!isSingleView">
                <button @click="switchTab('feed')" :class="activeTab === 'feed' ? 'text-black dark:text-white border-b-2 border-black dark:border-white pb-2' : 'text-zinc-500 pb-2'">Community</button>
                <button @click="switchTab('news')" :class="activeTab === 'news' ? 'text-nothing-red border-b-2 border-[#f02d2d] pb-2' : 'text-zinc-500 pb-2'">News</button>
            </div>

            <template x-if="isLoggedIn && !isSingleView && activeTab === 'feed' && !isLoading">
                <div class="bg-nothing-card border border-nothing rounded-3xl p-5 mb-6 shadow-sm hidden lg:block transition-all duration-300">
                    <div x-show="!isCreatingPost" class="flex gap-4 items-center cursor-text" @click="isCreatingPost = true; setTimeout(() => $refs.postContentInput.focus(), 100)">
                        <img :src="userAvatar" class="w-10 h-10 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex-1 bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-full px-4 py-2.5 text-sm text-zinc-500">What's on your mind? Share with the community...</div>
                    </div>
                    <form x-show="isCreatingPost" x-collapse @submit.prevent="submitPost" class="flex flex-col gap-4">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center gap-3">
                                <img :src="userAvatar" class="w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                <span class="font-bold text-sm text-black dark:text-white" x-text="userName"></span>
                            </div>
                            <button type="button" @click="isCreatingPost = false; resetForm()" class="text-zinc-400 hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="text" x-model="newPost.title" placeholder="Give your post a catchy title (Optional)..." class="w-full bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 text-black dark:text-white rounded-xl px-4 py-2.5 text-sm font-bold placeholder-zinc-400 dark:placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all shadow-inner">
                        <textarea x-ref="postContentInput" x-model="newPost.content" rows="4" placeholder="Type your thoughts here... *" class="w-full bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 text-black dark:text-white rounded-xl px-4 py-3 text-sm font-medium placeholder-zinc-400 dark:placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all resize-none custom-scrollbar shadow-inner"></textarea>
                        
                        <div x-show="newPost.showPoll" x-collapse class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/50 rounded-xl p-4 shadow-sm">
                            <h4 class="font-dot text-xs text-blue-600 dark:text-blue-400 mb-3 flex items-center gap-2"><i class="fas fa-list-ol"></i> Poll Options</h4>
                            <div class="space-y-2">
                                <template x-for="(opt, index) in newPost.pollOptions" :key="index">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-[10px] font-bold text-zinc-500" x-text="index + 1"></div>
                                        <input type="text" x-model="newPost.pollOptions[index]" :placeholder="'Option ' + (index + 1)" class="flex-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-black dark:text-white rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                                        <button type="button" x-show="newPost.pollOptions.length > 2" @click="newPost.pollOptions.splice(index, 1)" class="text-zinc-400 hover:text-red-500 w-6 h-6 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" x-show="newPost.pollOptions.length < 5" @click="newPost.pollOptions.push('')" class="mt-3 text-[10px] font-bold text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white transition flex items-center gap-1.5"><i class="fas fa-plus"></i> Add Option</button>
                        </div>

                        <div>
                            <div class="bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl px-3 py-2 flex flex-wrap gap-2 items-center shadow-inner focus-within:ring-2 focus-within:ring-blue-500/50 transition-all relative z-20">
                                <template x-for="(tag, index) in newPost.tags" :key="index">
                                    <div class="flex items-center gap-1 bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-md text-[10px] font-bold border border-blue-200 dark:border-blue-500/30">
                                        <span x-text="'#' + tag"></span>
                                        <button type="button" @click="newPost.tags.splice(index, 1)" class="hover:text-red-500 transition-colors"><i class="fas fa-times text-[8px]"></i></button>
                                    </div>
                                </template>
                                <input type="text" x-ref="tagInputRef" x-model="newPost.tagInput" @focus="showDesktopTagSuggestions = true" @keydown.enter.prevent="addDesktopTag(newPost.tagInput)" @keydown.comma.prevent="addDesktopTag(newPost.tagInput)" placeholder="Add tags..." class="bg-transparent border-none outline-none text-xs text-black dark:text-white flex-1 min-w-[100px]" :disabled="newPost.tags.length >= 5">
                                <div x-show="showDesktopTagSuggestions && filteredDesktopTags.length > 0 && newPost.tags.length < 5" @click.outside="showDesktopTagSuggestions = false" x-transition x-cloak class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl z-50 max-h-48 overflow-y-auto custom-scrollbar p-2">
                                    <template x-for="sTag in filteredDesktopTags" :key="sTag">
                                        <button type="button" @click="addDesktopTag(sTag)" class="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            <span x-text="'#' + sTag"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center pt-3 border-t border-nothing">
                            <div class="flex gap-4 text-zinc-500">
                                <button type="button" class="hover:text-blue-500 transition tooltip cursor-not-allowed opacity-50" title="Image Upload (Coming Soon)"><i class="fas fa-image text-lg"></i></button>
                                <button type="button" @click="newPost.showPoll = !newPost.showPoll" class="hover:text-emerald-500 transition tooltip" :class="newPost.showPoll ? 'text-emerald-500' : ''" title="Add Poll"><i class="fas fa-poll text-lg"></i></button>
                            </div>
                            <button type="submit" :disabled="isSubmitting || !newPost.content.trim()" class="font-dot text-xs bg-black text-white dark:bg-white dark:text-black px-6 py-2 rounded-full hover:bg-zinc-800 dark:hover:bg-zinc-200 transition shadow-md hover:shadow-lg transform disabled:opacity-50 flex items-center gap-2">
                                <span x-show="!isSubmitting">POST</span>
                                <span x-show="isSubmitting"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>

            <template x-if="isLoading">
                <div class="space-y-5">
                    <div class="bg-nothing-card border border-nothing rounded-3xl p-5 sm:p-6 shadow-sm">
                        <div class="flex gap-3 mb-4">
                            <div class="skeleton w-10 h-10 rounded-full"></div>
                            <div class="space-y-2 flex-1 pt-1">
                                <div class="skeleton h-3 w-32 rounded"></div>
                                <div class="skeleton h-2 w-20 rounded"></div>
                            </div>
                        </div>
                        <div class="space-y-2 mb-4">
                            <div class="skeleton h-3 w-full rounded"></div>
                            <div class="skeleton h-3 w-5/6 rounded"></div>
                        </div>
                        <div class="skeleton h-48 w-full rounded-xl mb-4"></div>
                    </div>
                </div>
            </template>

            <div class="space-y-5" x-show="!isLoading" x-transition.opacity.duration.300ms x-cloak>
                
                <template x-if="isSingleView && singlePost">
                    <div class="bg-nothing-card border border-nothing rounded-3xl p-6 sm:p-8 shadow-sm mb-5">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-nothing">
                            <button @click="goBackToFeed()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"><i class="fas fa-arrow-left"></i></button>
                            <span class="font-dot font-bold text-zinc-500 cursor-pointer" @click="goBackToFeed()">Back to Feed</span>
                        </div>
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex items-center gap-4">
                                <img :src="singlePost.avatar" class="w-12 h-12 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                <div>
                                    <div x-show="singlePost.type === 'news'" class="inline-flex items-center gap-1.5 bg-[#f02d2d]/10 text-nothing-red px-2 py-0.5 rounded text-[10px] font-dot font-bold mb-1 border border-[#f02d2d]/20"><i class="fas fa-bolt"></i> OFFICIAL</div>
                                    <h4 class="text-base font-bold text-black dark:text-white flex items-center gap-1.5" x-text="singlePost.author"></h4>
                                    <p class="font-dot text-xs text-zinc-500 mt-0.5 flex items-center gap-1"><i class="fas fa-clock text-[10px]"></i> <span x-text="singlePost.time"></span></p>
                                </div>
                            </div>
                        </div>
                        <h2 x-show="singlePost.title" class="text-2xl font-black text-black dark:text-white mb-4 leading-tight" x-text="singlePost.title"></h2>
                        
                        <div class="text-zinc-700 dark:text-zinc-300 text-base leading-relaxed mb-6" x-html="decodeHTML(singlePost.content)"></div>
                        
                        <template x-if="singlePost.image">
                            <div class="mb-6 rounded-2xl overflow-hidden border border-nothing">
                                <img :src="singlePost.image" class="w-full h-auto object-cover">
                            </div>
                        </template>

                        <template x-if="singlePost.poll_data && singlePost.poll_data.length > 0">
                            <div class="bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 mb-6">
                                <h4 class="font-dot text-sm font-bold mb-4 text-black dark:text-white"><i class="fas fa-poll text-blue-500 mr-2"></i> Community Poll</h4>
                                <div class="space-y-3">
                                    <template x-for="(opt, idx) in singlePost.poll_data" :key="idx">
                                        <button class="w-full relative bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-3 rounded-xl text-left overflow-hidden hover:border-blue-500 transition-colors group">
                                            <div class="relative z-10 flex justify-between text-sm font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-black dark:group-hover:text-white">
                                                <span x-text="opt.option"></span>
                                                <span class="font-bold text-blue-600 dark:text-blue-400" x-text="opt.votes + ' Votes'"></span>
                                            </div>
                                            <div class="absolute inset-y-0 left-0 bg-blue-50 dark:bg-blue-900/20" :style="`width: ${(opt.votes / Math.max(1, singlePost.total_votes)) * 100}%`"></div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        
                        <div class="flex flex-wrap gap-2 mb-6 border-b border-nothing pb-6">
                            <template x-for="tag in singlePost.tags">
                                <span x-show="tag" class="text-xs font-dot text-blue-600 bg-blue-50 border border-blue-100 dark:text-blue-400 dark:bg-blue-500/10 dark:border-blue-500/20 px-3 py-1.5 rounded-md" x-text="tag"></span>
                            </template>
                        </div>

                        <div class="flex items-center gap-8 text-zinc-500 text-sm font-bold mb-6">
                            <button class="flex items-center gap-2 hover-red transition nav-item" @click.stop="toggleLike(singlePost.id, true)">
                                <i class="fa-heart text-lg" :class="singlePost.liked ? 'fa-solid text-nothing-red' : 'fa-regular'"></i> 
                                <span x-text="singlePost.likes" :class="singlePost.liked ? 'text-nothing-red' : ''"></span>
                            </button>
                            <span class="flex items-center gap-2">
                                <i class="fa-regular fa-comment text-lg"></i> <span x-text="singlePost.comments"></span> Comments
                            </span>
                            <button @click="copyToClipboard(window.location.href)" class="flex items-center gap-2 hover:text-black dark:hover:text-white transition ml-auto nav-item">
                                <i class="fa-solid fa-link text-base"></i> <span class="hidden sm:inline">Copy Link</span>
                            </button>
                        </div>

                        <div class="bg-slate-50 dark:bg-[#0a0a0a] rounded-2xl p-4 sm:p-5 border border-nothing mb-8">
                            <h4 class="font-bold text-sm mb-4 text-black dark:text-white">Add a Comment</h4>
                            
                            <template x-if="!isLoggedIn">
                                <div class="text-center py-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl">
                                    <p class="text-zinc-500 text-sm font-medium mb-3">Join the discussion to share your thoughts.</p>
                                    <button @click="showLogin = true" class="font-dot text-sm border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 px-6 py-2 rounded-full hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all">Sign In</button>
                                </div>
                            </template>
                            
                            <template x-if="isLoggedIn">
                                <div class="flex gap-3 items-start">
                                    <img :src="userAvatar" class="w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-800">
                                    <div class="flex-1">
                                        <textarea x-model="newComment" rows="2" placeholder="Write your thoughts (Emojis supported 😊)..." class="w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-black dark:text-white rounded-xl px-4 py-3 text-sm font-medium placeholder-zinc-400 dark:placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all resize-none custom-scrollbar mb-3"></textarea>
                                        <div class="flex justify-end">
                                            <button @click="submitComment()" :disabled="!newComment.trim() || isSubmittingComment" class="font-dot text-xs bg-black text-white dark:bg-white dark:text-black px-6 py-2 rounded-full hover:bg-zinc-800 dark:hover:bg-zinc-200 transition shadow-md disabled:opacity-50 flex items-center gap-2">
                                                <span x-show="!isSubmittingComment">Comment</span>
                                                <span x-show="isSubmittingComment"><i class="fas fa-circle-notch fa-spin"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mb-5">
                            <h3 class="font-bold text-lg mb-4 text-black dark:text-white">Comments (<span x-text="singlePost.all_comments ? singlePost.all_comments.length : 0"></span>)</h3>
                            <template x-if="!singlePost.all_comments || singlePost.all_comments.length === 0">
                                <p class="text-zinc-500 text-sm font-dot">No comments yet. Be the first to share your thoughts!</p>
                            </template>
                            <div class="space-y-4">
                                <template x-for="(cmt, idx) in singlePost.all_comments" :key="idx">
                                    <div class="flex gap-4 items-start bg-nothing-card border border-nothing p-4 rounded-2xl shadow-sm">
                                        <img :src="cmt.avatar" class="w-8 h-8 rounded-full border border-zinc-200 dark:border-zinc-800">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <h5 class="text-sm font-bold text-black dark:text-white" x-text="cmt.author"></h5>
                                                <span class="text-[10px] text-zinc-500 font-dot" x-text="cmt.time"></span>
                                            </div>
                                            <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed" x-html="decodeHTML(cmt.comment)"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="!isSingleView && activeTab === 'feed'">
                    <div>
                        <template x-if="posts.length === 0">
                            <div class="text-center py-12 text-zinc-500 font-dot">No posts available yet.</div>
                        </template>
                        <template x-for="post in posts" :key="post.id">
                            <div class="bg-nothing-card border border-nothing rounded-3xl p-5 sm:p-6 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all shadow-sm hover:shadow-md cursor-pointer group mb-5" @click="viewPost(post.id, 'post')">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-3">
                                        <img :src="post.avatar" class="w-10 h-10 rounded-full border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                        <div>
                                            <h4 class="text-sm font-bold text-black dark:text-white flex items-center gap-1.5" x-text="post.author"></h4>
                                            <p class="font-dot text-[10px] text-zinc-500 mt-0.5 flex items-center gap-1"><i class="fas fa-clock text-[8px]"></i> <span x-text="post.time"></span></p>
                                        </div>
                                    </div>
                                    <button class="text-zinc-400 hover:text-black dark:hover:text-white p-2" @click.stop=""><i class="fas fa-ellipsis-h"></i></button>
                                </div>
                                <h2 x-show="post.title" class="text-lg font-bold text-black dark:text-white mb-2 leading-tight" x-text="post.title"></h2>
                                
                                <div class="text-zinc-700 dark:text-zinc-300 text-sm leading-relaxed mb-4 line-clamp-4" x-html="decodeHTML(post.content)"></div>
                                
                                <template x-if="post.image">
                                    <div class="mb-4 rounded-xl overflow-hidden border border-nothing">
                                        <img :src="post.image" loading="lazy" class="w-full h-auto object-cover max-h-80 hover:scale-105 transition-transform duration-500">
                                    </div>
                                </template>
                                
                                <div class="flex flex-wrap gap-2 mb-5">
                                    <template x-for="tag in post.tags">
                                        <span x-show="tag" class="text-[10px] font-dot text-blue-600 bg-blue-50 border border-blue-100 dark:text-blue-400 dark:bg-blue-500/10 dark:border-blue-500/20 px-2.5 py-1 rounded-md" x-text="tag"></span>
                                    </template>
                                </div>

                                <div class="flex items-center gap-6 text-zinc-500 text-xs font-bold pt-4 border-t border-nothing">
                                    <button class="flex items-center gap-2 hover-red transition nav-item" @click.stop="toggleLike(post.id)">
                                        <i class="fa-heart text-base" :class="post.liked ? 'fa-solid text-nothing-red' : 'fa-regular'"></i> 
                                        <span x-text="post.likes" :class="post.liked ? 'text-nothing-red' : ''"></span>
                                    </button>
                                    <button class="flex items-center gap-2 hover:text-black dark:hover:text-white transition nav-item">
                                        <i class="fa-regular fa-comment text-base"></i> <span x-text="post.comments"></span>
                                    </button>
                                    <button class="flex items-center gap-2 hover:text-black dark:hover:text-white transition ml-auto nav-item" @click.stop="copyToClipboard(window.location.origin + window.location.pathname + '?post=' + post.id)">
                                        <i class="fa-solid fa-link text-sm"></i> <span class="hidden sm:inline">Copy Link</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="posts.length > 0 && hasMorePosts" class="text-center py-4">
                            <button @click="loadFeed('post')" class="font-dot text-xs px-6 py-2 rounded-full border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all">
                                <span x-show="!isPaginating">Load More</span>
                                <i x-show="isPaginating" class="fas fa-spinner fa-spin"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="!isSingleView && activeTab === 'news'">
                    <div>
                        <template x-if="news.length === 0">
                            <div class="text-center py-12 text-zinc-500 font-dot">No official news available yet.</div>
                        </template>
                        <template x-for="item in news" :key="item.id">
                            <div class="relative bg-slate-50 dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 sm:p-8 overflow-hidden cursor-pointer hover:border-zinc-400 dark:hover:border-zinc-600 shadow-sm hover:shadow-md transition-all group mb-5" @click="viewPost(item.id, 'news')">
                                <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-[#f02d2d] to-orange-500"></div>
                                <div class="flex justify-between items-start mb-4 pl-2">
                                    <div>
                                        <div class="inline-flex items-center gap-1.5 bg-[#f02d2d]/10 text-nothing-red px-2.5 py-1 rounded text-[10px] font-dot font-bold mb-3 border border-[#f02d2d]/20"><i class="fas fa-bolt"></i> OFFICIAL</div>
                                        <h2 class="text-xl sm:text-2xl font-black text-black dark:text-white leading-tight mb-3 group-hover:text-[#f02d2d] transition-colors" x-text="item.title"></h2>
                                        
                                        <p class="text-zinc-600 dark:text-zinc-400 text-sm mb-5 leading-relaxed line-clamp-3" x-html="decodeHTML(item.content)"></p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pl-2 pt-4 border-t border-nothing">
                                    <p class="font-dot text-[10px] text-zinc-500 flex items-center gap-2"><i class="fas fa-calendar-day"></i> <span x-text="item.time"></span></p>
                                    <div class="flex gap-5 text-zinc-500 text-xs font-bold">
                                        <button class="flex items-center gap-1.5 hover-red transition nav-item" @click.stop="toggleLike(item.id)">
                                            <i class="fa-heart text-sm" :class="item.liked ? 'fa-solid text-nothing-red' : 'fa-regular'"></i> 
                                            <span x-text="item.likes" :class="item.liked ? 'text-nothing-red' : ''"></span>
                                        </button>
                                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-comment text-sm"></i> <span x-text="item.comments"></span></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="news.length > 0 && hasMoreNews" class="text-center py-4">
                            <button @click="loadFeed('news')" class="font-dot text-xs px-6 py-2 rounded-full border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all">
                                <span x-show="!isPaginating">Load More</span>
                                <i x-show="isPaginating" class="fas fa-spinner fa-spin"></i>
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </main>
    </div>

    <footer class="mt-auto border-t border-nothing py-8 bg-white dark:bg-[#050505] text-center pb-28 lg:pb-8 transition-colors">
        <p class="font-dot text-zinc-500 text-xs tracking-widest mb-2">NOVAIRA GLOBAL MARKETING SOLUTIONS</p>
        <p class="text-zinc-600 dark:text-zinc-500 text-[10px]">&copy; <?= date('Y') ?> All Rights Reserved.</p>
    </footer>

    <nav class="lg:hidden fixed bottom-4 left-4 right-4 z-40 bg-white/70 dark:bg-[#0a0a0a]/70 backdrop-blur-xl border border-zinc-200 dark:border-zinc-800 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.12)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.5)] flex justify-between items-center px-4 py-2 transition-colors">
        <button @click="switchTab('feed')" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full transition-all" :class="(!isSingleView && activeTab === 'feed') ? 'text-black dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white'"><i class="fas fa-layer-group text-lg"></i></button>
        <button @click="switchTab('news')" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full transition-all" :class="(!isSingleView && activeTab === 'news') ? 'text-[#f02d2d]' : 'text-zinc-500 dark:text-zinc-400 hover:text-[#f02d2d]'"><i class="fas fa-bullhorn text-lg"></i></button>
        
        <button @click="isLoggedIn ? window.location.href='create-post' : showLogin=true" class="nav-item flex items-center justify-center w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-full shadow-lg shadow-blue-500/40 border-4 border-white dark:border-[#0a0a0a] transform -translate-y-4">
            <i class="fas fa-plus text-xl"></i>
        </button>
        
        <button @click="openSearchModal()" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white relative"><i class="fas fa-search text-lg"></i></button>
        
        <button @click="isLoggedIn ? showProfileSidebar = true : showLogin = true" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full transition-all">
            <template x-if="isLoggedIn">
                <img :src="userAvatar" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 shadow-sm">
            </template>
            <template x-if="!isLoggedIn">
                <div class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 shadow-sm flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 text-zinc-500">
                    <i class="fas fa-user text-sm"></i>
                </div>
            </template>
        </button>
    </nav>

    <template x-if="isLoggedIn">
        <div x-show="showProfileSidebar" x-cloak class="fixed inset-0 z-[110] lg:hidden flex justify-end">
            <div x-show="showProfileSidebar" x-transition.opacity @click="showProfileSidebar = false" class="absolute inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm"></div>
            <div x-show="showProfileSidebar" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="relative w-64 h-full bg-white dark:bg-[#0a0a0a] border-l border-zinc-200 dark:border-zinc-800 shadow-2xl flex flex-col"
                 @touchstart="sidebarStartX = $event.touches[0].clientX"
                 @touchmove="handleSidebarTouchMove"
                 @touchend="handleSidebarTouchEnd">
                 
                <div class="p-6 border-b border-nothing text-center mt-4 relative">
                    <button @click="showProfileSidebar = false" class="absolute top-4 left-4 w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-900 text-zinc-400 hover:text-black dark:hover:text-white transition-colors"><i class="fas fa-times"></i></button>
                    <img :src="userAvatar" class="w-20 h-20 rounded-full border-4 border-zinc-100 dark:border-zinc-800 mx-auto mb-3 shadow-md">
                    <h3 class="font-bold text-lg text-black dark:text-white" x-text="userName"></h3>
                    <p class="text-xs text-zinc-500 font-dot mt-1 truncate" x-text="userEmail"></p>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-2">
                    <button @click="goBackToFeed(); showProfileSidebar=false;" class="w-full flex items-center gap-4 p-3 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"><i class="fas fa-layer-group w-5 text-center text-blue-500"></i> My Posts</button>
                    <button class="w-full flex items-center gap-4 p-3 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"><i class="fas fa-bookmark w-5 text-center text-amber-500"></i> Saved</button>
                    <button @click="showMobileSettings = true; showProfileSidebar = false;" class="w-full flex items-center gap-4 p-3 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"><i class="fas fa-cog w-5 text-center text-zinc-400"></i> Settings</button>
                </div>
                
                <div class="p-4 border-t border-nothing">
                    <button @click="window.location.href='?logout=1'" class="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 font-bold transition-colors hover:bg-red-100 dark:hover:bg-red-500/20"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </div>
            </div>
        </div>
    </template>

    <div x-show="showMobileSettings" x-cloak class="fixed inset-0 z-[120] lg:hidden flex items-end justify-center">
        <div x-show="showMobileSettings" x-transition.opacity @click="showMobileSettings = false" class="absolute inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm"></div>
        <div x-show="showMobileSettings" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="relative w-full h-[45vh] bg-white dark:bg-[#0a0a0a] rounded-t-3xl border-t border-zinc-200 dark:border-zinc-800 shadow-2xl flex flex-col"
             @touchstart="startY = $event.touches[0].clientY"
             @touchmove="handleTouchMove"
             @touchend="handleTouchEnd">
            <div class="w-full flex justify-center pt-4 pb-2 cursor-grab" id="swipeHandle"><div class="w-12 h-1.5 bg-zinc-300 dark:bg-zinc-700 rounded-full"></div></div>
            <div class="px-6 py-2 border-b border-nothing flex justify-between items-center">
                <h3 class="font-dot font-bold text-xl text-black dark:text-white">Settings</h3>
                <button @click="showMobileSettings = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900 text-zinc-500 hover:text-black dark:hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">
                <p class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-4 font-dot">Appearance</p>
                <div class="space-y-3">
                    <button @click="setTheme('light')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'light' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'"><div class="flex items-center gap-3 font-bold"><i class="fas fa-sun text-lg w-5"></i> Light Mode</div><i class="fas fa-check-circle" x-show="theme === 'light'"></i></button>
                    <button @click="setTheme('dark')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'dark' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'"><div class="flex items-center gap-3 font-bold"><i class="fas fa-moon text-lg w-5"></i> Dark Mode</div><i class="fas fa-check-circle" x-show="theme === 'dark'"></i></button>
                    <button @click="setTheme('system')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'system' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'"><div class="flex items-center gap-3 font-bold"><i class="fas fa-desktop text-lg w-5"></i> System Default</div><i class="fas fa-check-circle" x-show="theme === 'system'"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showSearchModal" x-cloak class="fixed inset-0 z-[150] flex items-start justify-center p-4 pt-20">
        <div x-show="showSearchModal" x-transition.opacity @click="showSearchModal = false" class="absolute inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm"></div>
        <div x-show="showSearchModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="relative bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl flex flex-col">
            <div class="flex items-center p-4 border-b border-nothing">
                <i class="fas fa-search text-zinc-400 ml-2"></i>
                <input type="text" x-model="searchQuery" x-ref="searchInput" placeholder="Search posts, news, or tags..." class="flex-1 bg-transparent border-none outline-none px-4 py-2 text-black dark:text-white placeholder-zinc-500 font-medium">
                <button @click="showSearchModal = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900 text-zinc-500 hover:text-black dark:hover:text-white transition-colors"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 max-h-[60vh] overflow-y-auto custom-scrollbar">
                <p x-show="!searchQuery.trim()" class="text-center text-sm font-dot text-zinc-500 py-8">Type something to search the community.</p>
                <div x-show="searchQuery.trim()" class="space-y-2">
                    <p class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-3">Results</p>
                    <button class="w-full text-left p-3 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-900 flex items-center gap-3 transition">
                        <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-500 flex items-center justify-center"><i class="fas fa-hashtag"></i></div>
                        <div>
                            <p class="font-bold text-sm text-black dark:text-white">Search tags for "<span x-text="searchQuery"></span>"</p>
                            <p class="text-xs text-zinc-500 mt-0.5">Explore related topics</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showLogin" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center p-4">
        <div x-show="showLogin" x-transition.opacity @click="showLogin = false" class="absolute inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm"></div>
        <div x-show="showLogin" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 w-full max-w-sm rounded-3xl p-8 text-center shadow-2xl">
            <button @click="showLogin = false" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900 text-zinc-500 hover:text-black dark:hover:text-white transition-colors"><i class="fas fa-times"></i></button>
            <div class="w-14 h-14 bg-black text-white dark:bg-white dark:text-black flex items-center justify-center font-black text-3xl rounded-xl mx-auto mb-6 shadow-sm">N</div>
            <h3 class="font-dot text-2xl font-bold text-black dark:text-white mb-2">JOIN NOVAIRA</h3>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mb-8 font-medium">Sign in to react, comment, and join the community discussion.</p>
            <div id="googleButtonContainer" class="flex justify-center w-full min-h-[44px]"></div>
            <p class="text-[10px] text-zinc-500 mt-5">By continuing, you agree to Novaira's Terms of Service & Privacy Policy.</p>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function handleCredentialResponse(response) {
        const fd = new FormData();
        fd.append('action', 'google_login');
        fd.append('credential', response.credential);

        fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') { window.location.reload(); } 
            else { alert(data.msg); }
        });
    }

    window.onload = function () {
        google.accounts.id.initialize({
            client_id: "754171446556-2pjkhjfbpfbl6ucpg23j4s5di4n08323.apps.googleusercontent.com",
            callback: handleCredentialResponse,
            auto_select: false 
        });
        google.accounts.id.renderButton(
            document.getElementById("googleButtonContainer"),
            { theme: document.documentElement.classList.contains('dark') ? "filled_black" : "outline", size: "large", shape: "pill", width: "100%" }
        );
        <?php if($is_logged_in === 'false'): ?>
            // Only prompt if they aren't actively trying to read a single post
            <?php if(!$is_single_view): ?>
                setTimeout(() => { google.accounts.id.prompt(); }, 2000); 
            <?php endif; ?>
        <?php endif; ?>
    }

    function communityApp() {
        return {
            isLoading: true, isPaginating: false, isLoggedIn: <?= $is_logged_in ?>,
            userName: <?= json_encode($user_name) ?>, userEmail: <?= json_encode($user_email) ?>,
            get userAvatar() { 
                if(!this.isLoggedIn || this.userName.trim() === '') return '';
                return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(this.userName) + '&background=random&color=fff'; 
            },

            activeTab: '<?= isset($_GET['news']) ? 'news' : 'feed' ?>', 
            isSingleView: <?= $is_single_view ? 'true' : 'false' ?>,
            singlePostId: '<?= $single_post_id ?>', singlePostType: '<?= $single_post_type ?>', singlePost: null,
            newComment: '', isSubmittingComment: false,

            isCreatingPost: false, isSubmitting: false,
            newPost: { title: '', content: '', tagInput: '', tags: [], showPoll: false, pollOptions: ['', ''] },
            showDesktopTagSuggestions: false, showLogin: false, showDesktopSettings: false, showMobileSettings: false, showProfileSidebar: false, showDesktopProfile: false,
            showSearchModal: false, searchQuery: '', showNotifications: false,
            theme: localStorage.getItem('novaira_theme') || 'system',
            startY: 0, sidebarStartX: 0,
            
            availableTags: ['NovairaV2', 'Updates', 'Discussion', 'AppReviews', 'Payouts', 'Feedback', 'Guidelines', 'Help', 'Community', 'BugReport'],
            trendingTags: [{ name: '#NovairaV2', isHot: true }, { name: '#AppReviews', isHot: true }, { name: '#Updates', isHot: false }],
            posts: [], news: [], postPage: 1, newsPage: 1, hasMorePosts: true, hasMoreNews: true,

            // DECODE HTML HELPER TO PREVENT LITERAL <BR> RENDERING
            decodeHTML(html) {
                if (!html) return '';
                var txt = document.createElement("textarea");
                txt.innerHTML = html;
                return txt.value;
            },

            initApp() {
                if (this.isSingleView) {
                    this.loadSinglePost();
                    // Load feeds silently in background so they are ready if user goes back
                    this.loadFeed('post', false);
                    this.loadFeed('news', false);
                } else {
                    this.loadFeed('post', true);
                    this.loadFeed('news', true); 
                }

                // Handle Browser Back/Forward natively without page refresh
                window.addEventListener('popstate', (e) => {
                    const params = new URLSearchParams(window.location.search);
                    const postId = params.get('post');
                    const newsId = params.get('news');

                    if (postId || newsId) {
                        this.singlePostId = postId || newsId;
                        this.singlePostType = postId ? 'post' : 'news';
                        this.isSingleView = true;
                        this.loadSinglePost();
                    } else {
                        this.isSingleView = false;
                        this.singlePost = null;
                    }
                });

                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                    if (this.theme === 'system') {
                        if (e.matches) document.documentElement.classList.add('dark');
                        else document.documentElement.classList.remove('dark');
                    }
                });
            },

            async viewPost(id, type) {
                this.isLoading = true;
                this.singlePostId = id;
                this.singlePostType = type;
                this.isSingleView = true;
                
                history.pushState({ id, type }, '', window.location.pathname + `?${type}=${id}`);
                
                await this.loadSinglePost();
                window.scrollTo({top: 0, behavior: 'smooth'});
            },

            goBackToFeed() {
                this.isSingleView = false;
                this.singlePost = null;
                this.activeTab = this.singlePostType === 'news' ? 'news' : 'feed';
                
                history.pushState(null, '', window.location.pathname);
                
                if (this.activeTab === 'feed' && this.posts.length === 0) this.loadFeed('post', true);
                if (this.activeTab === 'news' && this.news.length === 0) this.loadFeed('news', true);
                
                window.scrollTo({top: 0, behavior: 'smooth'});
            },

            switchTab(tab) {
                if(this.isSingleView) {
                    this.isSingleView = false;
                    this.singlePost = null;
                    history.pushState(null, '', window.location.pathname);
                }
                this.activeTab = tab; 
                window.scrollTo({top:0, behavior:'smooth'});
            },

            openSearchModal() {
                if(!this.isLoggedIn) {
                    this.showLogin = true;
                    return;
                }
                this.showSearchModal = true;
                setTimeout(() => this.$refs.searchInput.focus(), 100);
            },

            get filteredDesktopTags() {
                if (this.newPost.tagInput.trim() === '') return this.availableTags.filter(tag => !this.newPost.tags.includes(tag)).slice(0, 5);
                const search = this.newPost.tagInput.toLowerCase();
                return this.availableTags.filter(tag => tag.toLowerCase().includes(search) && !this.newPost.tags.includes(tag));
            },

            async loadFeed(type, isInitial = false) {
                if(type === 'post' && !this.hasMorePosts && !isInitial) return;
                if(type === 'news' && !this.hasMoreNews && !isInitial) return;
                if(!isInitial) this.isPaginating = true;

                const page = type === 'post' ? this.postPage : this.newsPage;
                try {
                    let res = await fetch(`api/fetch_feed.php?action=get_${type === 'post' ? 'feed' : 'news'}&page=${page}`);
                    let data = await res.json();
                    if(data.status === 'success') {
                        if(data.data.length < 10) { type === 'post' ? this.hasMorePosts = false : this.hasMoreNews = false; }
                        if(type === 'post') { this.posts = isInitial ? data.data : [...this.posts, ...data.data]; this.postPage++; } 
                        else { this.news = isInitial ? data.data : [...this.news, ...data.data]; this.newsPage++; }
                    }
                } catch(e) {}
                if(isInitial) setTimeout(() => { this.isLoading = false; }, 500); 
                this.isPaginating = false;
            },

            async loadSinglePost() {
                this.isLoading = true;
                try {
                    let res = await fetch(`api/fetch_feed.php?action=get_single_post&id=${this.singlePostId}`);
                    let data = await res.json();
                    if(data.status === 'success' && data.data.length > 0) {
                        this.singlePost = data.data[0];
                        this.singlePost.type = this.singlePostType;
                        if(this.singlePost.poll_data) {
                            try {
                                this.singlePost.poll_data = typeof this.singlePost.poll_data === 'string' ? JSON.parse(this.singlePost.poll_data) : this.singlePost.poll_data;
                                this.singlePost.total_votes = this.singlePost.poll_data.reduce((sum, opt) => sum + parseInt(opt.votes), 0);
                            } catch(e) {}
                        }
                    } else { 
                        this.goBackToFeed(); 
                    }
                } catch(e) {}
                setTimeout(() => { this.isLoading = false; }, 400);
            },

            setTheme(val) {
                this.theme = val; localStorage.setItem('novaira_theme', val);
                if (val === 'dark' || (val === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
                else document.documentElement.classList.remove('dark');
                this.showDesktopSettings = false; this.showMobileSettings = false;
            },

            handleTouchMove(e) {
                if (!this.showMobileSettings) return;
                if (!this.startY) this.startY = e.touches[0].clientY;
                if (e.touches[0].clientY - this.startY > 50) { this.showMobileSettings = false; this.startY = 0; }
            },
            handleTouchEnd() { this.startY = 0; },
            
            handleSidebarTouchMove(e) {
                if (!this.showProfileSidebar) return;
                if (!this.sidebarStartX) this.sidebarStartX = e.touches[0].clientX;
                if (e.touches[0].clientX - this.sidebarStartX > 50) { this.showProfileSidebar = false; this.sidebarStartX = 0; }
            },
            handleSidebarTouchEnd() { this.sidebarStartX = 0; },

            async toggleLike(postId, isSingle = false) {
                if(!this.isLoggedIn) { this.showLogin = true; return; }
                let post = isSingle ? this.singlePost : (this.posts.find(p => p.id === postId) || this.news.find(p => p.id === postId));
                if(post) {
                    post.liked = !post.liked;
                    post.likes += post.liked ? 1 : -1;
                    const fd = new FormData(); fd.append('action', 'toggle_like'); fd.append('post_id', postId);
                    fetch('api/post_action.php', { method: 'POST', body: fd });
                }
            },

            async submitComment() {
                if(!this.newComment.trim() || !this.singlePost) return;
                this.isSubmittingComment = true;
                const fd = new FormData(); fd.append('action', 'add_comment'); fd.append('post_id', this.singlePost.id); fd.append('comment', this.newComment);
                try {
                    let res = await fetch('api/post_action.php', { method: 'POST', body: fd });
                    let data = await res.json();
                    if(data.status === 'success') {
                        this.singlePost.comments++;
                        if(!this.singlePost.all_comments) this.singlePost.all_comments = [];
                        this.singlePost.all_comments.unshift({
                            author: this.userName, avatar: this.userAvatar, comment: this.newComment.replace(/\n/g, '<br>'), time: 'Just Now'
                        });
                        this.newComment = '';
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Comment added!', showConfirmButton: false, timer: 1500, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#1e293b' });
                    } else {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: data.msg, showConfirmButton: false, timer: 2000 });
                    }
                } catch(e) {}
                this.isSubmittingComment = false;
            },

            copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied!', showConfirmButton: false, timer: 1500, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#1e293b' }); }); },

            addDesktopTag(selectedTag = null) {
                let tag = selectedTag ? selectedTag : this.newPost.tagInput.trim();
                tag = tag.replace(/^#/, '').replace(/[^a-zA-Z0-9-]/g, '');
                if (tag && !this.newPost.tags.includes(tag)) {
                    if(this.newPost.tags.length < 5) this.newPost.tags.push(tag);
                    else Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Max 5 tags allowed.', showConfirmButton: false, timer: 2000 });
                }
                this.newPost.tagInput = ''; this.showDesktopTagSuggestions = false;
            },

            resetForm() { this.newPost = { title: '', content: '', tagInput: '', tags: [], showPoll: false, pollOptions: ['', ''] }; },

            async submitPost() {
                if(!this.newPost.content.trim()) return;
                if(this.newPost.showPoll) {
                    const filledOptions = this.newPost.pollOptions.filter(opt => opt.trim() !== '');
                    if(filledOptions.length < 2) { Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Fill at least 2 poll options.', showConfirmButton: false, timer: 2000 }); return; }
                }
                this.isSubmitting = true;
                let fd = new FormData(); fd.append('action', 'create_post'); fd.append('title', this.newPost.title); fd.append('content', this.newPost.content);
                if(this.newPost.tags.length > 0) fd.append('tags', this.newPost.tags.map(t => '#' + t).join(','));
                if(this.newPost.showPoll) fd.append('poll_options', JSON.stringify(this.newPost.pollOptions));
                try {
                    let res = await fetch('api/post_action.php', { method: 'POST', body: fd });
                    let data = await res.json();
                    if(data.status === 'success') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.msg, showConfirmButton: false, timer: 2000, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#1e293b' });
                        this.resetForm(); this.isCreatingPost = false;
                        
                        this.postPage = 1; this.posts = []; this.hasMorePosts = true; this.loadFeed('post', true);
                    } else { Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: data.msg, showConfirmButton: false, timer: 2000 }); }
                } catch(e) {}
                this.isSubmitting = false;
            }
        }
    }
</script>
</body>
</html>