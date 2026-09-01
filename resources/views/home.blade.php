@extends('layouts.public')

@section('title', 'Social Scheduler - Plan, Create & Schedule Social Media Content')

@section('description', 'Plan, create and schedule your social media content from one powerful platform.')

@push('styles')
<style>
    .hero {
        padding: 100px 0 90px;
        background: linear-gradient(180deg, #f8f9ff 0%, #ffffff 100%);
    }

    .hero-grid {
        display: grid;
        grid-template-columns: 1.05fr .95fr;
        gap: 70px;
        align-items: center;
    }

    .hero-badge {
        display: inline-flex;
        padding: 7px 13px;
        border-radius: 30px;
        background: #2e68bc;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .hero h1 {
        font-size: clamp(42px, 5vw, 66px);
        line-height: 1.08;
        letter-spacing: -2.5px;
        color: #101828;
        margin-bottom: 24px;
    }

    .hero h1 span {
        color: #2e68bc;
    }

    .hero-text {
        font-size: 18px;
        color: #667085;
        max-width: 590px;
        margin-bottom: 32px;
    }

    .hero-buttons {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }

    .dashboard-preview {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 15px;
        box-shadow: 0 25px 70px rgba(16,24,40,.12);
        transform: rotate(1deg);
    }

    .preview-window {
        border-radius: 12px;
        background: #f7f8fc;
        overflow: hidden;
    }

    .preview-top {
        height: 42px;
        background: #fff;
        border-bottom: 1px solid #e8eaf0;
        display: flex;
        align-items: center;
        padding: 0 15px;
        gap: 6px;
    }

    .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #d0d5dd;
    }

    .preview-content {
        padding: 22px;
    }

    .preview-title {
        height: 16px;
        width: 180px;
        background: #dfe3f0;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 7px;
    }

    .calendar-cell {
        height: 65px;
        border-radius: 7px;
        background: #fff;
        border: 1px solid #eaecf0;
		overflow:hidden;
    }

    .calendar-cell.active {
        background: #eeecff;
        border-color: #d8d4ff;
    }
	.calendar-cell.active span{    padding: 10px;
    display: flex;
    width: auto;
    height: 100%;
    align-items: center;
    justify-content: center;}
	.calendar-cell.active span svg path{fill:#fff !important;}
	
	{}
	.ChannelsSection_tile__aOdcn[data-channel=linkedin]{background:#2967b3;}
	.ChannelsSection_tile__aOdcn[data-channel=threads]{background:#000000;}
	.ChannelsSection_tile__aOdcn[data-channel=tiktok]{background:#000000;}
	.ChannelsSection_tile__aOdcn[data-channel=bluesky]{background:#2967b3;}
	.ChannelsSection_tile__aOdcn[data-channel=youtube]{background:#ff0000;}
	.ChannelsSection_tile__aOdcn[data-channel=instagram]{background:#ed0274;}
	.ChannelsSection_tile__aOdcn[data-channel=pinterest]{background:#e60022;}
	.ChannelsSection_tile__aOdcn[data-channel=facebook]{background:#1083fe;}
	.ChannelsSection_tile__aOdcn[data-channel=x]{background:#000000;}

    /* Sections */
    .section {
        padding: 90px 0;
    }
	.img-box img{width:100%; max-width:650px; border-radius:10px;}

    .section-heading {
        text-align: center;
        max-width: 650px;
        margin: 0 auto 50px;
    }

    .section-heading h2 {
        font-size: 38px;
        letter-spacing: -1.2px;
        color: #101828;
        margin-bottom: 14px;
    }

    .section-heading p {
        color: #667085;
        font-size: 16px;
    }

    .features {
        background: #fff;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .feature-card {
        padding: 30px;
        border: 1px solid #eaecf0;
        border-radius: 15px;
        background: #fff;
        transition: .25s;
    }

    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(16,24,40,.08);
        border-color: #d9d5ff;
    }

    .feature-icon {
        width: 46px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: #eeecff;
        color: #2e68bc;
        font-size: 21px;
        margin-bottom: 20px;
    }

    .feature-card h3 {
        font-size: 17px;
        margin-bottom: 9px;
    }

    .feature-card p {
        color: #667085;
        font-size: 14px;
    }

    .how {
        background: #f8f9fc;
    }

    .steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }

    .step {
        text-align: center;
        position: relative;
    }

    .step-number {
        width: 55px;
        height: 55px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: #2e68bc;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }

    .step h3 {
        margin-bottom: 8px;
    }

    .step p {
        color: #667085;
        font-size: 14px;
    }

    .benefits {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
    }

    .benefits h2 {
        font-size: 40px;
        line-height: 1.15;
        margin-bottom: 20px;
    }

    .benefits-text {
        color: #667085;
        margin-bottom: 25px;
    }

    .benefit-list {
        display: grid;
        gap: 13px;
    }

    .benefit-item {
        display: flex;
        gap: 12px;
        align-items: center;
        font-weight: 600;
        font-size: 14px;
    }

    .check {
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eeecff;
        color: #2e68bc;
        border-radius: 50%;
        font-size: 13px;
    }

    .cta {
        padding: 80px 0;
    }

    .cta-box {
        border-radius: 22px;
        background: #171c2d;
        color: #fff;
        padding: 70px 40px;
        text-align: center;
    }

    .cta-box h2 {
        font-size: 38px;
        margin-bottom: 12px;
    }

    .cta-box p {
        color: #b9c0d0;
        margin-bottom: 28px;
    }

    @media (max-width: 900px) {
        .hero-grid,
        .benefits {
            grid-template-columns: 1fr;
        }

        .feature-grid,
        .steps {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 600px) {
        .hero {
            padding: 65px 0;
        }

        .feature-grid,
        .steps {
            grid-template-columns: 1fr;
        }

        .section {
            padding: 65px 0;
        }

        .section-heading h2,
        .benefits h2,
        .cta-box h2 {
            font-size: 30px;
        }

        .dashboard-preview {
            transform: none;
        }
    }
</style>
@endpush

@section('content')

<section class="hero">
    <div class="container hero-grid">

        <div>
            <div class="hero-badge">
                SOCIAL MEDIA MANAGEMENT, SIMPLIFIED
            </div>

            <h1>
                Plan, Create & <span>Schedule</span> Your Social Media Content
            </h1>

            <p class="hero-text">
                Manage your social media content from one powerful platform.
                Create posts, schedule content, import posts in bulk and keep
                your social channels organized from a single dashboard.
            </p>

            <div class="hero-buttons">
                <a href="{{ route('login') }}" class="btn btn-primary">
                    Get Started
                </a>

                <a href="#features" class="btn btn-outline">
                    Explore Features
                </a>
            </div>
        </div>

   <div class="dashboard-preview">
            <div class="preview-window">

                <div class="preview-top">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>

                <div class="preview-content">

                    <div class="preview-title"></div>

                    <div class="calendar">
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="instagram"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M20.387 3.653C19.34 2.565 17.847 2 16.153 2H7.847C4.339 2 2 4.339 2 7.847v8.266c0 1.734.565 3.226 1.694 4.314C4.782 21.476 6.234 22 7.887 22h8.226c1.734 0 3.185-.564 4.234-1.573C21.435 19.38 22 17.887 22 16.153V7.847c0-1.694-.564-3.145-1.613-4.194Zm-.161 12.5c0 1.25-.444 2.258-1.17 2.944-.725.685-1.733 1.048-2.943 1.048H7.887c-1.21 0-2.218-.363-2.943-1.048-.726-.726-1.09-1.734-1.09-2.984V7.847c0-1.21.364-2.218 1.09-2.944.685-.685 1.733-1.048 2.943-1.048h8.306c1.21 0 2.218.363 2.944 1.089.686.725 1.089 1.733 1.089 2.903v8.306Zm-1.694-9.476a1.17 1.17 0 1 1-2.339 0 1.17 1.17 0 0 1 2.339 0ZM6.838 11.96c0-2.863 2.34-5.162 5.162-5.162s5.161 2.34 5.161 5.162S14.863 17.12 12 17.12a5.146 5.146 0 0 1-5.162-5.161Zm1.855 0A3.321 3.321 0 0 0 12 15.266a3.321 3.321 0 0 0 3.306-3.306A3.321 3.321 0 0 0 12 8.653a3.321 3.321 0 0 0-3.307 3.307Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="pinterest"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M12 1C5.925 1 1 5.925 1 12c0 4.66 2.9 8.644 6.991 10.247-.096-.87-.183-2.21.039-3.16.2-.858 1.29-5.467 1.29-5.467s-.33-.659-.33-1.633c0-1.53.887-2.672 1.99-2.672.94 0 1.392.705 1.392 1.55 0 .944-.6 2.355-.91 3.662-.26 1.095.549 1.988 1.628 1.988 1.955 0 3.458-2.061 3.458-5.037 0-2.634-1.892-4.475-4.594-4.475-3.13 0-4.967 2.347-4.967 4.774 0 .945.364 1.959.818 2.51a.33.33 0 0 1 .077.315c-.084.348-.27 1.095-.306 1.248-.048.201-.16.244-.368.147-1.374-.64-2.233-2.648-2.233-4.262 0-3.47 2.521-6.656 7.268-6.656 3.816 0 6.782 2.719 6.782 6.353 0 3.791-2.39 6.842-5.708 6.842-1.115 0-2.163-.58-2.521-1.263 0 0-.552 2.1-.686 2.615-.248.955-.919 2.153-1.367 2.883 1.03.319 2.123.491 3.257.491 6.075 0 11-4.925 11-11S18.075 1 12 1Z"></path></svg></span></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="linkedin"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M18.338 18.338H15.67v-4.177c0-.997-.018-2.278-1.387-2.278-1.39 0-1.602 1.085-1.602 2.206v4.25h-2.668v-8.59h2.561v1.173h.036c.356-.675 1.227-1.388 2.526-1.388 2.703 0 3.202 1.78 3.202 4.092v4.712ZM7.004 8.574a1.548 1.548 0 1 1 0-3.097 1.548 1.548 0 0 1 0 3.097ZM5.67 18.338h2.67v-8.59h-2.67v8.59ZM19.668 3H4.328C3.597 3 3 3.581 3 4.297v15.404C3 20.418 3.596 21 4.329 21h15.339c.734 0 1.332-.582 1.332-1.299V4.297C21 3.581 20.402 3 19.668 3Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
													
													<div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="x"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.903 10.469 21.348 2h-1.764l-6.465 7.353L7.955 2H2l7.808 11.12L2 22h1.764l6.828-7.765L16.044 22H22l-8.097-11.531Zm-2.417 2.748-.791-1.107L4.4 3.3h2.71l5.08 7.11.791 1.107 6.604 9.242h-2.71l-5.389-7.542Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="threads"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.628 1.256c-.72.033-1.37.106-1.987.222-2.53.481-4.495 1.715-5.805 3.648-.097.143-.179.272-.306.481-.062.1-.29.529-.364.685a11.365 11.365 0 0 0-.852 2.57c-.18.869-.272 1.683-.307 2.701a16.084 16.084 0 0 0 .24 3.23c.305 1.667.869 3.067 1.707 4.245 1.019 1.427 2.402 2.454 4.126 3.062 1.062.374 2.235.584 3.585.64.485.02 1.004.01 1.574-.028 2.455-.168 4.3-.903 5.84-2.325.889-.82 1.479-1.757 1.766-2.805a5.35 5.35 0 0 0 .173-.964 7.545 7.545 0 0 0 0-.907c-.112-1.177-.521-2.134-1.273-2.973-.16-.18-.519-.51-.714-.66a7.256 7.256 0 0 0-1.632-.938l-.134-.055-.014-.174c-.098-1.194-.442-2.172-1.039-2.946-.814-1.056-2.1-1.642-3.737-1.701-1.308-.048-2.457.254-3.386.89a4.61 4.61 0 0 0-.545.443 4.266 4.266 0 0 0-.548.611l-.05.07.282.178.802.508.552.349.03.019.102-.126c.557-.689 1.416-1.05 2.494-1.05.79 0 1.437.168 1.913.497.516.358.852.917 1.026 1.708.017.076.03.145.03.152 0 .01-.022.01-.093-.002-.822-.13-2.057-.171-2.983-.097-1.08.085-2.015.367-2.769.838a3.876 3.876 0 0 0-.973.862 3.13 3.13 0 0 0-.64 1.508c-.03.2-.03.698.001.902.145.98.671 1.774 1.554 2.35.852.554 2.043.839 3.22.77 1.845-.11 3.18-.885 3.986-2.314.31-.55.566-1.323.673-2.039a.785.785 0 0 1 .022-.115c.006-.009.046.009.135.063.267.161.472.313.69.516.591.547.909 1.189 1.007 2.029.022.182.018.598-.007.79-.12.94-.563 1.754-1.341 2.47-1.072.986-2.344 1.534-4.002 1.723-1.258.145-2.564.099-3.692-.128-2.92-.587-4.773-2.367-5.55-5.331-.419-1.589-.514-3.502-.266-5.282.35-2.516 1.365-4.386 2.99-5.513C8.19 3.785 9.478 3.35 11.035 3.2a11.997 11.997 0 0 1 2.878.072c2.112.315 3.742 1.204 4.854 2.649.511.664.939 1.505 1.227 2.41.032.101.063.183.07.183.015 0 1.925-.47 1.936-.477.016-.01-.17-.57-.292-.884-.928-2.375-2.584-4.097-4.853-5.047-1.136-.475-2.398-.747-3.905-.84a21.664 21.664 0 0 0-1.32-.01Zm2.362 11.049c.399.032.81.085 1.114.142l.12.021c.008 0-.008.182-.036.381-.098.718-.293 1.31-.579 1.754-.472.735-1.194 1.098-2.276 1.146-.84.037-1.607-.17-2.092-.567-.684-.559-.722-1.502-.086-2.117.188-.182.374-.304.655-.43.435-.194.985-.308 1.666-.346.28-.015 1.253-.006 1.514.016Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
                                                   
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="tiktok"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.1 1c.347 3.122 2.01 4.983 4.9 5.181v3.511c-1.675.172-3.142-.402-4.849-1.485v6.568c0 8.342-8.677 10.95-12.166 4.97-2.242-3.848-.87-10.6 6.322-10.87v3.702c-.548.092-1.133.237-1.668.429-1.6.567-2.507 1.63-2.255 3.505.485 3.59 6.77 4.653 6.247-2.363V1.007h3.47V1Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="bluesky"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5.769 4.212C8.29 5.972 11.004 9.539 12 11.453c.996-1.914 3.709-5.482 6.231-7.241C20.051 2.942 23 1.96 23 5.086c0 .624-.385 5.244-.611 5.994-.785 2.608-3.647 3.273-6.192 2.87 4.449.704 5.58 3.035 3.136 5.366-4.642 4.426-6.672-1.111-7.192-2.53-.096-.26-.14-.382-.141-.278 0-.104-.045.018-.14.278-.52 1.419-2.55 6.956-7.193 2.53-2.445-2.331-1.313-4.662 3.136-5.366-2.545.403-5.407-.262-6.192-2.87C1.385 10.33 1 5.71 1 5.086c0-3.126 2.949-2.144 4.769-.874Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="youtube"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23.76 7.148s-.234-1.68-.954-2.42c-.912-.97-1.935-.974-2.404-1.031-3.359-.247-8.397-.247-8.397-.247h-.01s-5.038 0-8.397.247c-.469.057-1.491.061-2.404 1.032-.72.74-.954 2.42-.954 2.42S0 9.12 0 11.092v1.85c0 1.971.24 3.944.24 3.944s.234 1.68.954 2.42c.913.97 2.112.939 2.646 1.04 1.92.188 8.16.246 8.16.246s5.043-.008 8.402-.255c.469-.056 1.492-.06 2.404-1.032.72-.74.954-2.42.954-2.42s.24-1.972.24-3.944v-1.85c0-1.971-.24-3.944-.24-3.944ZM9.523 15.183V8.335l6.484 3.435-6.484 3.413Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="facebook"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23 12.067C23 5.955 18.075 1 12 1S1 5.955 1 12.067C1 17.591 5.023 22.17 10.281 23v-7.734H7.488v-3.199h2.793V9.63c0-2.774 1.643-4.306 4.155-4.306 1.204 0 2.462.216 2.462.216v2.724h-1.387c-1.366 0-1.792.853-1.792 1.728v2.076h3.05l-.487 3.2h-2.563V23C18.977 22.17 23 17.591 23 12.067Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="x"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.903 10.469 21.348 2h-1.764l-6.465 7.353L7.955 2H2l7.808 11.12L2 22h1.764l6.828-7.765L16.044 22H22l-8.097-11.531Zm-2.417 2.748-.791-1.107L4.4 3.3h2.71l5.08 7.11.791 1.107 6.604 9.242h-2.71l-5.389-7.542Z"></path></svg></span></div>
                                                    <div class="calendar-cell "></div>
                                                    <div class="calendar-cell "></div>
                                                    
													
													
													 <div class="calendar-cell active"><span class="ChannelsSection_tile__aOdcn" data-channel="linkedin"><svg class="ChannelsSection_tileLogo__cKUC6" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M18.338 18.338H15.67v-4.177c0-.997-.018-2.278-1.387-2.278-1.39 0-1.602 1.085-1.602 2.206v4.25h-2.668v-8.59h2.561v1.173h.036c.356-.675 1.227-1.388 2.526-1.388 2.703 0 3.202 1.78 3.202 4.092v4.712ZM7.004 8.574a1.548 1.548 0 1 1 0-3.097 1.548 1.548 0 0 1 0 3.097ZM5.67 18.338h2.67v-8.59h-2.67v8.59ZM19.668 3H4.328C3.597 3 3 3.581 3 4.297v15.404C3 20.418 3.596 21 4.329 21h15.339c.734 0 1.332-.582 1.332-1.299V4.297C21 3.581 20.402 3 19.668 3Z"></path></svg></span></div>
                                            </div>

                </div>

            </div>
        </div>

    </div>
</section>

<section class="section features" id="features">

    <div class="container">

        <div class="section-heading">
            <h2>Everything You Need to Manage Social Media</h2>

            <p>
                Powerful tools designed to make planning and managing
                your social media workflow simpler.
            </p>
        </div>

        <div class="feature-grid">

            <div class="feature-card">
                <div class="feature-icon">◷</div>
                <h3>Social Media Scheduling</h3>
                <p>
                    Plan and schedule your content across your connected
                    social media accounts.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">✦</div>
                <h3>AI Content Generation</h3>
                <p>
                    Generate social media content efficiently with
                    AI-powered content creation tools.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">▤</div>
                <h3>Bulk Post Import</h3>
                <p>
                    Import multiple posts efficiently using bulk
                    spreadsheet-based content management.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">▦</div>
                <h3>Content Calendar</h3>
                <p>
                    Organize scheduled and planned content using a
                    centralized calendar.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">◎</div>
                <h3>Multiple Social Accounts</h3>
                <p>
                    Manage your connected social media accounts
                    from one convenient place.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">✓</div>
                <h3>Easy Content Management</h3>
                <p>
                    Create, edit and manage your social posts through
                    an intuitive workflow.
                </p>
            </div>

        </div>

    </div>

</section>

<section class="section how" id="how-it-works">

    <div class="container">

        <div class="section-heading">
            <h2>How It Works</h2>

            <p>
                Get your social media workflow organized in three simple steps.
            </p>
        </div>

        <div class="steps">

            <div class="step">
                <div class="step-number">01</div>
                <h3>Connect</h3>
                <p>
                    Connect your supported social media accounts
                    to your Social Scheduler workspace.
                </p>
            </div>

            <div class="step">
                <div class="step-number">02</div>
                <h3>Create</h3>
                <p>
                    Create your posts or use AI-powered tools
                    to help generate content.
                </p>
            </div>

            <div class="step">
                <div class="step-number">03</div>
                <h3>Schedule</h3>
                <p>
                    Schedule your content and manage your publishing
                    workflow from one place.
                </p>
            </div>

        </div>

    </div>

</section>

<section class="section">

    <div class="container benefits">

        <div>
            <h2>
                A simpler way to manage your social media workflow.
            </h2>

            <p class="benefits-text">
                Spend less time switching between tools and more time
                focusing on the content you want to publish.
            </p>

            <div class="benefit-list">

                <div class="benefit-item">
                    <span class="check">✓</span>
                    Save time managing social media
                </div>

                <div class="benefit-item">
                    <span class="check">✓</span>
                    Organize content in one place
                </div>

                <div class="benefit-item">
                    <span class="check">✓</span>
                    Schedule posts ahead of time
                </div>

                <div class="benefit-item">
                    <span class="check">✓</span>
                    Simplify bulk content management
                </div>

                <div class="benefit-item">
                    <span class="check">✓</span>
                    Work more efficiently
                </div>

            </div>
        </div>

        <div class="img-box">
		 <img src="../public/images/image_2.png" alt="SocialScheduler">
             <!-- <div class="dashboard-preview">
                <div class="preview-window">
				

                   <div class="preview-top">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>

                    <div class="preview-content">

                        <div class="preview-title"></div>

                        <div class="calendar">
                            @for($i = 1; $i <= 21; $i++)
                                <div class="calendar-cell {{ $i % 4 === 0 ? 'active' : '' }}"></div>
                            @endfor
                        </div>

                    </div>

                </div>
            </div> -->
        </div>

    </div>

</section>

<section class="cta">

    <div class="container">

        <div class="cta-box">

            <h2>Ready to simplify your social media workflow?</h2>

            <p>
                Create, organize and schedule your social media content
                from one place.
            </p>

            <a href="{{ route('login') }}" class="btn btn-primary">
                Get Started
            </a>

        </div>

    </div>

</section>

@endsection