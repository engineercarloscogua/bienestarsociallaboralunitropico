# Portal Bienestar Social Laboral - Unitrópico

Portal público + panel admin autogestionable en PHP, sin base de datos por ahora.

## Requisitos en cPanel

- PHP 8.0 o superior.
- Apache con `.htaccess` habilitado.
- Permiso de escritura para `data/` y `assets/uploads/`.
- Extensiones PHP estándar: `json`, `session`, `fileinfo`.
- El archivo `data/security.json` se usa para los límites de seguridad y debe permanecer dentro de `data/`.

## Subida a cPanel

1. Entra a cPanel -> File Manager.
2. Sube el contenido completo del proyecto a:
   - `public_html/` si será el sitio principal.
   - `public_html/bienestar/` si será una sección.
3. Verifica permisos:
   - Carpetas: `755`.
   - Archivos: `644`.
   - `data/`: debe permitir escritura.
   - `assets/uploads/`: debe permitir escritura.
4. Abre el sitio:
   - Portal: `https://tudominio.com/`
   - Admin: `https://tudominio.com/admin/login.php`

Si lo subes a una subcarpeta:

- Portal: `https://tudominio.com/bienestar/`
- Admin: `https://tudominio.com/bienestar/admin/login.php`

## Credenciales

El usuario inicial es `admin`. La contraseña inicial aleatoria se entrega junto con el paquete de despliegue y debe cambiarse después del primer ingreso desde:

`Dashboard -> Cambiar Contraseña`

No publiques la contraseña en la documentación del servidor ni la reutilices en otros servicios.

## Qué queda autogestionable

- Títulos principales del sitio.
- Tarjetas y servicios.
- Subpáginas dinámicas.
- Imágenes desde biblioteca.
- Comentarios y respuestas de visitantes.
- Estadísticas internas por mes.
- Google Calendar embebido.

## Seguridad incluida

El proyecto incluye `.htaccess` para:

- Evitar listado de directorios.
- Bloquear acceso web directo a `data/`.
- Bloquear acceso web directo a `includes/`.
- Bloquear acceso web directo a `knowledge/` y `tmp/`.
- Impedir ejecución de PHP dentro de `assets/uploads/`.
- Añadir cabeceras básicas de seguridad.
- Proteger el panel con sesión endurecida, CSRF, expiración y límite de intentos.
- Aplicar límites de frecuencia a comentarios y analítica pública.

## Importante

No requiere MySQL todavía. Los datos se guardan en `data/data.json`, por eso esa carpeta debe poder escribirse desde PHP.

Cuando en el futuro migren a base de datos, la interfaz del admin puede conservarse y cambiarse solo la capa de almacenamiento.
