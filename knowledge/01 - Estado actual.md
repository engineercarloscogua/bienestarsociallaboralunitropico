# Estado actual

## Resumen

El proyecto es PHP plano con HTML, CSS y JavaScript vanilla. No usa base de datos. El contenido se guarda en `data/data.json`.

## Servidor local

- Laragon está instalado.
- PHP disponible en `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- Servidor usado: `http://127.0.0.1:8000/index.php`.

## Cambios recientes

- Se cambió el sitio público de estilo oscuro a estilo claro institucional.
- Se aplicó paleta basada en manual de identidad: verde institucional, bronce/oliva, blanco y neutros.
- Se vinculó el escudo real en el sidebar.
- Se agregaron imágenes a tarjetas mediante `image_url` en `data/data.json`.
- Se completó el mapeo de imágenes de `Images_pag` hacia las secciones y tarjetas correspondientes.
- Los héroes de Talento Humano, Desarrollo, Bienestar y Calendario muestran imagen institucional o de sección.
- Se quitaron partículas/canvas del fondo.
- El panel admin también fue llevado a estilo claro.
- `admin/cards.php` permite guardar y editar `image_url` para no perder imágenes al modificar tarjetas.
- `admin/cards.php` ahora permite elegir imágenes desde un selector con vista previa, usando la biblioteca `assets/uploads`.
- `admin/cards.php` también permite subir una imagen nueva directamente al editar/crear una tarjeta; si se sube archivo, reemplaza la imagen seleccionada.
- `admin/cards.php` tiene el botón Editar reforzado con `type="button"` y datos JSON seguros en `data-card`, para que el modal abra de forma estable al editar tarjetas.
- Las imágenes de tarjetas públicas y vistas previas del admin usan `object-fit: contain` para mostrarse completas, centradas y sin recortes.
- Las tarjetas públicas muestran la descripción como una capa desplegable sobre la imagen con hover/foco; en móviles o pantallas táctiles la descripción queda visible bajo la imagen.
- Se creó `pages/programa.php` como plantilla dinámica para subpáginas de programas.
- `admin/pages.php` administra el contenido interno de las tarjetas que apuntan a `pages/programa.php?slug=...`: imagen principal, introducción, bloques, enlaces y secciones por año.
- El editor de contenido en `admin/pages.php` usa repetidores visuales para agregar/quitar secciones, enlaces y recursos por año sin escribir formatos manuales.
- Los bloques de `program_pages` pueden incluir `image_url` para mostrar imágenes dentro de cada bloque en `pages/programa.php`.
- La sección Gestión del Talento Humano ya no muestra “Mis Registros (Looker Studio)”.
- Inducción y Reinducción quedaron unificadas en una sola tarjeta con subpágina por años 2025 y 2026.
- El calendario de actividades usa el iframe de Google Calendar institucional.
- El footer/sidebar usan los correos `psicologiaorganizacional@unitropico.edu.co` y `climaorganizacional@unitropico.edu.co`.
- El favicon del sitio usa `assets/uploads/logosimbolo.png`.
- El footer público fue reemplazado por un bloque institucional con logo, contacto, ventanilla única, enlace a líneas de contacto, texto de inspección y franja legal.
- `pages/programa.php` ahora renderiza secciones extendidas: beneficios con imágenes, piezas interactivas, boletines por año, recursos multimedia, visor PDF y juegos.
- La Política de Estímulos incluye incentivo por uso de bicicleta, formulario, consulta de registros y beneficios gráficos adicionales.
- La Política de Integridad incluye boletines 2025, espacio 2026 y tarjetas desplegables de valores basadas en el Acuerdo CS No. 050 de 2023.
- Herramientas de Liderazgo ya tiene subpágina propia con piezas prácticas, videos, podcasts y charlas recomendadas.
- Preparación para la Jubilación incluye pieza gráfica extraída de PDF, guía de presupuesto personal embebida y ruta interactiva de retiro.
- Recursos de Autocuidado incluye enfoque psicosocial/clima organizacional, videos, podcasts, accesos de Positiva y zona de juegos.
- Calendario de Actividades tiene tarjetas destacadas rediseñadas con interacción y CTA al calendario mensual.
- La navegación lateral tiene transición animada entre secciones.
- Talibrí quedó integrado como mascota fija del portal, con mensajes aleatorios cada 2 minutos, animaciones y orientación hacia el contenido.
- CSS y JS usan `filemtime()` como cache-busting para que el navegador cargue cambios recientes.

## Verificación reciente

- Todos los `.php` pasaron `php -l`.
- Páginas públicas principales respondieron HTTP `200`.
- Las seis subpáginas dinámicas de programa respondieron HTTP `200`.

Relacionado: [[03 - Identidad visual]], [[04 - Autogestion CMS]], [[06 - Bitacora]]
