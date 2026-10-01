@extends('saas.layout', ['title' => 'Superadministrador · Lorito'])
@section('content')
<section class="card" style="max-width:480px;margin:auto"><div class="brand">Lorito · Plataforma</div><h1>Acceso de superadministrador</h1><p class="muted">Administra los negocios, sus pruebas y sus suscripciones.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="/superadmin/login">@csrf<label>Correo<input name="email" type="email" required autocomplete="username"></label><label>Contraseña<input name="password" type="password" required autocomplete="current-password"></label><button style="width:100%;margin-top:20px">Ingresar</button></form></section>
@endsection
