@extends('layouts.public')

@section('title', 'Terms & Conditions - Social Scheduler')

@section('description', 'Terms and Conditions governing the use of Social Scheduler.')

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

    .legal-content h3 {
        color: #344054;
        font-size: 17px;
        margin: 25px 0 10px;
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

    @media (max-width: 800px) {
        .legal-layout {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .legal-sidebar {
            position: static;
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
        <h1>Terms & Conditions</h1>
        <p>
            Please read these terms carefully before using Social Scheduler.
        </p>
    </div>
</section>

<div class="container legal-layout">

    <aside class="legal-sidebar">
        <div class="legal-sidebar-title">On this page</div>

        <a href="#acceptance">Acceptance of Terms</a>
        <a href="#service">Description of Service</a>
        <a href="#accounts">User Accounts</a>
        <a href="#integrations">Social Media Integrations</a>
        <a href="#content">User Content</a>
        <a href="#acceptable-use">Acceptable Use</a>
        <a href="#ai">AI Generated Content</a>
        <a href="#publishing">Scheduling & Publishing</a>
        <a href="#payments">Subscriptions & Payments</a>
        <a href="#intellectual-property">Intellectual Property</a>
        <a href="#third-party">Third-Party Services</a>
        <a href="#availability">Availability</a>
        <a href="#disclaimer">Disclaimer</a>
        <a href="#liability">Limitation of Liability</a>
        <a href="#termination">Termination</a>
        <a href="#governing-law">Governing Law</a>
        <a href="#changes">Changes</a>
        <a href="#contact">Contact</a>
    </aside>

    <main class="legal-content">

        <div class="legal-note">
            <strong>Last Updated</strong>
            <p>{{ date('F d, Y') }}</p>
        </div>

        <section id="acceptance">
            <h2>1. Acceptance of Terms</h2>

            <p>
                Welcome to Social Scheduler. These Terms & Conditions govern
                your access to and use of the Social Scheduler website,
                application and related services.
            </p>

            <p>
                By accessing or using Social Scheduler, you acknowledge that
                you have read, understood and agree to be bound by these Terms
                & Conditions and any applicable policies referenced herein.
            </p>
        </section>

        <section id="service">
            <h2>2. Description of Service</h2>

            <p>
                Social Scheduler provides tools that allow users to create,
                manage, organize and schedule social media content through
                a centralized platform.
            </p>

            <p>
                Available functionality may include content creation,
                scheduling, bulk content import, AI-assisted content
                generation, content calendars and integrations with
                supported third-party social media platforms.
            </p>
        </section>

        <section id="accounts">
            <h2>3. User Accounts</h2>

            <p>
                Certain features require you to create an account. You are
                responsible for providing accurate and up-to-date information
                and for maintaining the security of your account credentials.
            </p>

            <p>
                You are responsible for activities performed through your
                account and should notify us if you believe your account has
                been accessed without authorization.
            </p>
        </section>

        <section id="integrations">
            <h2>4. Social Media Accounts and Integrations</h2>

            <p>
                Social Scheduler may allow you to connect supported social
                media accounts and platforms.
            </p>

            <p>
                You are responsible for ensuring that you have the appropriate
                authorization to connect and manage any social media account
                through the service.
            </p>

            <p>
                Third-party platforms may change their APIs, permissions,
                policies or availability. Such changes may affect features
                provided through Social Scheduler.
            </p>
        </section>

        <section id="content">
            <h2>5. User Content</h2>

            <p>
                You retain ownership of content that you submit, upload or
                create through Social Scheduler, subject to the rights
                necessary for us to provide the service.
            </p>

            <p>
                You grant Social Scheduler the necessary permission to
                process, store, transmit and display your content solely
                as required to provide requested functionality.
            </p>

            <p>
                You are responsible for ensuring that your content does not
                violate applicable laws or the rights of others.
            </p>
        </section>

        <section id="acceptable-use">
            <h2>6. Acceptable Use</h2>

            <p>
                You agree not to use Social Scheduler for unlawful,
                fraudulent, abusive, harmful or malicious activities.
            </p>

            <p>You must not use the service to:</p>

            <ul>
                <li>Violate applicable laws or regulations.</li>
                <li>Infringe intellectual property or privacy rights.</li>
                <li>Distribute malicious software or harmful content.</li>
                <li>Attempt to gain unauthorized access to the service.</li>
                <li>Abuse or interfere with the operation of the platform.</li>
                <li>Use automated methods to abuse service resources.</li>
            </ul>
        </section>

        <section id="ai">
            <h2>7. AI-Generated Content</h2>

            <p>
                Social Scheduler may provide AI-powered functionality to
                assist users in generating or improving social media content.
            </p>

            <p>
                AI-generated content may contain inaccurate, incomplete or
                inappropriate information. You are responsible for reviewing
                AI-generated content before publishing or using it.
            </p>

            <p>
                Social Scheduler does not guarantee that AI-generated content
                will be accurate, original, suitable for a particular purpose
                or free from errors.
            </p>
        </section>

        <section id="publishing">
            <h2>8. Scheduling and Publishing</h2>

            <p>
                Social Scheduler provides tools intended to help users
                schedule and publish social media content.
            </p>

            <p>
                Successful publishing may depend on third-party APIs,
                permissions, account status, connectivity, platform
                availability and other factors outside our control.
            </p>

            <p>
                We do not guarantee that every scheduled post will be
                successfully published at the requested time.
            </p>
        </section>

        <section id="payments">
            <h2>9. Subscriptions and Payments</h2>

            <p>
                Certain Social Scheduler features may be provided through
                paid subscription plans.
            </p>

            <p>
                Where applicable, pricing, billing periods, renewal terms
                and other applicable payment conditions will be presented
                during the relevant purchase or subscription process.
            </p>

            <p>
                Subscription availability, pricing and features may be
                changed from time to time.
            </p>
        </section>

        <section id="intellectual-property">
            <h2>10. Intellectual Property</h2>

            <p>
                The Social Scheduler platform, including its software,
                design, branding, logos, interfaces and original materials,
                is protected by applicable intellectual property laws.
            </p>

            <p>
                Except as expressly permitted, you may not copy, reproduce,
                modify, distribute, reverse engineer or commercially exploit
                the platform or its proprietary materials.
            </p>
        </section>

        <section id="third-party">
            <h2>11. Third-Party Services</h2>

            <p>
                Social Scheduler may integrate with third-party services,
                including social media platforms and technology providers.
            </p>

            <p>
                Your use of third-party services may be subject to their own
                terms, conditions and privacy policies. Social Scheduler is
                not responsible for changes made by third-party providers.
            </p>
        </section>

        <section id="availability">
            <h2>12. Service Availability and Changes</h2>

            <p>
                We may update, modify, improve, suspend or discontinue parts
                of the service from time to time.
            </p>

            <p>
                We will make reasonable efforts to maintain the availability
                of the platform but cannot guarantee uninterrupted or
                error-free operation.
            </p>
        </section>

        <section id="disclaimer">
            <h2>13. Disclaimer</h2>

            <p>
                Social Scheduler is provided on an availability basis.
                While reasonable efforts may be made to maintain the service,
                we do not guarantee that the platform will always be
                uninterrupted, secure, accurate or free of errors.
            </p>
        </section>

        <section id="liability">
            <h2>14. Limitation of Liability</h2>

            <p>
                To the maximum extent permitted by applicable law, Social
                Scheduler and its operators will not be responsible for
                indirect, incidental, special or consequential losses arising
                from the use of or inability to use the service.
            </p>

            <p>
                Nothing in these Terms is intended to exclude or limit
                liability that cannot legally be excluded or limited.
            </p>
        </section>

        <section id="termination">
            <h2>15. Termination</h2>

            <p>
                You may stop using the service at any time, subject to any
                applicable subscription or contractual obligations.
            </p>

            <p>
                We may suspend or terminate access where reasonably necessary,
                including in cases involving violations of these Terms,
                misuse of the service or security concerns.
            </p>
        </section>

        <section id="governing-law">
            <h2>16. Governing Law</h2>

            <p>
                These Terms shall be governed by the applicable laws of
                <strong>[Governing Law / Jurisdiction]</strong>.
            </p>

            <p>
                Please replace the above placeholder with the appropriate
                jurisdiction before publishing the final legal version.
            </p>
        </section>

        <section id="changes">
            <h2>17. Changes to These Terms</h2>

            <p>
                We may update these Terms & Conditions from time to time.
                Updated terms will be published on this page with a revised
                "Last Updated" date.
            </p>

            <p>
                Your continued use of the service after changes are published
                may constitute acceptance of the updated terms, where
                permitted by applicable law.
            </p>
        </section>

        <section id="contact">
            <h2>18. Contact Us</h2>

            <p>
                If you have questions regarding these Terms & Conditions,
                please contact the Social Scheduler team using the official
                contact information provided by the service.
            </p>
        </section>

    </main>

</div>

@endsection