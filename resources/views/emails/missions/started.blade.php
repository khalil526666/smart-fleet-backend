@php($title = 'Mission démarrée')
@php($heading = 'Mission démarrée')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Une mission vient de démarrer.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">' . e($mission->title) . '</div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Chauffeur: <strong>' . e($chauffeur->name) . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Statut: <strong>in_progress</strong></div>
    </div>
    <p style="margin:0;color:#6b7280;font-size:13px;">Consultez l’application Smart Fleet pour suivre l’avancement.</p>
  ')
])

