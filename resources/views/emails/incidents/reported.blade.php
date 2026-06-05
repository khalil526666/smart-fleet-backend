@php($title = 'Incident signalé')
@php($heading = 'Incident signalé')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Un nouvel incident a été signalé.</p>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 12px;margin:10px 0 14px;">
      <div style="font-weight:900;color:#0f172a;">Incident #' . e($incident->id) . '</div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Signalé par: <strong>' . e($chauffeur->name) . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Mission: <strong>' . e($incident->mission_id ?: '—') . '</strong></div>
      <div style="margin-top:6px;color:#475569;font-size:13px;">Type: <strong>' . e($incident->type ?: '—') . '</strong></div>
      <div style="margin-top:10px;color:#111827;font-size:13px;white-space:pre-wrap;">' . e($incident->description) . '</div>
    </div>
    <p style="margin:0;color:#6b7280;font-size:13px;">Veuillez consulter Smart Fleet pour traiter cet incident.</p>
  ')
])

