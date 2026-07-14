@php
    $resolvedSiteName = $siteName ?? setting('site_name', 'Unsell');
    $resolvedTitle = $maintenanceTitle ?? setting('maintenance_title', 'We\'ll be back soon');
    $resolvedMessage = $maintenanceMessage ?? setting('maintenance_message', 'The marketplace is temporarily unavailable. Please try again shortly.');
    $resolvedSupportEmail = $supportEmail ?? setting('contact_email', '');
    $resolvedSupportPhone = $supportPhone ?? setting('support_phone', '');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline | {{ $resolvedSiteName }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(170deg, #fff9f2, #f3f4f6);
            color: #10242f;
        }

        .card {
            width: min(90vw, 480px);
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 30px rgba(2, 6, 23, 0.08);
            padding: 28px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        p {
            margin: 10px 0 0;
            line-height: 1.55;
            color: #334155;
        }

        a {
            display: inline-block;
            margin-top: 18px;
            text-decoration: none;
            background: #ea580c;
            color: white;
            border-radius: 14px;
            padding: 11px 18px;
            font-weight: 600;
        }

        .support {
            margin-top: 14px;
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
        }

        .support a {
            display: inline;
            margin-top: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            color: #ea580c;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>{{ $resolvedTitle }}</h1>
        <p>{{ $resolvedMessage }}</p>

        @if ($resolvedSupportEmail !== '' || $resolvedSupportPhone !== '')
            <p class="support">
                Need help?
                @if ($resolvedSupportEmail !== '')
                    Email <a href="mailto:{{ $resolvedSupportEmail }}">{{ $resolvedSupportEmail }}</a>
                @endif
                @if ($resolvedSupportEmail !== '' && $resolvedSupportPhone !== '')
                    or
                @endif
                @if ($resolvedSupportPhone !== '')
                    call <a href="tel:{{ preg_replace('/[^0-9\+]/', '', $resolvedSupportPhone) }}">{{ $resolvedSupportPhone }}</a>
                @endif
            </p>
        @endif

        <a href="/">Retry</a>
    </main>
</body>
</html>
