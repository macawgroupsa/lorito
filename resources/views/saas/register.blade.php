@extends('saas.layout', ['title' => 'Crear negocio · Lorito'])
@section('content')
<section class="card" style="max-width:560px;margin:auto"><div class="brand">Lorito<span style="color:#e3ad2d">.</span></div><h1>Abre tu espacio de ventas</h1><p class="muted">Tu negocio tendrá sus propios datos y una prueba gratis de 7 días.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="/register">@csrf
<label>Nombre del negocio<input name="business_name" required maxlength="150" value="{{ old('business_name') }}" placeholder="Ej. La suerte de Puerto Barrios"></label>
<label>Enlace corto para tu negocio<input name="slug" required maxlength="40" pattern="[A-Za-z0-9_-]{3,40}" value="{{ old('slug') }}" placeholder="mi-punto"><span class="muted">Usarás este identificador para ingresar a tu espacio.</span></label>
<label>Tu nombre<input name="owner_name" required maxlength="120" value="{{ old('owner_name') }}"></label>
<label>Correo electrónico<input name="email" type="email" required value="{{ old('email') }}"></label>
<div class="grid"><label>Contraseña<input name="password" type="password" required minlength="8" autocomplete="new-password"></label><label>Confirma la contraseña<input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></label></div>
<button style="width:100%;margin-top:20px">Crear mi negocio</button></form><p class="muted" style="text-align:center;margin-top:18px">¿Ya tienes cuenta? <a href="/?tenant=lorito">Ingresa a tu puesto</a></p></section>
@endsection
