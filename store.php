<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

// Fetch all ACTIVE apps from the database
$apps = [];
$categories = ['All'];

try {
    $stmt = $pdo->query("SELECT * FROM novaira_store_apps WHERE status = 'active' ORDER BY id DESC");
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract unique categories dynamically
    foreach ($apps as $app) {
        if (!in_array($app['category'], $categories) && !empty($app['category'])) {
            $categories[] = $app['category'];
        }
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <title>Novaira App Store - Download Premium Apps & Games Fast</title>
    <meta name="description" content="Discover, download, and install top-rated premium applications at Novaira App Store. Experience a high-speed, secure, and seamless ecosystem for games, tools, and productivity apps.">
    <meta name="keywords" content="Novaira App Store, Android apps, APK download, premium tools, games, secure download, fast apk installer, latest apps">
    <meta name="author" content="Novaira Global">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="https://yourdomain.com/">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yourdomain.com/">
    <meta property="og:title" content="Novaira App Store - Premium Applications">
    <meta property="og:description" content="Download high-quality curated applications safely and instantly without leaving the page.">
    <meta property="og:image" content="/assets/images/seo-banner.jpg">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Novaira App Store">
    <meta property="twitter:description" content="Download high-quality curated applications safely and instantly.">
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "Novaira App Store",
      "url": "https://yourdomain.com/",
      "description": "Premium ecosystem for downloading fast, secure applications.",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://yourdomain.com/?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    
    <link rel="icon" href="/assets/images/logo.png" type="image/png">
    
    <script>
        // DEFAULT TO LIGHT THEME
        const savedTheme = localStorage.getItem('store_theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
            document.write('<meta name="theme-color" id="theme-color-meta" content="#020617">'); // Slate 950
        } else {
            document.documentElement.classList.remove('dark');
            document.write('<meta name="theme-color" id="theme-color-meta" content="#f8fafc">'); // Slate 50
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif']
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'pulse-fast': 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite'
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' }
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .glass-card {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:active { transform: scale(0.97); }
        [x-cloak] { display: none !important; }
        
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(to right, #2563eb, #06b6d4);
        }
    </style>
</head>
<body x-data="storeManager()" class="bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white antialiased min-h-screen relative overflow-x-hidden selection:bg-blue-500/30">

    <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none opacity-50 dark:opacity-20">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-cyan-400 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-400 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 flex flex-col min-h-screen">
        
        <header class="fixed top-0 w-full z-50 bg-white/70 dark:bg-slate-950/70 backdrop-blur-2xl border-b border-slate-200/50 dark:border-white/5 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-tr from-blue-600 to-cyan-400 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-500/30">
                        <i class="fab fa-google-play text-lg"></i>
                    </div>
                    <span class="font-black text-xl tracking-tight text-slate-900 dark:text-white">Novaira</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="$refs.searchInput.focus()" class="sm:hidden text-slate-500 dark:text-slate-400 hover:text-blue-600 transition-colors">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    
                    <button @click="toggleTheme()" class="w-10 h-10 rounded-full bg-slate-200/50 dark:bg-slate-800/50 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-300/50 dark:hover:bg-slate-700/50 transition-all border border-slate-300/50 dark:border-white/5 backdrop-blur-sm">
                        <i :class="isDark ? 'fas fa-sun text-amber-400' : 'fas fa-moon'"></i>
                    </button>
                </div>
            </div>
        </header>

        <main class="flex-grow pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
            
            <div class="text-center max-w-2xl mx-auto mb-10 animate-fade-in-up">
                <h1 class="text-4xl md:text-5xl font-black mb-4 tracking-tight">Discover Incredible <span class="text-gradient">Apps.</span></h1>
                <p class="text-slate-500 dark:text-slate-400 font-medium mb-8">Curated applications designed for the future.</p>
                
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input 
                        x-ref="searchInput"
                        x-model="searchQuery" 
                        type="text" 
                        placeholder="Games, Tools, Finance..." 
                        class="w-full pl-11 pr-4 py-4 bg-white/80 dark:bg-slate-900/60 backdrop-blur-xl border border-slate-200 dark:border-white/10 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] text-slate-900 dark:text-white font-medium focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all placeholder-slate-400"
                    >
                    <button x-show="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-white" x-cloak>
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
            </div>

            <nav class="relative mb-8 -mx-4 px-4 sm:mx-0 sm:px-0" aria-label="App Categories">
                <div class="flex space-x-3 overflow-x-auto hide-scrollbar pb-4 pt-1 snap-x">
                    <template x-for="cat in categories" :key="cat">
                        <button 
                            @click="selectedCategory = cat"
                            :class="selectedCategory === cat 
                                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30 transform scale-105 border-transparent' 
                                : 'bg-white/80 dark:bg-slate-900/60 backdrop-blur-md text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/5 hover:bg-white dark:hover:bg-slate-800/90'"
                            class="snap-start shrink-0 px-6 py-2.5 rounded-full font-bold text-sm transition-all duration-300"
                            x-text="cat">
                        </button>
                    </template>
                </div>
            </nav>

            <div x-show="filteredApps().length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <template x-for="(app, index) in filteredApps()" :key="app.id">
                    <article 
                        class="glass-card cursor-pointer group bg-white/80 dark:bg-slate-900/60 backdrop-blur-xl border border-slate-200 dark:border-white/10 rounded-3xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.2)] flex flex-col h-full animate-fade-in-up"
                        @click="openAppModal(app)"
                        :style="'animation-delay: ' + (index * 50) + 'ms'">
                        
                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-20 h-20 shrink-0 rounded-2xl bg-white dark:bg-slate-800 overflow-hidden shadow-sm border border-slate-100 dark:border-slate-700 relative group-hover:shadow-md transition-shadow">
                                <img :src="app.icon_url" :alt="app.name + ' icon'" @error="$event.target.src='https://via.placeholder.com/150?text=App'" class="w-full h-full object-cover">
                            </div>
                            
                            <div class="flex-1 min-w-0 pt-1">
                                <h3 class="font-extrabold text-lg text-slate-900 dark:text-white leading-tight truncate" x-text="app.name"></h3>
                                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-1 mb-2 truncate" x-text="app.category"></p>
                                
                                <div class="flex items-center gap-1 text-[11px] font-black text-amber-500">
                                    <i class="fas fa-star"></i>
                                    <span x-text="app.rating"></span>
                                    <span class="text-slate-400 dark:text-slate-500 font-medium ml-1" x-text="'(' + formatNumber(app.reviews_count) + ')'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-grow"></div>

                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 flex justify-between items-center">
                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-1 rounded-md uppercase tracking-wide">Free</span>
                            <button class="bg-blue-50 dark:bg-slate-800 text-blue-600 dark:text-blue-400 font-bold text-sm px-6 py-2 rounded-full hover:bg-blue-600 hover:text-white dark:hover:bg-blue-500 transition-colors shadow-sm">
                                GET
                            </button>
                        </div>
                    </article>
                </template>
            </div>

            <div x-show="filteredApps().length === 0" class="text-center py-20" x-cloak>
                <div class="w-24 h-24 bg-white/50 dark:bg-slate-800/50 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6 text-slate-400 dark:text-slate-500 text-4xl shadow-sm">
                    <i class="fas fa-ghost"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">No apps found</h3>
                <p class="text-slate-500 dark:text-slate-400">Try adjusting your search or category filter.</p>
            </div>

            <section class="mt-20 border-t border-slate-200/50 dark:border-white/5 pt-12 pb-4 text-center max-w-4xl mx-auto">
                <h2 class="text-2xl font-black text-slate-800 dark:text-white mb-4">Why Choose Novaira App Store?</h2>
                <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed mb-6">
                    Novaira App Store provides a state-of-the-art ecosystem for discovering and installing the latest Android applications. We verify all APK files to ensure 100% secure downloads.
                </p>
                <div class="flex flex-wrap justify-center gap-4 text-xs font-bold text-slate-500">
                    <span class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><i class="fas fa-shield-alt text-emerald-500 mr-1"></i> Verified Safe</span>
                    <span class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><i class="fas fa-bolt text-amber-500 mr-1"></i> Fast Download</span>
                    <span class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg"><i class="fas fa-sync text-blue-500 mr-1"></i> Auto Updates</span>
                </div>
            </section>

        </main>

        <footer class="mt-auto border-t border-slate-200/50 dark:border-white/5 py-8 text-center text-sm font-medium text-slate-500 backdrop-blur-md bg-white/30 dark:bg-slate-900/30">
            <p>&copy; <?= date('Y') ?> Novaira Global. All rights reserved. Premium App Delivery.</p>
        </footer>
    </div>

    <div x-show="isModalOpen" class="fixed inset-0 z-[100] flex justify-center items-end sm:items-center p-0 sm:p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        
        <div x-show="isModalOpen" 
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity" 
             @click="closeAppModal()"></div>
        
        <div x-show="isModalOpen" 
             x-transition:enter="ease-out duration-400" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-12 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-300" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-12 sm:scale-95" 
             class="relative z-10 w-full max-w-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-3xl rounded-t-[2.5rem] sm:rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-200 dark:border-white/10 flex flex-col max-h-[90vh]">
            
            <div class="px-6 py-4 flex justify-end sticky top-0 z-20">
                <button @click="closeAppModal()" class="w-8 h-8 rounded-full bg-slate-200/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 flex items-center justify-center hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors backdrop-blur-md shadow-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="px-6 sm:px-10 pb-10 overflow-y-auto hide-scrollbar" x-show="!showTutorial" x-if="activeApp">
                
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8 text-center sm:text-left">
                    <div class="w-32 h-32 shrink-0 rounded-[2rem] bg-white dark:bg-slate-800 overflow-hidden shadow-xl border border-slate-100 dark:border-slate-700 relative">
                        <img :src="activeApp?.icon_url" @error="$event.target.src='https://via.placeholder.com/150?text=App'" class="w-full h-full object-cover">
                    </div>
                    <div class="pt-2 flex-1 w-full">
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white leading-tight mb-1" x-text="activeApp?.name"></h2>
                        <p class="text-sm font-bold text-slate-500 mb-6" x-text="activeApp?.category"></p>
                        
                        <div class="relative w-full mx-auto sm:mx-0">
                            
                            <div x-show="downloadState === 'idle'" class="flex flex-col sm:flex-row items-center gap-4">
                                <button @click="startDownload(activeApp)" class="w-full sm:w-auto inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-3.5 rounded-full shadow-lg shadow-blue-500/30 transition-transform active:scale-95">
                                    <i class="fas fa-cloud-download-alt text-lg mr-2"></i> GET APP
                                </button>
                                <button @click="showTutorial = true" class="text-blue-600 dark:text-blue-400 text-sm font-bold hover:underline">
                                    How to install?
                                </button>
                            </div>

                            <div x-show="downloadState === 'downloading'" class="w-full sm:w-auto inline-flex items-center justify-center bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm px-8 py-3.5 rounded-full">
                                <i class="fas fa-spinner fa-spin text-lg mr-2"></i> Starting Download...
                            </div>

                            <div x-show="downloadState === 'done'" x-transition.opacity class="w-full bg-emerald-50 border border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 rounded-2xl p-4 text-left shadow-sm mt-2">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 text-emerald-500 dark:text-emerald-400 text-xl">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h5 class="font-bold text-emerald-800 dark:text-emerald-300 text-sm mb-1">Download Sent to File Manager!</h5>
                                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium leading-relaxed mb-3">
                                            Pull down your Android notification panel or open your "Downloads" folder to install the APK.
                                        </p>
                                        <button @click="showTutorial = true" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-colors">
                                            <i class="fas fa-book-open mr-1.5"></i> View Install Guide
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="flex justify-between sm:justify-start sm:gap-12 py-5 border-y border-slate-200/80 dark:border-slate-700/50 mb-8 bg-slate-50/50 dark:bg-slate-800/20 rounded-2xl px-4 sm:px-0 sm:bg-transparent">
                    <div class="text-center sm:pl-4">
                        <div class="text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Rating</div>
                        <div class="font-black text-xl text-slate-800 dark:text-white flex items-center justify-center gap-1">
                            <span x-text="activeApp?.rating"></span>
                            <i class="fas fa-star text-sm text-amber-500"></i>
                        </div>
                    </div>
                    <div class="w-px bg-slate-200 dark:bg-slate-700"></div>
                    <div class="text-center">
                        <div class="text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Size</div>
                        <div class="font-black text-xl text-slate-800 dark:text-white" x-text="appSizeText"></div>
                    </div>
                    <div class="w-px bg-slate-200 dark:bg-slate-700"></div>
                    <div class="text-center sm:pr-4">
                        <div class="text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Price</div>
                        <div class="font-black text-xl text-slate-800 dark:text-white">Free</div>
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-black text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-500"></i> About this app
                    </h4>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-sm whitespace-pre-line bg-white/50 dark:bg-slate-800/30 p-5 rounded-2xl border border-slate-100 dark:border-slate-700/50" x-text="activeApp?.description || 'No description provided.'"></p>
                </div>
            </div>

            <div class="px-6 sm:px-10 pb-10 overflow-y-auto hide-scrollbar" x-show="showTutorial" x-cloak>
                <div class="flex items-center gap-4 mb-8">
                    <button @click="showTutorial = false" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">How to Install</h2>
                </div>

                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 font-black text-lg">1</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-lg mb-1">Download the APK</h4>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Tap the <strong>GET APP</strong> button. The file will automatically download to your phone's File Manager.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 font-black text-lg">2</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-lg mb-1">Open the File</h4>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Pull down your notification bar and tap the downloaded <strong>.apk</strong> file, or find it in your "Downloads" app.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 font-black text-lg">3</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-lg mb-1">Allow Unknown Sources</h4>
                            <p class="text-slate-600 dark:text-slate-400 text-sm mb-3">If a security popup appears, tap <strong>Settings</strong>.</p>
                            <div class="bg-slate-100 dark:bg-slate-800 rounded-xl p-4 flex items-center gap-3 border border-slate-200 dark:border-slate-700">
                                <i class="fas fa-toggle-on text-blue-500 text-xl"></i>
                                <span class="font-medium text-sm text-slate-700 dark:text-slate-300">Turn on "Allow from this source"</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 font-black text-lg">4</div>
                        <div>
                            <h4 class="font-bold text-slate-900 dark:text-white text-lg mb-1">Install & Open</h4>
                            <p class="text-slate-600 dark:text-slate-400 text-sm">Tap the back button, then tap <strong>Install</strong>. Once finished, tap <strong>Open</strong> to launch your new app!</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10 text-center">
                    <button @click="showTutorial = false" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold text-sm px-8 py-3 rounded-full hover:bg-slate-800 dark:hover:bg-slate-200 transition-colors w-full sm:w-auto">
                        Got it, go back
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        function storeManager() {
            return {
                isDark: document.documentElement.classList.contains('dark'),
                apps: <?= json_encode($apps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                categories: <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                selectedCategory: 'All',
                searchQuery: '',
                isModalOpen: false,
                activeApp: null,
                
                // Enhanced Download & Tutorial States
                downloadState: 'idle', // 'idle' | 'downloading' | 'done'
                appSizeText: 'Varies',
                showTutorial: false,

                toggleTheme() {
                    this.isDark = !this.isDark;
                    const htmlElement = document.documentElement;
                    
                    if(this.isDark) {
                        htmlElement.classList.add('dark');
                        localStorage.setItem('store_theme', 'dark');
                        document.getElementById('theme-color-meta').setAttribute('content', '#020617');
                    } else {
                        htmlElement.classList.remove('dark');
                        localStorage.setItem('store_theme', 'light');
                        document.getElementById('theme-color-meta').setAttribute('content', '#f8fafc');
                    }
                },

                filteredApps() {
                    return this.apps.filter(app => {
                        const matchesCategory = this.selectedCategory === 'All' || app.category === this.selectedCategory;
                        const matchesSearch = app.name.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                              app.category.toLowerCase().includes(this.searchQuery.toLowerCase());
                        return matchesCategory && matchesSearch;
                    });
                },

                openAppModal(app) {
                    this.activeApp = app;
                    this.isModalOpen = true;
                    
                    // Reset States
                    this.downloadState = 'idle';
                    this.showTutorial = false;
                    
                    // Direct Real File Size Parsing
                    if (app.size && app.size.trim() !== '') {
                        this.appSizeText = app.size + ' MB';
                    } else {
                        this.appSizeText = 'Varies';
                    }
                    
                    document.body.style.overflow = 'hidden';
                },

                closeAppModal() {
                    this.isModalOpen = false;
                    setTimeout(() => { 
                        this.activeApp = null; 
                        this.downloadState = 'idle';
                        this.showTutorial = false;
                    }, 300);
                    document.body.style.overflow = '';
                },

                formatNumber(num) {
                    if (!num) return '0';
                    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
                    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
                    return num.toString();
                },

                startDownload(app) {
                    if (!app || !app.download_link) return;
                    
                    this.downloadState = 'downloading';

                    // DIRECT BROWSER NATIVE DOWNLOAD
                    // This pushes the file directly to the Android File Manager natively.
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = app.download_link;
                    a.target = '_top'; 
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // Update UI to show the post-download instruction alert
                    setTimeout(() => {
                        this.downloadState = 'done';
                    }, 1200); 
                }
            }
        }
    </script>
</body>
</html>