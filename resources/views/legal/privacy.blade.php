@extends('layouts.public')

@section('title', 'Privacy Policy - Social Scheduler')

@section('description', 'Learn how Social Scheduler collects, uses and protects your information.')

@push('styles')
<style>
    .legal-hero {
        background: linear-gradient(180deg, #f8f9ff 0%, #ffffff 100%);
        padding: 75px 0 55px;
        text-align: center;
        border-bottom: 1px solid #edf0f5;
    }

    .legal-hero h1 {
        font-size: 48px;
        line-height: 1.15;
        letter-spacing: -1.5px;
        color: #101828;
        margin-bottom: 14px;
    }

    .legal-hero p {
        color: #667085;
        font-size: 15px;
    }

    .legal-layout {
        display: grid;
        grid-template-columns: 230px 1fr;
        gap: 60px;
        padding: 70px 0 100px;
    }

    .legal-sidebar {
        position: sticky;
        top: 100px;
        height: fit-content;
    }

    .legal-sidebar-title {
        font-size: 12px;
        font-weight: 700;
        color: #98a2b3;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 14px;
    }

    .legal-sidebar a {
        display: block;
        padding: 8px 12px;
        border-left: 2px solid transparent;
        color: #667085;
        font-size: 13px;
        transition: .2s;
    }

    .legal-sidebar a:hover {
        color: #635bff;
        border-left-color: #635bff;
        background: #f8f7ff;
    }

    .legal-content {
        max-width: 800px;
    }

    .legal-content h2 {
        color: #101828;
        font-size: 22px;
        margin: 38px 0 12px;
        letter-spacing: -.3px;
    }

    .legal-content h2:first-child {
        margin-top: 0;
    }

    .legal-content p {
        color: #667085;
        font-size: 15px;
        line-height: 1.8;
        margin-bottom: 15px;
    }

    .legal-content ul {
        margin: 10px 0 20px 22px;
    }

    .legal-content li {
        color: #667085;
        font-size: 15px;
        line-height: 1.8;
        margin-bottom: 7px;
    }

    .legal-note {
        background: #f8f9fc;
        border: 1px solid #eaecf0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .legal-note strong {
        color: #344054;
        font-size: 14px;
    }

    .legal-note p {
        margin: 6px 0 0;
        font-size: 14px;
    }

    .privacy-highlight {
        padding: 22px;
        border-radius: 12px;
        background: #f5f3ff;
        border: 1px solid #e3dfff;
        margin: 25px 0;
    }

    .privacy-highlight p {
        margin: 0;
        color: #4b46a8;
        font-size: 14px;
    }

    @media (max-width: 800px) {
        .legal-layout {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .legal-sidebar {
            display: none;
        }

        .legal-hero h1 {
            font-size: 36px;
        }

        .legal-content h2 {
            font-size: 20px;
        }
    }
</style>
@endpush

@section('content')

<section class="legal-hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>
            Learn how we collect, use and protect information when you use
            Social Scheduler.
        </p>
    </div>
</section>

<div class="container legal-layout">

    <aside class="legal-sidebar">
        <div class="legal-sidebar-title">On this page</div>

        <a href="#introduction">Introduction</a>
        <a href="#information">Information We Collect</a>
        <a href="#social-data">Social Media Data</a>
        <a href="#use">How We Use Information</a>
        <a href="#ai">AI Processing</a>
        <a href="#cookies">Cookies</a>
        <a href="#sharing">Data Sharing</a>
        <a href="#retention">Data Retention</a>
        <a href="#security">Data Security</a>
        <a href="#transfers">International Transfers</a>
        <a href="#rights">Your Rights</a>
        <a href="#children">Children's Privacy</a>
        <a href="#third-party">Third-Party Services</a>
        <a href="#changes">Changes</a>
        <a href="#contact">Contact</a>
    </aside>

    <main class="legal-content">

        <div class="legal-note">
            <strong>Last Updated</strong>
            <p>{{ date('F d, Y') }}</p>
        </div>

        <section id="introduction">
            <h2>1. Introduction</h2>

            <p>
                Social Scheduler respects your privacy and is committed to
                protecting the information associated with your use of the
                service.
            </p>

            <p>
                This Privacy Policy explains what information may be
                collected, how it may be used and the choices available
                to you when using Social Scheduler.
            </p>
        </section>

        <section id="information">
            <h2>2. Information We Collect</h2>

            <p>
                Depending on how you use the service, we may collect or
                process the following categories of information:
            </p>

            <ul>
                <li>Name and account information.</li>
                <li>Email address and contact information.</li>
                <li>Authentication and account-related information.</li>
                <li>Connected social media account information.</li>
                <li>Posts, images and other content you upload or create.</li>
                <li>Scheduled post information.</li>
                <li>Usage and interaction information.</li>
                <li>Browser, device and technical information.</li>
                <li>IP address and security/log information where applicable.</li>
                <li>Information collected through cookies or similar technologies.</li>
            </ul>
        </section>

        <section id="social-data">
            <h2>3. Social Media Data</h2>

            <p>
                When you connect a supported social media account,
                Social Scheduler may process information necessary to
                authenticate the connection and provide requested
                functionality.
            </p>

            <p>
                This may include information required to manage connected
                accounts and create or publish content through the relevant
                platform.
            </p>

            <p>
                Social Scheduler does not intentionally request or access
                unrelated private information from connected social
                platforms.
            </p>
        </section>

        <section id="use">
            <h2>4. How We Use Information</h2>

            <p>
                Information may be used for legitimate purposes including:
            </p>

            <ul>
                <li>Providing and operating Social Scheduler.</li>
                <li>Managing user accounts.</li>
                <li>Scheduling and publishing requested content.</li>
                <li>Providing AI-assisted content functionality.</li>
                <li>Improving application functionality and user experience.</li>
                <li>Maintaining platform security.</li>
                <li>Preventing fraud, abuse and unauthorized activity.</li>
                <li>Providing customer support.</li>
                <li>Sending important service-related communications.</li>
                <li>Complying with applicable legal requirements.</li>
            </ul>
        </section>

        <section id="ai">
            <h2>5. AI Processing</h2>

            <p>
                Social Scheduler may provide AI-powered features for
                generating or improving social media content.
            </p>

            <div class="privacy-highlight">
                <p>
                    When you use an AI-powered feature, the content required
                    to provide that feature may be processed by technology
                    providers used to deliver the AI functionality.
                </p>
            </div>

            <p>
                AI-generated results may not always be accurate. Users should
                review generated content before publishing it.
            </p>
        </section>

        <section id="cookies">
            <h2>6. Cookies and Similar Technologies</h2>

            <p>
                Social Scheduler may use cookies and similar technologies
                necessary to operate the website and maintain user sessions.
            </p>

            <p>
                Depending on the features enabled on the platform, cookies
                may also be used for preferences, analytics or other
                functionality.
            </p>
        </section>

        <section id="sharing">
            <h2>7. Data Sharing</h2>

            <p>
                Information may be shared with service providers when
                necessary to operate Social Scheduler.
            </p>

            <p>This may include:</p>

            <ul>
                <li>Hosting and infrastructure providers.</li>
                <li>Technology and software service providers.</li>
                <li>Social media platforms when required for requested functionality.</li>
                <li>AI service providers when AI features are used.</li>
                <li>Professional advisers where reasonably necessary.</li>
                <li>Authorities where disclosure is required by law.</li>
            </ul>

            <p>
                We do not sell personal information as part of the ordinary
                operation of the Social Scheduler service unless explicitly
                stated in an applicable notice.
            </p>
        </section>

        <section id="retention">
            <h2>8. Data Retention</h2>

            <p>
                We retain information for as long as reasonably necessary
                to provide the service, maintain legitimate business
                records, resolve disputes, enforce agreements and comply
                with applicable legal obligations.
            </p>

            <p>
                Retention periods may vary depending on the type of
                information and the purpose for which it is processed.
            </p>
        </section>

        <section id="security">
            <h2>9. Data Security</h2>

            <p>
                We use reasonable technical and organizational measures
                designed to protect information against unauthorized access,
                alteration, disclosure or destruction.
            </p>

            <p>
                However, no internet transmission or electronic storage
                system can be guaranteed to be completely secure.
            </p>
        </section>

        <section id="transfers">
            <h2>10. International Data Transfers</h2>

            <p>
                Social Scheduler and its service providers may process
                information in countries other than the country in which
                you reside.
            </p>

            <p>
                Where applicable, appropriate safeguards may be used for
                international transfers in accordance with applicable law.
            </p>
        </section>

        <section id="rights">
            <h2>11. Your Privacy Rights</h2>

            <p>
                Depending on applicable law and your location, you may have
                rights regarding your personal information, which may include:
            </p>

            <ul>
                <li>Requesting access to personal information.</li>
                <li>Requesting correction of inaccurate information.</li>
                <li>Requesting deletion of certain information.</li>
                <li>Requesting restriction of processing where applicable.</li>
                <li>Objecting to certain processing activities where applicable.</li>
                <li>Requesting data portability where applicable.</li>
            </ul>

            <p>
                Requests are subject to applicable legal requirements and
                available verification procedures.
            </p>
        </section>

        <section id="children">
            <h2>12. Children's Privacy</h2>

            <p>
                Social Scheduler is intended for users who are able to
                lawfully use the service under applicable laws.
            </p>

            <p>
                We do not knowingly seek to collect personal information
                from children where such collection is prohibited by law.
            </p>
        </section>

        <section id="third-party">
            <h2>13. Third-Party Services</h2>

            <p>
                Social Scheduler may integrate with third-party platforms
                and services.
            </p>

            <p>
                These third parties may have their own privacy policies and
                terms governing their handling of information.
            </p>

            <p>
                We encourage users to review the privacy policies of
                third-party services they choose to connect.
            </p>
        </section>

        <section id="changes">
            <h2>14. Changes to This Privacy Policy</h2>

            <p>
                We may update this Privacy Policy periodically to reflect
                changes to the service, technology, legal requirements or
                our privacy practices.
            </p>

            <p>
                When changes are made, the updated policy will be published
                on this page together with a revised "Last Updated" date.
            </p>
        </section>

        <section id="contact">
            <h2>15. Contact Us</h2>

            <p>
                If you have questions, concerns or requests regarding this
                Privacy Policy or the handling of your information, please
                contact the Social Scheduler team using the official contact
                information provided by the service.
            </p>
        </section>

    </main>

</div>

@endsection