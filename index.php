<?php
session_start();
require_once 'includes/auth.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirectToDashboard($_SESSION['user_type']);
} elseif (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill2Startup - Connect Skills with Innovation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Enhanced animations for homepage */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -2;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Header styles */
        header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: logoGlow 3s ease-in-out infinite alternate;
        }

        @keyframes logoGlow {
            from { filter: drop-shadow(0 0 5px rgba(102, 126, 234, 0.3)); }
            to { filter: drop-shadow(0 0 15px rgba(118, 75, 162, 0.6)); }
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        nav a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.3s ease;
            z-index: -1;
        }

        nav a:hover::before {
            left: 0;
        }

        nav a:hover {
            color: white;
            transform: translateY(-2px);
        }

        header.scrolled nav a {
            color: #333;
        }

        /* Hero section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            padding-top: 80px;
        }

        .hero-content {
            animation: heroSlideIn 1s ease-out;
        }

        @keyframes heroSlideIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: textPulse 4s ease-in-out infinite;
        }

        @keyframes textPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .hero p {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.3);
        }

        /* Section styles */
        section {
            padding: 5rem 0;
            position: relative;
        }

        .section-bg {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin: 2rem 0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleFloat 3s ease-in-out infinite;
        }

        @keyframes titleFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Features grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 20px;
            text-align: center;
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            animation: cardSlideUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes cardSlideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.2);
            background: rgba(255, 255, 255, 1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
            animation: iconBounce 2s ease-in-out infinite;
        }

        @keyframes iconBounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Statistics section */
        .stats-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            margin: 3rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            transition: all 0.3s ease;
            animation: statCounter 2s ease-out;
        }

        .stat-card:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 1);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #666;
            font-weight: 500;
        }

        /* Process section */
        .process-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 4rem 2rem;
            margin: 3rem 0;
        }

        .process-timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .process-step {
            display: flex;
            align-items: center;
            margin-bottom: 3rem;
            animation: stepSlide 0.8s ease-out;
            animation-fill-mode: both;
        }

        .process-step:nth-child(1) { animation-delay: 0.2s; }
        .process-step:nth-child(2) { animation-delay: 0.4s; }
        .process-step:nth-child(3) { animation-delay: 0.6s; }
        .process-step:nth-child(4) { animation-delay: 0.8s; }

        @keyframes stepSlide {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .step-number {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-right: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 10px 40px rgba(102, 126, 234, 0.5); }
            100% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); }
        }

        .step-content h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .step-content p {
            color: #666;
            line-height: 1.6;
        }

        /* CTA section */
        .cta-section {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            backdrop-filter: blur(20px);
            border-radius: 20px;
            margin: 3rem 0;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }

        /* Footer */
        footer {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        footer p {
            color: #666;
            font-weight: 500;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .process-step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            nav ul {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Scroll animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease-out;
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Button hover effects */
        .btn-glow {
            position: relative;
            overflow: hidden;
        }

        .btn-glow::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: width 0.6s, height 0.6s;
            transform: translate(-50%, -50%);
        }

        .btn-glow:hover::after {
            width: 300px;
            height: 300px;
        }
    </style>
</head>
<body>
    <!-- Floating particles background -->
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <header id="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>Skill2Startup</h1>
                </div>
                <nav>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#process">How It Works</a></li>
                        <li><a href="login.php" class="btn-glow">Login</a></li>
                        <li><a href="admin/login.php" class="btn-glow">Admin</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Connect Skills with Innovation</h1>
                    <p>Empowering students to showcase their talents while helping startups discover the perfect team members. Join the revolution where skills meet opportunity!</p>
                    <div class="hero-buttons">
                        <a href="student/register.php" class="btn btn-primary btn-glow">🎓 Join as Student</a>
                        <a href="startup/register.php" class="btn btn-primary btn-glow">🚀 Register Startup</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features">
            <div class="container">
                <div class="section-bg">
                    <h2 class="section-title">Why Choose Skill2Startup?</h2>
                    <div class="features-grid">
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">🎓</div>
                            <h3>For Students</h3>
                            <p>Showcase your skills, build an impressive portfolio, find internships, and gain real-world experience with innovative startups across various industries.</p>
                        </div>
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">🚀</div>
                            <h3>For Startups</h3>
                            <p>Access a curated pool of talented students, find the right skills for your projects, build your dream team, and scale your startup efficiently.</p>
                        </div>
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">🔒</div>
                            <h3>Verified Platform</h3>
                            <p>All startups undergo thorough verification by our admin team to ensure authenticity, quality opportunities, and a secure environment for collaboration.</p>
                        </div>
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">⚡</div>
                            <h3>Smart Matching</h3>
                            <p>Our intelligent algorithm matches students with startups based on skills, interests, project requirements, and career goals for optimal partnerships.</p>
                        </div>
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">📧</div>
                            <h3>Instant Notifications</h3>
                            <p>Stay updated with real-time email notifications for application status, new opportunities, project updates, and important platform announcements.</p>
                        </div>
                        <div class="feature-card animate-on-scroll">
                            <div class="feature-icon">💼</div>
                            <h3>Career Growth</h3>
                            <p>Build valuable connections, gain industry experience, develop professional skills, and kickstart your career in the dynamic startup ecosystem.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistics Section -->
        <section>
            <div class="container">
                <div class="stats-section animate-on-scroll">
                    <h2 class="section-title">Platform Impact</h2>
                    <p style="text-align: center; font-size: 1.2rem; color: #666; margin-bottom: 2rem;">
                        Connecting talent with innovation across the globe
                    </p>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number" data-count="850">0</div>
                            <div class="stat-label">Active Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" data-count="150">0</div>
                            <div class="stat-label">Verified Startups</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" data-count="420">0</div>
                            <div class="stat-label">Successful Matches</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" data-count="95">0</div>
                            <div class="stat-label">Success Rate %</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about">
            <div class="container">
                <div class="section-bg animate-on-scroll">
                    <h2 class="section-title">About Skill2Startup</h2>
                    <div style="max-width: 800px; margin: 0 auto; text-align: center;">
                        <p style="font-size: 1.2rem; line-height: 1.8; color: #555; margin-bottom: 2rem;">
                            Skill2Startup is a revolutionary platform designed to bridge the gap between talented students and innovative startups. 
                            We believe that every student has unique skills that can contribute to the startup ecosystem, and every startup 
                            deserves access to fresh talent and perspectives.
                        </p>
                        <p style="font-size: 1.1rem; line-height: 1.7; color: #666;">
                            Our platform features comprehensive user management with secure authentication, real-time email notifications for application updates, 
                            an intuitive admin dashboard for startup verification, and a sophisticated matching system that connects the right talent 
                            with the right opportunities. Join us in shaping the future of work and innovation!
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="process">
            <div class="container">
                <div class="process-section">
                    <h2 class="section-title">How It Works</h2>
                    <div class="process-timeline">
                        <div class="process-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h3>Register & Verify</h3>
                                <p>Create your account as a student or startup with email verification. Students get instant access, while startups undergo admin verification for platform security.</p>
                            </div>
                        </div>
                        <div class="process-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h3>Build Your Profile</h3>
                                <p>Students showcase their skills, projects, and interests. Startups detail their company, project requirements, and team needs for better matching.</p>
                            </div>
                        </div>
                        <div class="process-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h3>Smart Matching</h3>
                                <p>Our intelligent system analyzes profiles and requirements to suggest optimal matches. Browse opportunities and submit applications with ease.</p>
                            </div>
                        </div>
                        <div class="process-step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h3>Connect & Collaborate</h3>
                                <p>Receive instant email notifications about application status. Start collaborating on exciting projects and build amazing products together!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action -->
        <section>
            <div class="container">
                <div class="cta-section animate-on-scroll">
                    <h2 class="cta-title">Ready to Get Started?</h2>
                    <p class="cta-subtitle">Join thousands of students and startups already building the future together</p>
                    <div class="hero-buttons">
                        <a href="student/register.php" class="btn btn-primary btn-glow">Start as Student</a>
                        <a href="startup/register.php" class="btn btn-primary btn-glow">Launch Your Startup</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2025 Skill2Startup. All rights reserved. | Connecting talent with innovation worldwide.</p>
        </div>
    </footer>

    <script>
        // Create floating particles
        function createParticles() {
            const particleContainer = document.getElementById('particles');
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random size and position
                const size = Math.random() * 6 + 3;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 15 + 20) + 's';
                
                particleContainer.appendChild(particle);
            }
        }

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                }
            });
        }, observerOptions);

        // Observe all elements with animate-on-scroll class
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Animated counter for statistics
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current) + (target >= 100 && element.textContent.includes('%') ? '' : '');
            }, 20);
        }

        // Trigger counters when stats section is visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach(stat => {
                        const target = parseInt(stat.getAttribute('data-count'));
                        animateCounter(stat, target);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Initialize particles when page loads
        window.addEventListener('load', () => {
            createParticles();
        });

        // Add parallax effect to hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Add loading animation
        window.addEventListener('load', () => {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>
