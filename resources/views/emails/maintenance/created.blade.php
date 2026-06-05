@php($title = 'Maintenance enregistrée')
@php($heading = 'Maintenance enregistrée')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Une opération de maintenance a été enregistrée.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">' . e($maintenance->maintenance_type) . '</div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Véhicule: <strong>' . e(($vehicle?->brand ? ($vehicle->brand . ' ' . $vehicle->model) : '—')) . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Immatriculation: <strong>' . e($vehicle?->license_plate ?: '—') . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Date: <strong>' . e(optional($maintenance->date)->toDateString() ?: '—') . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Coût: <strong>' . e($maintenance->cost) . '</strong></div>
      ' . ($maintenance->description ? ('<div style="margin-top:10px;color:#111827;font-size:13px;white-space:pre-wrap;">' . e($maintenance->description) . '</div>') : '') . '
    </div>
    <p style="margin:0;color:#6b7280;font-size:13px;">Consultez Smart Fleet pour le détail et l’historique.</p>
  ')
])

