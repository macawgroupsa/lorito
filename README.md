# Lorito · Control de ventas

Sistema local para administrar jugadas de números, puntos vendedores, listas, ventas, resultados, usuarios y cuadres.

## Tecnología

- Laravel 13 con PHP 8.3+
- Vue 3 y Vite
- Tailwind CSS 4
- MySQL / MariaDB
- Zona horaria `America/Guatemala`

## Iniciar en Laragon

1. Crea la base MySQL `loritogt` si aún no existe y ajusta las credenciales en `.env`.
2. Instala dependencias con `composer install` y `npm install`.
3. Corre `php artisan migrate --seed` para preparar tablas y usuario administrador.
4. Configura `CENTRAL_DB_DATABASE` y `TENANT_DEFAULT_SLUG` en `.env`, luego corre `php artisan saas:install` para crear la base central SaaS y registrar la base local como primer negocio. La migración central crea el superadministrador de la plataforma.
5. Compila los recursos con `npm run build`.
6. Inicia `php artisan serve --host=127.0.0.1 --port=8010` y abre `http://127.0.0.1:8010`.

Acceso inicial: `admin@lorito.local` / `lorito2026`. Cambia esta contraseña antes de exponer el sitio a otras personas o redes.

## Módulos

- **Resumen:** ventas, pedazos, comisiones, premios y neto por fecha; estados de jugada actualizados cada 15 segundos.
- **Jugadas:** horario del sorteo, anticipación de cierre, pedazos por quetzal y premio por quetzal.
- **Puntos de venta y listas:** asignación de listas y comisión por punto.
- **Ventas:** pantalla táctil con jugadas múltiples, números `00`–`99`, monto por número y comprobante PDF angosto listo para imprimir. El servidor bloquea ventas al llegar a `hora del sorteo - minutos de cierre`.
- **Resultados:** número ganador y cálculo de premios a partir de ventas y reglas capturadas al vender.
- **Cuadres:** desglose por jugada y lista; exportación CSV.
- **Usuarios:** administradores y vendedores asignados a un punto.

El ejemplo inicial es Q5 por 400 pedazos y Q400 de premio: el valor de inicio queda en 80 pedazos y Q80 por cada quetzal, editable en cada jugada. Cada venta conserva una copia de su porcentaje de comisión y regla de pago para que cambiar una configuración no altere el historial.

## SaaS y aislamiento

- `/register` crea un negocio con 14 días de prueba y su propia base MySQL (`lorito_tenant_*`). El primer usuario queda como administrador de ese negocio.
- `/superadmin/login` abre el panel privado para revisar negocios y administrar el estado, plan, precio y vencimiento manual de cada suscripción. `/saas/login` redirige a esta dirección por compatibilidad.
- Cada petición selecciona su base de datos por subdominio (`TENANT_BASE_DOMAIN`) o por el identificador `tenant` del enlace/header. En local, el registro deja el enlace con `?tenant=identificador`; en producción se recomienda configurar DNS wildcard y HTTPS para los subdominios.
- El usuario MySQL de la aplicación debe poder crear bases de datos para los nuevos negocios. Todas las bases usan las credenciales MySQL configuradas en `.env`; sus tablas y datos de operación son independientes.
- La prueba inicia en 7 días. La renovación y el control de estado son manuales; no hay pasarela de pago conectada todavía.
- El superadministrador se provisiona al ejecutar las migraciones de la base central. La contraseña se guarda como hash y puede actualizarse en el servidor mediante `SUPERADMIN_EMAIL` y `SUPERADMIN_PASSWORD` al ejecutar `php artisan saas:install`.
