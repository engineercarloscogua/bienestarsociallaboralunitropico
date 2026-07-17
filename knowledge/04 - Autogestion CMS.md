# Autogestión CMS

## Lo que ya existe

- Login admin en `admin/login.php`.
- Dashboard en `admin/dashboard.php`.
- Gestión de tarjetas en `admin/cards.php`.
- Gestión de páginas en `admin/pages.php`.
- Gestión de imágenes en `admin/media.php`.
- Estado y migración en `admin/database.php`.
- Persistencia MariaDB mediante PDO, con JSON disponible para transición.

## Capacidades actuales

- Editar configuración del sitio.
- Cambiar contraseña.
- Crear/editar/eliminar tarjetas.
- Crear/editar registros de subpáginas.
- Subir y eliminar imágenes.
- Incrustar videos de YouTube o Google Drive pegando su enlace en un bloque multimedia.

## Limitación principal

Crear una subpágina en el admin no crea automáticamente una página pública navegable. Actualmente el sistema espera que exista `pages/[slug].php`.

## Mejor solución recomendada

Crear una plantilla pública dinámica:

- `pages/page.php?slug=mi-pagina`, o
- router tipo `page.php?slug=...` desde links generados.

Así el admin podría crear subpáginas reales sin tocar archivos PHP.

## Avance implementado

Ya existe `pages/programa.php?slug=...` para renderizar subpáginas de programas desde el almacenamiento activo, en la sección `program_pages`.

Subpáginas actuales:

- `politica-estimulos`
- `induccion-reinduccion`
- `politica-integridad`
- `preparacion-jubilacion`
- `psicologia-orientacion`
- `recursos-autocuidado`

## Campos útiles a futuro

- `content_html` o bloques estructurados.
- `hero_image`.
- `image_url` por tarjeta.
- `template` para tipos de página.

Relacionado: [[02 - Arquitectura]], [[05 - Pendientes]]
