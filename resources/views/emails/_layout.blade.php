<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title ?? 'Smart Fleet' }}</title>
  </head>
  <body style="margin:0;padding:0;background:#f5f7fb;font-family:Segoe UI, Roboto, Arial, sans-serif;color:#111827;">
    <div style="max-width:640px;margin:0 auto;padding:24px 14px;">
      <div style="background:#0f766e;border-radius:14px;padding:18px 18px;color:#fff;">
        <div style="font-weight:900;letter-spacing:.12em;font-size:14px;">SMART FLEET</div>
        <div style="opacity:.92;margin-top:6px;font-size:13px;">Gestion de flotte · Missions · Maintenance</div>
      </div>

      <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 18px;margin-top:12px;">
        <h1 style="margin:0 0 10px 0;font-size:18px;line-height:1.35;color:#0f172a;">{{ $heading ?? ($title ?? 'Notification') }}</h1>

        <div style="font-size:14px;line-height:1.55;color:#111827;">
          {{ $slot }}
        </div>

        <div style="margin-top:16px;padding-top:12px;border-top:1px solid #eef2f7;font-size:12px;line-height:1.5;color:#6b7280;">
          <div>Ce message a été envoyé automatiquement par Smart Fleet.</div>
          <div style="margin-top:4px;">© {{ date('Y') }} Smart Fleet</div>
        </div>
      </div>
    </div>
  </body>
</html>

