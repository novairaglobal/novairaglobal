<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <link rel="icon" type="image/png" href="https://novaira.infinityfreeapp.com/assets/images/logo.png">
    
    <title>404 Page Not Found | Novaira</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-glow: rgba(13, 110, 253, 0.15);
            --accent-glow: rgba(102, 16, 242, 0.15);
            --grid-color: rgba(13, 110, 253, 0.06); /* Subtle blue grid lines */
            --bg-color: #f8fafc; /* Clean off-white */
            --text-main: #0f172a; /* Slate 900 */
            --text-muted: #64748b; /* Slate 500 */
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevents scrollbars from background animation */
            color: var(--text-main);
        }

        /* Live Animated Soft Mesh Background */
        .bg-animation {
            position: fixed; 
            width: 150vw;
            height: 150vh;
            top: -25vh;
            left: -25vw;
            z-index: -2;
            background: 
                radial-gradient(circle at 30% 40%, var(--primary-glow) 0%, transparent 50%),
                radial-gradient(circle at 70% 60%, var(--accent-glow) 0%, transparent 50%);
            animation: breathe 12s ease-in-out infinite alternate;
        }

        @keyframes breathe {
            0% { transform: scale(1) rotate(0deg); }
            100% { transform: scale(1.1) rotate(5deg); }
        }

        /* Futuristic Moving Grid Overlay */
        .bg-grid {
            position: fixed;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            /* Create the grid pattern */
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 50px 50px;
            /* Fade out the grid at the edges for a professional look */
            -webkit-mask-image: radial-gradient(circle at center, black 40%, transparent 80%);
            mask-image: radial-gradient(circle at center, black 40%, transparent 80%);
            /* Smooth infinite panning animation */
            animation: panGrid 15s linear infinite;
        }

        @keyframes panGrid {
            0% { background-position: 0px 0px; }
            100% { background-position: 50px 50px; } /* Must match background-size for perfect looping */
        }

        /* Dedicated centering wrapper to fix desktop alignment */
        .page-wrapper {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            perspective: 1000px;
            position: relative;
            z-index: 1;
        }

        /* Light Frosted Glassmorphism Card */
        .card-404 {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            animation: floatUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
            transition: transform 0.1s ease-out;
            margin: auto;
        }

        @keyframes floatUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Animated Floating Logo */
        .brand-logo {
            height: 40px;
            margin-bottom: 25px;
            filter: drop-shadow(0 8px 12px rgba(13, 110, 253, 0.15));
            animation: floatLogo 4s ease-in-out infinite;
        }

        @keyframes floatLogo {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        /* Vibrant Gradient 404 Text */
        .error-code {
            font-size: 110px;
            font-weight: 800;
            letter-spacing: -4px;
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 6px rgba(13, 110, 253, 0.1));
        }

        h4 {
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .message {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 35px;
            line-height: 1.6;
        }

        /* Premium Buttons */
        .btn-custom {
            border-radius: 12px;
            padding: 14px 24px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            flex: 1;
        }

        .btn-primary-modern {
            background: #0f172a;
            color: #ffffff;
            border: none;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.15);
        }

        .btn-primary-modern:hover, .btn-primary-modern:active {
            background: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(15, 23, 42, 0.2);
            color: #ffffff;
        }

        .btn-outline-modern {
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-main);
            border: 1px solid rgba(15, 23, 42, 0.15);
        }

        .btn-outline-modern:hover, .btn-outline-modern:active {
            background: rgba(15, 23, 42, 0.04);
            border-color: rgba(15, 23, 42, 0.3);
            transform: translateY(-2px);
            color: var(--text-main);
        }

        .btn-group-custom {
            display: flex;
            gap: 12px;
            width: 100%;
        }

        /* Responsive Mobile Layout */
        @media (max-width: 576px) {
            .card-404 { 
                padding: 40px 20px; 
                border-radius: 20px;
            }
            .error-code { 
                font-size: 85px; 
            }
            .brand-logo {
                height: 35px;
            }
            h4 {
                font-size: 1.1rem;
            }
            .message {
                font-size: 0.9rem;
                margin-bottom: 25px;
            }
            .btn-group-custom { 
                flex-direction: column-reverse; 
            }
            .btn-custom { 
                width: 100%; 
            }
        }
    </style>
</head>
<body>

    <div class="bg-animation"></div>
    <div class="bg-grid"></div>

    <div class="page-wrapper">
        <div class="card-404" id="glass-card">
            <img src="https://novaira.infinityfreeapp.com/assets/images/logo.png" alt="Novaira Logo" class="brand-logo">

            <div class="error-code">404</div>
            <h4>Page Not Found</h4>
            
            <p class="message">
                The page you are looking for has been moved, removed, or does not exist. Let's get you back on track.
            </p>

            <div class="btn-group-custom">
                <button onclick="goBack()" class="btn btn-outline-modern btn-custom">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Go Back
                </button>
                <a href="/" class="btn btn-primary-modern btn-custom">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Home Page
                </a>
            </div>
        </div>
    </div>

    <script>
        // Back Button Logic
        function goBack() {
            if (document.referrer !== "") {
                window.history.back();
            } else {
                window.location.href = "/";
            }
        }

        // Live 3D Mouse Parallax - ONLY active on Desktop (devices that support hover)
        if (window.matchMedia("(hover: hover)").matches) {
            const card = document.getElementById('glass-card');

            document.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 50;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 50;
                card.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            document.addEventListener('mouseleave', () => {
                card.style.transform = `rotateY(0deg) rotateX(0deg)`;
                card.style.transition = `transform 0.5s ease`;
            });
            
            document.addEventListener('mouseenter', () => {
                card.style.transition = `transform 0.1s ease-out`;
            });
        }
    </script>

</body>
</html>