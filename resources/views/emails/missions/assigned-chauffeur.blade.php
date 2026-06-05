@php($title = 'Mission assignée')
@php($heading = 'Vous avez une nouvelle mission')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Bonjour ' . e($chauffeur->name ?: 'Conducteur') . ',</p>
    <p style="margin:0 0 12px 0;">Une mission vous a été assignée dans Smart Fleet. Connectez-vous à l’application pour la consulter et la démarrer.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">' . e($mission->title) . '</div>
      ' . ($mission->description ? ('<div style="margin-top:8px;color:#475569;font-size:13px;white-space:pre-wrap;">' . e($mission->description) . '</div>') : '') . '
      ' . ($vehicle ? ('<div style="margin-top:10px;color:#475569;font-size:13px;">Véhicule : <strong>' . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? ''))) . '</strong> — ' . e($vehicle->license_plate ?? '') . '</div>') : '') . '
      ' . ($mission->starts_at ? ('<div style="margin-top:6px;color:#475569;font-size:13px;">Prévu le : <strong>' . e($mission->starts_at->format('d/m/Y H:i')) . '</strong></div>') : '') . '
    </div>
    <p style="margin:0;color:#6b7280;font-size:13px;">Statut actuel : <strong>planifiée</strong>. Ouvrez « Mes missions » dans l’application pour démarrer.</p>
  ')
])
