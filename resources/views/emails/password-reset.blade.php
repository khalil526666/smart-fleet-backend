@php($title = 'Réinitialisation du mot de passe')
@php($heading = 'Réinitialisation du mot de passe')

@include('emails._layout', [
  'title' => $title,
  'heading' => $heading,
  'slot' => new \Illuminate\Support\HtmlString('
    <p style="margin:0 0 10px 0;">Bonjour ' . e($user->name ?: 'Utilisateur') . ',</p>
    <p style="margin:0 0 12px 0;">Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe.</p>
    <p style="margin:0 0 14px 0;text-align:center;">
      <a href="' . e($resetUrl) . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:800;">Réinitialiser mon mot de passe</a>
    </p>
    <p style="margin:0 0 12px 0;color:#6b7280;font-size:13px;">Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</p>
    <p style="margin:0;color:#6b7280;font-size:13px;">Lien direct: <span style="word-break:break-all;">' . e($resetUrl) . '</span></p>
  ')
])

