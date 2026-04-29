<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KidZoo API</title>
    <style>
        :root {
            --bg: #f6f1e8;
            --panel: rgba(255, 252, 247, 0.9);
            --text: #1f2937;
            --muted: #5b6472;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --accent: #f97316;
            --line: rgba(31, 41, 55, 0.12);
            --shadow: 0 30px 70px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(249, 115, 22, 0.28), transparent 30%),
                radial-gradient(circle at bottom right, rgba(15, 118, 110, 0.25), transparent 28%),
                linear-gradient(135deg, #fff7ed 0%, #f8fafc 48%, #ecfeff 100%);
        }

        .page {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 48px 0 56px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            align-items: stretch;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }

        .intro {
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .intro::after {
            content: "";
            position: absolute;
            inset: auto -40px -48px auto;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.22), transparent 68%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.1);
            color: var(--primary-dark);
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(38px, 6vw, 72px);
            line-height: 0.95;
            letter-spacing: -0.04em;
        }

        .lead {
            max-width: 640px;
            margin: 0 0 24px;
            font-size: 20px;
            line-height: 1.7;
            color: var(--muted);
        }

        .actions,
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .button,
        .chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .button:hover,
        .chip:hover {
            transform: translateY(-2px);
        }

        .button-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 16px 32px rgba(15, 118, 110, 0.24);
        }

        .button-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--line);
        }

        .status {
            padding: 28px;
            display: grid;
            gap: 18px;
            background:
                linear-gradient(180deg, rgba(15, 118, 110, 0.96), rgba(17, 94, 89, 0.95)),
                linear-gradient(135deg, transparent 0 52%, rgba(255, 255, 255, 0.1) 52% 100%);
            color: #f8fafc;
        }

        .status h2,
        .section h3 {
            margin: 0;
        }

        .status-grid,
        .section-grid {
            display: grid;
            gap: 14px;
        }

        .status-item,
        .section-card {
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .status strong,
        .section-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .status code,
        .section-card code {
            font-family: Consolas, "Courier New", monospace;
            font-size: 14px;
        }

        .section {
            margin-top: 24px;
            padding: 30px;
        }

        .section p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .section-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 22px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--line);
        }

        .chip {
            padding: 10px 14px;
            background: rgba(15, 118, 110, 0.08);
            color: var(--primary-dark);
            border: 1px solid rgba(15, 118, 110, 0.14);
        }

        .footer-note {
            margin-top: 20px;
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 920px) {
            .hero,
            .section-grid {
                grid-template-columns: 1fr;
            }

            .intro,
            .status,
            .section {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero">
            <article class="card intro">
                <span class="eyebrow">KidZoo Backend</span>
                <h1>KidZoo API is live.</h1>
                <p class="lead">
                    This Laravel service powers the KidZoo graduation project: parent and child authentication,
                    game sessions, chatbot history, dashboards, notifications, and ML-assisted visual perception screening.
                </p>

                <div class="actions">
                    <a class="button button-primary" href="{{ url('/api/health') }}">Open health check</a>
                    <a class="button button-secondary" href="{{ url('/docs') }}">Read local API docs</a>
                </div>

                <div class="section" style="margin-top: 28px; padding: 0; background: transparent; box-shadow: none; border: 0;">
                    <h3>Quick access</h3>
                    <p>Useful entry points for testing the backend locally.</p>
                    <div class="quick-links" style="margin-top: 18px;">
                        <a class="chip" href="{{ url('/api/parent/register') }}">Parent register</a>
                        <a class="chip" href="{{ url('/api/parent/login') }}">Parent login</a>
                        <a class="chip" href="{{ url('/api/child/login') }}">Child login</a>
                        <a class="chip" href="{{ url('/up') }}">Framework health</a>
                    </div>
                </div>
            </article>

            <aside class="card status">
                <div>
                    <h2>Local runtime</h2>
                </div>

                <div class="status-grid">
                    <div class="status-item">
                        <strong>App</strong>
                        <code>{{ config('app.name', 'KidZoo') }}</code>
                    </div>
                    <div class="status-item">
                        <strong>Environment</strong>
                        <code>{{ app()->environment() }}</code>
                    </div>
                    <div class="status-item">
                        <strong>API base</strong>
                        <code>{{ url('/api') }}</code>
                    </div>
                    <div class="status-item">
                        <strong>Database</strong>
                        <code>{{ config('database.default') }}</code>
                    </div>
                    <div class="status-item">
                        <strong>Session driver</strong>
                        <code>{{ config('session.driver') }}</code>
                    </div>
                    <div class="status-item">
                        <strong>Queue driver</strong>
                        <code>{{ config('queue.default') }}</code>
                    </div>
                </div>
            </aside>
        </section>

        <section class="card section">
            <h3>What this backend covers</h3>
            <p>The current app is API-first. The homepage is intentionally a launchpad for developers, testers, and mobile integration work.</p>

            <div class="section-grid">
                <article class="section-card">
                    <strong>Parent flows</strong>
                    <code>/api/parent/register</code><br>
                    <code>/api/parent/login</code><br>
                    <code>/api/parent/profile</code>
                </article>

                <article class="section-card">
                    <strong>Child flows</strong>
                    <code>/api/child/login</code><br>
                    <code>/api/child/games</code><br>
                    <code>/api/child/sessions/start</code>
                </article>

                <article class="section-card">
                    <strong>Monitoring</strong>
                    <code>/api/health</code><br>
                    <code>/up</code><br>
                    <code>/docs</code>
                </article>
            </div>

            <p class="footer-note">
                ML requests are proxied by Laravel to the separate local inference service on port 8001.
            </p>
        </section>
    </main>
</body>
</html>
