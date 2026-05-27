<?php
session_start();

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

// Security Check: Must be logged in to create a post
if (!isset($_SESSION['user_id'])) {
    header("Location: feed");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '');
$user_email = $_SESSION['email'] ?? "guest@novaira.com";

// Auto-patch: add poll_data column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS poll_data JSON NULL AFTER image_url;");
} catch(Exception $e) {}

// ==========================================
// HANDLE POST CREATION AJAX
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    header('Content-Type: application/json');
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

        // Generate Unique Post ID
        $post_id = strtoupper(substr(md5(uniqid()), 0, 10));
        $now = date('Y-m-d H:i:s');

        // Insert into Database
        $stmt = $pdo->prepare("INSERT INTO community_posts (post_id, user_id, post_type, title, content, poll_data, tags, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        $stmt->execute([$post_id, $user_id, $post_type, $title, $content, $poll_json, $tags, $now]);

        echo json_encode(['status' => 'success', 'msg' => 'Post created successfully!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: feed");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Create Post | Novaira Community</title>
    
    <script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.onkeydown = function(e) {
            if(e.keyCode == 123) return false; 
            if(e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'C'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) return false; 
            if(e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false; 
        }
    </script>

    <script>
        const savedTheme = localStorage.getItem('novaira_theme') || 'system';
        if (savedTheme === 'dark' || (savedTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Hide Alpine elements until initialized */
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

        .skeleton {
            background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
            background-size: 200% 100%;
            animation: skeletonLoading 1.5s infinite;
        }
        .dark .skeleton {
            background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%);
            background-size: 200% 100%;
        }
        @keyframes skeletonLoading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body x-data="createPostApp()" class="overflow-x-hidden no-scrollbar flex flex-col min-h-screen" x-init="initApp()" @touchmove="handleTouchMove" @touchend="handleTouchEnd">

    <nav class="sticky top-0 z-40 bg-white/80 dark:bg-[#050505]/80 backdrop-blur-xl border-b border-nothing border-b-[1px] transition-colors">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between relative">
            <div class="flex items-center gap-3">
                <button onclick="window.history.back()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors mr-2">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <span class="font-dot text-xl font-bold tracking-widest text-black dark:text-white transition-colors">CREATE POST</span>
            </div>
            
            <div class="flex items-center gap-4">
                <img @click="showProfileSidebar = true" src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=random&color=fff" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 cursor-pointer shadow-sm lg:hidden block">
                
                <div class="relative hidden lg:block">
                    <img @click="showDesktopProfile = !showDesktopProfile" @click.outside="showDesktopProfile = false" src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=random&color=fff" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 cursor-pointer shadow-sm">
                    
                    <div x-show="showDesktopProfile" x-transition x-cloak class="absolute right-0 mt-4 w-52 bg-white dark:bg-[#0a0a0a] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl py-2 z-50">
                        <div class="px-4 py-3 border-b border-nothing mb-2">
                            <p class="text-sm font-bold text-black dark:text-white leading-tight"><?= $user_name ?></p>
                            <p class="text-[10px] text-zinc-500 font-dot mt-1 truncate"><?= $user_email ?></p>
                        </div>
                        <a href="feed" class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 flex items-center gap-3 text-zinc-700 dark:text-zinc-300 transition-colors">
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
        </div>
    </nav>

    <div class="flex-grow w-full max-w-4xl mx-auto px-4 py-8 pb-32 relative">
        
        <template x-if="isLoading">
            <div class="bg-nothing-card border border-nothing rounded-3xl p-6 sm:p-8 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <div class="skeleton h-4 w-32 rounded mb-2"></div>
                        <div class="skeleton h-12 w-full rounded-xl"></div>
                    </div>
                    <div>
                        <div class="skeleton h-4 w-40 rounded mb-2"></div>
                        <div class="skeleton h-32 w-full rounded-2xl"></div>
                    </div>
                    <div>
                        <div class="skeleton h-4 w-24 rounded mb-2"></div>
                        <div class="skeleton h-12 w-full rounded-xl"></div>
                    </div>
                    <div class="pt-6 border-t border-nothing flex justify-between">
                        <div class="skeleton h-8 w-20 rounded"></div>
                        <div class="skeleton h-12 w-40 rounded-full"></div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!isLoading">
            <form x-ref="postForm" @submit.prevent="submitPost" class="bg-nothing-card border border-nothing rounded-3xl p-6 sm:p-8 shadow-sm relative overflow-visible" x-transition.opacity.duration.500ms>
                
                <div class="absolute top-0 right-0 p-8 opacity-5 text-9xl text-blue-500 pointer-events-none -mt-10 -mr-10"><i class="fas fa-pen-nib"></i></div>

                <div class="relative z-10 flex flex-col gap-6">
                    
                    <div>
                        <label class="block font-dot text-xs text-zinc-500 mb-2">Title (Optional)</label>
                        <input type="text" x-model="title" placeholder="Give your post a catchy title..." class="w-full bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 text-black dark:text-white rounded-xl px-4 py-3 text-sm font-bold placeholder-zinc-400 dark:placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all shadow-inner">
                    </div>

                    <div>
                        <label class="block font-dot text-xs text-zinc-500 mb-2">What's on your mind? *</label>
                        <textarea x-model="content" rows="5" placeholder="Type your thoughts here..." class="w-full bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 text-black dark:text-white rounded-2xl px-5 py-4 text-sm font-medium placeholder-zinc-400 dark:placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all resize-none custom-scrollbar shadow-inner"></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <button type="button" @click="showPoll = !showPoll" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 transition flex items-center gap-1.5"><i class="fas fa-poll"></i> <span x-text="showPoll ? 'Remove Poll' : 'Add Poll'"></span></button>
                            <span class="text-[10px] text-zinc-500 font-medium font-mono" x-text="content.length + ' / 1000'"></span>
                        </div>
                    </div>

                    <div x-show="showPoll" x-collapse>
                        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/50 rounded-2xl p-5 shadow-sm">
                            <h4 class="font-dot text-xs text-blue-600 dark:text-blue-400 mb-3 flex items-center gap-2"><i class="fas fa-list-ol"></i> Create a Poll</h4>
                            
                            <div class="space-y-3">
                                <template x-for="(opt, index) in pollOptions" :key="index">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-500" x-text="index + 1"></div>
                                        <input type="text" x-model="pollOptions[index]" :placeholder="'Option ' + (index + 1)" class="flex-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-black dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                                        <button type="button" x-show="pollOptions.length > 2" @click="removePollOption(index)" class="text-zinc-400 hover:text-red-500 w-8 h-8 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
                                    </div>
                                </template>
                            </div>
                            
                            <button type="button" x-show="pollOptions.length < 5" @click="addPollOption" class="mt-4 text-xs font-bold text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white transition flex items-center gap-1.5"><i class="fas fa-plus"></i> Add Option</button>
                        </div>
                    </div>

                    <div class="relative">
                        <label class="block font-dot text-xs text-zinc-500 mb-2 flex items-center justify-between">
                            <span>Tags (Max 5)</span>
                            <button type="button" @click="showTagSuggestions = !showTagSuggestions; if(showTagSuggestions) setTimeout(()=> $refs.tagInputRef.focus(), 100)" class="text-[10px] text-blue-500 hover:underline"><i class="fas fa-tags"></i> View Tags</button>
                        </label>
                        <div class="bg-slate-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl px-4 py-3 flex flex-wrap gap-2 items-center shadow-inner focus-within:ring-2 focus-within:ring-blue-500/50 transition-all relative z-20">
                            
                            <template x-for="(tag, index) in tags" :key="index">
                                <div class="flex items-center gap-1.5 bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-3 py-1 rounded-lg text-xs font-bold border border-blue-200 dark:border-blue-500/30">
                                    <span x-text="'#' + tag"></span>
                                    <button type="button" @click="removeTag(index)" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
                                </div>
                            </template>

                            <input type="text" x-ref="tagInputRef" x-model="tagInput" @focus="showTagSuggestions = true" @keydown.enter.prevent="addTag(tagInput)" @keydown.comma.prevent="addTag(tagInput)" placeholder="Add a tag..." class="bg-transparent border-none outline-none text-sm text-black dark:text-white flex-1 min-w-[120px]" :disabled="tags.length >= 5">
                        </div>

                        <div x-show="showTagSuggestions && filteredTags.length > 0 && tags.length < 5" @click.outside="showTagSuggestions = false" x-transition x-cloak class="absolute left-0 right-0 mt-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl z-50 max-h-48 overflow-y-auto custom-scrollbar p-2">
                            <template x-for="sTag in filteredTags" :key="sTag">
                                <button type="button" @click="addTag(sTag)" class="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                    <span x-text="'#' + sTag"></span>
                                </button>
                            </template>
                        </div>
                        <p class="text-[10px] text-zinc-500 mt-2">Press <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-800">Enter</kbd> or <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-800">,</kbd> to add. Do not include #.</p>
                    </div>

                    <div class="pt-6 border-t border-nothing flex items-center justify-between">
                        <button type="button" onclick="window.history.back()" class="text-sm font-bold text-zinc-500 hover:text-black dark:hover:text-white transition-colors">Cancel</button>
                        
                        <button type="submit" :disabled="isSubmitting || (!content.trim() && !title.trim())" class="font-dot text-sm bg-black text-white dark:bg-white dark:text-black px-8 py-3 rounded-full hover:bg-zinc-800 dark:hover:bg-zinc-200 transition shadow-lg hover:shadow-xl hover:-translate-y-0.5 transform disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <span x-show="!isSubmitting">Publish Post <i class="fas fa-paper-plane ml-1"></i></span>
                            <span x-show="isSubmitting"><i class="fas fa-circle-notch fa-spin"></i> Publishing...</span>
                        </button>
                    </div>

                </div>
            </form>
        </template>
    </div>

    <nav class="lg:hidden fixed bottom-4 left-4 right-4 z-40 bg-white/70 dark:bg-[#0a0a0a]/70 backdrop-blur-xl border border-zinc-200 dark:border-zinc-800 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.12)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.5)] flex justify-between items-center px-4 py-2 transition-colors">
        <button @click="window.location.href='feed'" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white">
            <i class="fas fa-layer-group text-lg"></i>
        </button>
        <button @click="window.location.href='feed?news=1'" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full text-zinc-500 dark:text-zinc-400 hover:text-[#f02d2d]">
            <i class="fas fa-bullhorn text-lg"></i>
        </button>
        <button class="nav-item flex items-center justify-center w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-full shadow-lg shadow-blue-500/40 border-4 border-white dark:border-[#0a0a0a] transform -translate-y-4">
            <i class="fas fa-plus text-xl"></i>
        </button>
        <button @click="showMobileSettings = true" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white relative">
            <i class="fas fa-cog text-lg"></i>
        </button>
        <button @click="showProfileSidebar = true" class="nav-item flex flex-col items-center justify-center w-12 h-12 rounded-full transition-all">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=random&color=fff" class="w-8 h-8 rounded-full border border-zinc-300 dark:border-zinc-700 shadow-sm">
        </button>
    </nav>

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
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=random&color=fff" class="w-20 h-20 rounded-full border-4 border-zinc-100 dark:border-zinc-800 mx-auto mb-3 shadow-md">
                <h3 class="font-bold text-lg text-black dark:text-white"><?= $user_name ?></h3>
                <p class="text-xs text-zinc-500 font-dot mt-1 truncate"><?= $user_email ?></p>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                <button @click="window.location.href='feed'" class="w-full flex items-center gap-4 p-3 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-medium transition-colors">
                    <i class="fas fa-layer-group w-5 text-center text-blue-500"></i> My Posts
                </button>
                <button class="w-full flex items-center gap-4 p-3 rounded-xl hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-medium transition-colors">
                    <i class="fas fa-bookmark w-5 text-center text-amber-500"></i> Saved
                </button>
            </div>
            
            <div class="p-4 border-t border-nothing">
                <button @click="window.location.href='?logout=1'" class="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 font-bold transition-colors hover:bg-red-100 dark:hover:bg-red-500/20">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>

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
            
            <div class="w-full flex justify-center pt-4 pb-2 cursor-grab" id="swipeHandle">
                <div class="w-12 h-1.5 bg-zinc-300 dark:bg-zinc-700 rounded-full"></div>
            </div>

            <div class="px-6 py-2 border-b border-nothing flex justify-between items-center">
                <h3 class="font-dot font-bold text-xl text-black dark:text-white">Settings</h3>
                <button @click="showMobileSettings = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-900 text-zinc-500 hover:text-black dark:hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">
                <p class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-4 font-dot">Appearance</p>
                <div class="space-y-3">
                    <button @click="setTheme('light')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'light' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'">
                        <div class="flex items-center gap-3 font-bold"><i class="fas fa-sun text-lg w-5"></i> Light Mode</div>
                        <i class="fas fa-check-circle" x-show="theme === 'light'"></i>
                    </button>
                    <button @click="setTheme('dark')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'dark' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'">
                        <div class="flex items-center gap-3 font-bold"><i class="fas fa-moon text-lg w-5"></i> Dark Mode</div>
                        <i class="fas fa-check-circle" x-show="theme === 'dark'"></i>
                    </button>
                    <button @click="setTheme('system')" class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all" :class="theme === 'system' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-600' : 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300'">
                        <div class="flex items-center gap-3 font-bold"><i class="fas fa-desktop text-lg w-5"></i> System Default</div>
                        <i class="fas fa-check-circle" x-show="theme === 'system'"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function getSafeSwalTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            return { 
                background: isDark ? '#1e293b' : '#ffffff', 
                color: isDark ? '#f8fafc' : '#0f172a',
                customClass: { popup: 'rounded-3xl border border-slate-100 dark:border-gray-700/60 shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] dark:shadow-2xl' }
            };
        }

        function createPostApp() {
            return {
                isLoading: true,
                title: '',
                content: '',
                tags: [],
                tagInput: '',
                showTagSuggestions: false,
                availableTags: [
                    'NovairaV2', 'Updates', 'Discussion', 'AppReviews', 'Payouts', 
                    'Feedback', 'Guidelines', 'Help', 'Community', 'BugReport'
                ],
                showPoll: false,
                pollOptions: ['', ''],
                isSubmitting: false,
                
                showDesktopSettings: false,
                showMobileSettings: false,
                showProfileSidebar: false,
                showDesktopProfile: false,
                theme: localStorage.getItem('novaira_theme') || 'system',
                
                startY: 0,
                sidebarStartX: 0, 

                initApp() {
                    setTimeout(() => {
                        this.isLoading = false;
                    }, 1000);

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                        if (this.theme === 'system') {
                            if (e.matches) document.documentElement.classList.add('dark');
                            else document.documentElement.classList.remove('dark');
                        }
                    });
                },

                get filteredTags() {
                    if (this.tagInput.trim() === '') {
                        return this.availableTags.filter(tag => !this.tags.includes(tag)).slice(0, 5);
                    }
                    const search = this.tagInput.toLowerCase();
                    return this.availableTags.filter(tag => 
                        tag.toLowerCase().includes(search) && !this.tags.includes(tag)
                    );
                },

                setTheme(val) {
                    this.theme = val;
                    localStorage.setItem('novaira_theme', val);
                    if (val === 'dark' || (val === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                    this.showDesktopSettings = false;
                    this.showMobileSettings = false;
                },

                handleTouchMove(e) {
                    if (!this.showMobileSettings) return;
                    if (!this.startY) this.startY = e.touches[0].clientY;
                    const currentY = e.touches[0].clientY;
                    if (currentY - this.startY > 50) {
                        this.showMobileSettings = false;
                        this.startY = 0;
                    }
                },
                handleTouchEnd() {
                    this.startY = 0;
                },

                handleSidebarTouchMove(e) {
                    if (!this.showProfileSidebar) return;
                    if (!this.sidebarStartX) this.sidebarStartX = e.touches[0].clientX;
                    const currentX = e.touches[0].clientX;
                    if (currentX - this.sidebarStartX > 50) { 
                        this.showProfileSidebar = false;
                        this.sidebarStartX = 0;
                    }
                },
                handleSidebarTouchEnd() {
                    this.sidebarStartX = 0;
                },

                addPollOption() {
                    if(this.pollOptions.length < 5) this.pollOptions.push('');
                },
                removePollOption(index) {
                    if(this.pollOptions.length > 2) this.pollOptions.splice(index, 1);
                },

                addTag(selectedTag = null) {
                    let tag = selectedTag ? selectedTag : this.tagInput.trim();
                    tag = tag.replace(/^#/, '');
                    tag = tag.replace(/[^a-zA-Z0-9-]/g, '');
                    
                    if (tag && !this.tags.includes(tag)) {
                        if(this.tags.length < 5) {
                            this.tags.push(tag);
                        } else {
                            Swal.fire({...getSafeSwalTheme(), icon: 'warning', title: 'Limit Reached', text: 'You can only add up to 5 tags.'});
                        }
                    }
                    this.tagInput = '';
                    this.showTagSuggestions = false;
                    this.$refs.tagInputRef.focus();
                },

                removeTag(index) {
                    this.tags.splice(index, 1);
                },

                async submitPost() {
                    if(!this.content.trim() && !this.title.trim()) return;
                    
                    if(this.showPoll) {
                        const filledOptions = this.pollOptions.filter(opt => opt.trim() !== '');
                        if(filledOptions.length < 2) {
                            Swal.fire({...getSafeSwalTheme(), icon: 'warning', title: 'Poll Error', text: 'Please fill at least 2 poll options or remove the poll.'});
                            return;
                        }
                    }

                    this.isSubmitting = true;
                    
                    let fd = new FormData();
                    fd.append('action', 'create_post');
                    fd.append('title', this.title);
                    fd.append('content', this.content);
                    
                    if(this.tags.length > 0) fd.append('tags', this.tags.map(t => '#' + t).join(','));
                    if(this.showPoll) fd.append('poll_options', JSON.stringify(this.pollOptions));

                    try {
                        let res = await fetch('', { method: 'POST', body: fd });
                        let data = await res.json();
                        
                        if(data.status === 'success') {
                            Swal.fire({
                                ...getSafeSwalTheme(),
                                icon: 'success', title: 'Published!', text: data.msg,
                                timer: 1500, showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'feed';
                            });
                        } else {
                            Swal.fire({ ...getSafeSwalTheme(), icon: 'error', title: 'Oops', text: data.msg });
                        }
                    } catch(e) {
                        Swal.fire({ ...getSafeSwalTheme(), icon: 'error', title: 'Error', text: 'Network connection failed.' });
                    }
                    this.isSubmitting = false;
                }
            }
        }
    </script>
</body>
</html>