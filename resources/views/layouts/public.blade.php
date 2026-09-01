<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Social Scheduler')</title>
    <meta name="description" content="@yield('description', 'Plan, create and schedule your social media content from one powerful platform.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #172033;
            background: #ffffff;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            width: min(1180px, calc(100% - 40px));
            margin: auto;
        }

        /* Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255,255,255,.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #edf0f5;
        }

        .nav {
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
            color: #171c2d;
			display:flex;
        }

        .brand span {
            color: #635bff;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a {
            color: #667085;
            font-size: 14px;
            font-weight: 500;
            transition: .2s;
        }

        .nav-links a:hover {
            color: #635bff;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            transition: .2s;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: #2e68bc;
            color: #fff;
            box-shadow: 0 5px 15px rgba(99,91,255,.2);
        }

        .btn-primary:hover {
            background: #10428a;
            transform: translateY(-1px);
        }

        .btn-outline {
            border-color: #dfe3eb;
            color: #344054;
            background: #fff;
        }

        .btn-outline:hover {
            border-color: #635bff;
            color: #635bff;
        }

        .mobile-toggle {
            display: none;
            border: 0;
            background: transparent;
            font-size: 24px;
            cursor: pointer;
        }

        /* Footer */
        .site-footer {
            border-top: 1px solid #edf0f5;
            background: #fafbfc;
            padding: 55px 0 25px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 60px;
        }

        .footer-description {
            margin-top: 14px;
            max-width: 380px;
            color: #667085;
            font-size: 14px;
        }

        .footer-title {
            font-weight: 700;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-links a {
            font-size: 14px;
            color: #667085;
        }

        .footer-links a:hover {
            color: #635bff;
        }

        .footer-bottom {
            border-top: 1px solid #e9ecf1;
            margin-top: 45px;
            padding-top: 22px;
            color: #98a2b3;
            font-size: 13px;
        }

        @media (max-width: 800px) {
            .nav-links,
            .nav-actions {
                display: none;
            }

            .mobile-toggle {
                display: block;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>

    @stack('styles')
</head>

<body>

<header class="site-header">
    <div class="container nav">

        <a href="{{ route('home') }}" class="brand">
            <img src="../public/images/logo_header.png" alt="SocialScheduler">
        </a>

        <nav class="nav-links">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('home') }}#features">Features</a>
            <a href="{{ route('home') }}#how-it-works">How It Works</a>
        </nav>

        <div class="nav-actions">
            <!-- <a href="{{ route('login') }}" class="btn btn-outline">Sign In</a> -->
            <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
        </div>

        <button class="mobile-toggle">☰</button>

    </div>
</header>

@yield('content')

<footer class="site-footer">
    <div class="container">

        <div class="footer-grid">

            <div>
                <a href="{{ route('home') }}" class="brand">
                   
             <img src="../public/images/logo_header.png" alt="SocialScheduler">
                </a>

                <p class="footer-description">
                    Plan, create and schedule your social media content
                    from one simple and powerful platform.
                </p>
            </div>

            <div>
                <div class="footer-title">Product</div>

                <div class="footer-links">
                    <a href="{{ route('home') }}#features">Features</a>
                    <a href="{{ route('home') }}#how-it-works">How It Works</a>
                    <a href="{{ route('login') }}">Sign In</a>
                </div>
            </div>

            <div>
                <div class="footer-title">Legal</div>

                <div class="footer-links">
                    <a href="{{ route('terms') }}">Terms & Conditions</a>
                    <a href="{{ route('privacy') }}">Privacy Policy</a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            © {{ date('Y') }} Social Scheduler. All rights reserved.
        </div>

    </div>
</footer>

@stack('scripts')

</body>
</html>