# Arquitectura

## Stack

- PHP 8.x
- CSS propio
- JavaScript vanilla
- JSON como almacenamiento local
- Sin frameworks ni dependencias npm
- Sin base de datos

## Estructura principal

- `index.php`: página de inicio.
- `pages/`: subpáginas públicas actuales.
- `admin/`: panel de administración.
- `includes/functions.php`: lectura/escritura JSON, helpers, iconos y URLs.
- `includes/header.php`: layout público, sidebar y navegación.
- `includes/footer.php`: cierre del layout público.
- `data/data.json`: configuración, páginas, tarjetas y credenciales admin.
- `assets/uploads/`: imágenes usadas por el sitio.
- `Images_pag/`: carpeta fuente con imágenes organizadas por área.

## Flujo de contenido

`data/data.json` -> `includes/functions.php` -> `index.php` / `pages/*.php`

## Riesgos técnicos

- La creación de subpáginas aún no es completamente autogestionable: el admin crea el registro, pero no genera una ruta pública dinámica.
- README actual no describe fielmente el estado sin BD.
- Validación de imágenes en `admin/media.php` depende de `$_FILES['type']`; conviene usar `finfo_file()`.

Relacionado: [[04 - Autogestion CMS]], [[05 - Pendientes]]
