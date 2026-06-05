<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - Users</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
      .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #f3f4f6; }
      .actions form { display: inline-block; margin-right: 8px; }
      button { padding: 6px 10px; border: 1px solid #d1d5db; background: white; border-radius: 8px; cursor: pointer; }
      button:hover { background: #f9fafb; }
      button[disabled] { opacity: .5; cursor: not-allowed; }
    </style>
  </head>
  <body>
    <h2>Admin Dashboard</h2>
    <p>Manage user roles (admin only).</p>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $user)
          <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td><span class="pill">{{ $user->role }}</span></td>
            <td class="actions">
              <form method="POST" action="{{ route('users.makeGestionnaire', ['id' => $user->id]) }}">
                @csrf
                <button type="submit" @disabled($user->role === 'admin')>Make Gestionnaire</button>
              </form>
              <form method="POST" action="{{ route('users.removeGestionnaire', ['id' => $user->id]) }}">
                @csrf
                <button type="submit" @disabled($user->role === 'admin')>Remove Gestionnaire</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </body>
</html>

