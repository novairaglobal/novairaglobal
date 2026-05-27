<?php
// Optional: If you want to automatically redirect users back to the home page 
// the exact moment you turn maintenance off, you can include your db.php here.
// However, the JavaScript auto-refresh (at the bottom) is usually safer and sufficient.
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Retry-After: 3600'); // Tells search engines the site will be back in ~1 hour
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <meta name="theme-color" content="#f9fafb">
    
    <title>System Maintenance | Novaira Global Marketing</title>
    <meta name="description" content="Novaira Global Marketing is currently undergoing scheduled maintenance. We will be back shortly.">
    <meta name="robots" content="noindex, nofollow">

    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#4F46E5', // Indigo-600
                        secondary: '#1E293B', // Slate-800
                        accent: '#F3F4F6'
                    }
                }
            }
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Subtle pulse for the background blobs */
        @keyframes slow-pulse {
            0%, 100% { opacity: 0.4; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
        .animate-slow-pulse {
            animation: slow-pulse 8s ease-in-out infinite;
        }

        /* Smooth infinite progress bar reflection */
        @keyframes progress-sweep {
            0% { transform: translateX(-100%) skewX(-20deg); }
            100% { transform: translateX(300%) skewX(-20deg); }
        }
        .animate-progress-sweep {
            animation: progress-sweep 2s infinite linear;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex items-center justify-center overflow-hidden relative">

    <div class="absolute -top-10 -right-10 sm:top-[-10%] sm:right-[-5%] w-64 h-64 sm:w-96 sm:h-96 bg-indigo-200 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-50 animate-slow-pulse z-0"></div>
    <div class="absolute -bottom-10 -left-10 sm:bottom-[-10%] sm:left-[-5%] w-64 h-64 sm:w-96 sm:h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-[60px] sm:blur-[80px] opacity-50 animate-slow-pulse z-0" style="animation-delay: 4s;"></div>

    <div class="w-full max-w-lg px-4 sm:px-6 relative z-10 flex flex-col items-center">
        
        <div class="flex items-center justify-center gap-3 mb-6 sm:mb-8 w-full text-center">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-primary text-white rounded-xl flex items-center justify-center font-bold text-xl sm:text-2xl shadow-lg shrink-0">N</div>
            <span class="font-bold text-2xl sm:text-3xl tracking-tight text-secondary">Novaira</span>
        </div>

        <div class="bg-white/80 backdrop-blur-xl border border-white/40 p-6 sm:p-10 rounded-[2rem] shadow-2xl w-full h-fit flex flex-col text-center">
            
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-indigo-50 text-primary rounded-full flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-5 shadow-inner relative shrink-0">
                <i class="fas fa-cog animate-spin" style="animation-duration: 4s;"></i>
                <div class="absolute -bottom-0.5 -right-0.5 sm:-bottom-1 sm:-right-1 w-5 h-5 sm:w-6 sm:h-6 bg-green-500 rounded-full border-[3px] sm:border-4 border-white"></div>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-secondary mb-3">System Maintenance</h1>
            <p class="text-gray-500 text-sm sm:text-[1.05rem] leading-relaxed mb-6">
                We are currently upgrading our infrastructure to provide you with a faster, more robust marketing dashboard. We'll be back online shortly.
            </p>

            <div class="w-full bg-gray-100 rounded-full h-2 sm:h-2.5 mb-6 overflow-hidden relative">
                <div class="bg-primary h-full rounded-full w-full absolute top-0 left-0 overflow-hidden">
                    <div class="absolute top-0 bottom-0 left-0 w-1/2 bg-white/30 animate-progress-sweep"></div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 sm:p-5 text-xs sm:text-sm text-gray-600 text-left w-full">
                <p class="font-bold text-secondary mb-1.5 flex items-center gap-2">
                    <i class="fas fa-headset text-primary"></i> Need urgent assistance?
                </p>
                <p class="leading-relaxed mb-3 text-gray-500">If you have an ongoing campaign that requires immediate attention, please reach out to our emergency support line.</p>
                <a href="mailto:novaira.global@gmail.com" class="inline-block text-primary font-semibold hover:underline bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 transition-colors break-all">
                    novaira.global@gmail.com
                </a>
            </div>
        </div>

        <p class="text-gray-400 text-xs sm:text-sm mt-6 sm:mt-8 font-medium text-center">
            This page will automatically refresh to check if the system is back online.
        </p>

    </div>

    <script>
        // Check if the site is back online every 60 seconds (60000 milliseconds)
        setTimeout(function() {
            // By reloading the page, if you have turned maintenance_mode to '0' 
            // in the database, the user will naturally be able to access the home page again.
            window.location.href = "/";
        }, 60000);
    </script>

</body>
</html>