<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jeyanco | Register Identity</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@300;500;700&display=swap');

        body {
            margin: 0;
            background-image: linear-gradient(135deg, rgba(10, 15, 30, 0.92) 0%, rgba(5, 10, 20, 0.85) 100%), 
                              url('https://images.unsplash.com/photo-1541888946425-d81bb19240f5?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Rajdhani', sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        #bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.6;
        }

        .glass-card {
            background: rgba(8, 12, 25, 0.7);
            backdrop-filter: blur(25px) saturate(200%);
            -webkit-backdrop-filter: blur(25px) saturate(200%);
            border: 1px solid rgba(0, 212, 255, 0.25);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            position: relative;
            overflow: hidden;
        }

        .neon-text {
            font-family: 'Orbitron', sans-serif;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.9);
            letter-spacing: 4px;
        }

        .input-glow:focus {
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.2);
            border-color: rgba(0, 212, 255, 0.6);
            background: rgba(0, 0, 0, 0.6);
        }

        .btn-scan {
            background: linear-gradient(45deg, #00d4ff, #0077ff);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-scan:hover {
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.7);
            transform: translateY(-2px);
        }

        .logo-container {
            filter: drop-shadow(0 0 20px rgba(0, 212, 255, 0.6));
            animation: pulse 4s infinite ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.03); opacity: 1; }
        }
    </style>
</head>

<body class="flex items-center justify-center">

    <canvas id="bg-canvas"></canvas>

    <div class="glass-card p-10 rounded-[2rem] w-full max-w-md mx-4 text-white z-10 border-t-2 border-blue-500/40">
        
        <div class="flex flex-col items-center mb-8">
            <div class="logo-container w-24 h-24 mb-4 rounded-full border border-blue-400/30 bg-black/60 overflow-hidden flex items-center justify-center p-1">
                <img src="/images/JeyancoLogo.png"
                     alt="Jeyanco Logo"
                     class="w-full h-full object-contain scale-110">
            </div>

            <h1 class="neon-text text-xl font-bold uppercase text-center">Identity Registry</h1>
            <div class="h-[2px] w-32 bg-blue-500 mt-2 shadow-[0_0_10px_#00d4ff]"></div>
            <p class="text-[10px] uppercase text-blue-200 tracking-[5px] mt-2 opacity-70">Mainframe Authorization</p>
        </div>

        <form action="{{ route('register.post') }}" method="POST" class="space-y-6">
            @csrf
            
            @if($errors->any())
                <div class="bg-red-900/50 border border-red-500 text-red-200 text-[10px] p-3 rounded-xl animate-pulse font-medium tracking-widest text-center">
                    SYSTEM ALERT: {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="text-[10px] uppercase tracking-[3px] text-blue-400 ml-1 font-medium">Create Admin ID</label>
                <input type="text" name="username" value="{{ old('username') }}" required
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-5 py-4 mt-1 outline-none input-glow transition-all font-mono text-blue-100 placeholder:text-blue-900/50"
                    placeholder="ASSIGN USERNAME">
            </div>

            <div>
                <label class="text-[10px] uppercase tracking-[3px] text-blue-400 ml-1 font-medium">New Access Key</label>
                <input type="password" name="password" required
                    class="w-full bg-black/50 border border-white/10 rounded-xl px-5 py-4 mt-1 outline-none input-glow transition-all font-mono text-blue-100 placeholder:text-blue-900/50"
                    placeholder="••••••••••">
            </div>

            <button type="submit" 
                class="btn-scan w-full text-white font-bold py-4 rounded-xl tracking-[4px] uppercase text-sm mt-4">
                Initialize Protocols
            </button>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="text-[9px] uppercase tracking-[2px] text-blue-400 hover:text-white transition-colors opacity-60">
                    Return to Login Portal
                </a>
            </div>
        </form>

        <div class="mt-12 flex justify-between items-center opacity-40 text-[9px] uppercase tracking-widest font-mono">
            <span>Status: Ready</span>
            <span>Jeyanco Security v2.0</span>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('bg-canvas');
        const ctx = canvas.getContext('2d');
        let particles = [];

        function init() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 1.5 + 0.3;
                this.speedX = Math.random() * 0.3 - 0.15;
                this.speedY = Math.random() * 0.3 - 0.15;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x > canvas.width) this.x = 0;
                if (this.x < 0) this.x = canvas.width;
                if (this.y > canvas.height) this.y = 0;
                if (this.y < 0) this.y = canvas.height;
            }

            draw() {
                ctx.fillStyle = 'rgba(0, 212, 255, 0.5)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function createParticles() {
            particles = [];
            for (let i = 0; i < 70; i++) {
                particles.push(new Particle());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => { p.update(); p.draw(); });
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', () => {
            init();
            createParticles();
        });

        init();
        createParticles();
        animate();
    </script>
</body>
</html>