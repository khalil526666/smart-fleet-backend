@php($title = 'Incident enregistré')
@php($heading = 'Votre signalement a bien été reçu')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Bonjour ' . e($chauffeur->name ?: 'Conducteur') . ',</p>
    <p style="margin:0 0 12px 0;">Nous avons bien enregistré votre signalement d’incident. L’équipe en sera informée.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">Référence #' . e($incident->id) . '</div>
      ' . ($mission ? ('<div style="margin-top:6px;color:#475569;font-size:13px;">Mission : <strong>' . e($mission->title) . '</strong></div>') : '') . '
      ' . ($incident->type ? ('<div style="margin-top:6px;color:#475569;font-size:13px;">Type : <strong>' . e($incident->type) . '</strong></div>') : '') . '
      <div style="margin-top:10px;color:#111827;font-size:13px;white-space:pre-wrap;">' . e($incident->description) . '</div>
    </div>
    <p style="margin:0;color:#6b7280;font-size:13px;">Conservez cet email comme accusé de réception.</p>
  ')
])
