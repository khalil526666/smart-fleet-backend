@php($title = 'Mission terminée')
@php($heading = 'Confirmation')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Bonjour ' . e($chauffeur->name ?: 'Conducteur') . ',</p>
    <p style="margin:0 0 12px 0;">Vous avez bien terminé la mission suivante. Une copie d’information a également été envoyée à l’équipe Smart Fleet.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">' . e($mission->title) . '</div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Statut : <strong>terminée</strong></div>
    </div>
  ')
])
