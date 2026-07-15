# Bitácora

## 2026-07-06

- Se revisó que el proyecto no usa base de datos y depende de `data/data.json`.
- Se detectó que la autogestión de subpáginas está incompleta porque requiere crear archivos PHP manualmente.
- Se levantó el proyecto localmente con PHP de Laragon en `127.0.0.1:8000`.
- Se revisaron primeras 25 páginas del manual de identidad mediante renderizado PDF.
- Se cambió el sitio público a estilo claro institucional.
- Se agregó uso de imágenes en tarjetas mediante `image_url`.
- Se actualizó sidebar con escudo institucional.
- Se actualizó admin a estilo claro.
- Se creó esta bóveda Obsidian en `knowledge/` para mantener contexto del proyecto.
- Se revisaron imágenes de `Images_pag` y se integraron en tarjetas/héroes por sección.
- Se completaron imágenes faltantes en Talento Humano y se agregó edición de `image_url` en `admin/cards.php`.
- Se corrigió `data/data.json` a UTF-8 sin BOM para que PHP pueda decodificarlo correctamente.
- Se eliminó la tarjeta “Mis Registros (Looker Studio)” de Gestión del Talento Humano.
- Se unificaron Inducción y Reinducción en una sola tarjeta.
- Se creó `pages/programa.php` para subpáginas dinámicas de programas.
- Se agregaron subpáginas para Política de Estímulos, Inducción/Reinducción, Política de Integridad, Preparación para la Jubilación, Psicología y Orientación Laboral, y Recursos de Autocuidado.
- Se integró el iframe de Google Calendar solicitado en `pages/calendario.php`.
- Se mejoró `admin/cards.php` para cambiar imágenes de tarjetas desde un selector visual con vista previa.
- Se corrigió la sustitución de imágenes en tarjetas agregando `multipart/form-data`, subida directa de archivo y validación MIME/tamaño.
- Se robusteció el botón Editar de `admin/cards.php`: ahora usa `type="button"`, datos JSON en `data-card`, función intermedia segura y funciones expuestas para que el modal de edición abra aunque haya caracteres especiales en los textos.
- Se ajustó el CSS de imágenes de tarjetas públicas y vista previa del admin para usar `object-fit: contain`, centrar la imagen y evitar recortes al cargar logos, afiches o piezas con formato variable.
- Se cambió el diseño de las tarjetas públicas para que la descripción viva en una capa sobre la imagen y se despliegue con hover, foco o navegación por teclado; en pantallas táctiles se muestra de forma visible debajo de la imagen.
- Se amplió `admin/pages.php` con la sección "Contenido de tarjetas" para editar desde administración las subpáginas dinámicas de `pages/programa.php?slug=...`, incluyendo imagen principal, introducción, bloques de texto/imagen, enlaces y secciones por año.
- Se perfeccionó el editor de "Contenido de tarjetas" para que use secciones agregables con campos separados: tipo de bloque, título, texto, imagen, URL multimedia, botones/enlaces y recursos por año. Ya no depende de escribir líneas con separadores manuales.
- `pages/programa.php` ahora puede renderizar imágenes dentro de los bloques de contenido mediante `image_url`.
- Se reemplazó `bienestar@unitropico.edu.co` por los correos reales de psicología y clima organizacional.
- Se creó `assets/uploads/logosimbolo.png` y se configuró como favicon del sitio.
- Se reemplazó el footer simple por un footer institucional inspirado en la referencia enviada, con enlace a atención al ciudadano de Unitrópico.

Relacionado: [[01 - Estado actual]], [[03 - Identidad visual]], [[05 - Pendientes]]

## 2026-07-08

- Se levantó nuevamente el proyecto local en `http://127.0.0.1:8000/` usando PHP de Laragon.
- Se enriqueció `pages/programa.php` para soportar nuevas secciones reutilizables: beneficios, piezas interactivas, boletines por año, biblioteca de recursos, visor PDF y zona de juegos.
- En Política de Estímulos e Incentivos se agregó el incentivo por uso de bicicleta con formulario, consulta de registros y piezas gráficas de beneficios.
- En Política de Integridad se agregaron boletines 2025, espacio reservado para boletines 2026 y piezas interactivas basadas en el Acuerdo CS No. 050 de 2023.
- En Herramientas de Liderazgo se creó subpágina dinámica con consejos, piezas interactivas, videos, podcasts y charlas recomendadas.
- En Evaluación de Rendimiento Laboral se configuró enlace externo a `http://144.91.123.192:8090/`.
- En Preparación para la Jubilación se agregó pieza gráfica extraída de PDF, visor de la guía de presupuesto personal y ruta interactiva de preparación para el retiro.
- En Recursos de Autocuidado se agregaron contenidos con enfoque de psicología y clima organizacional, videos, podcasts, accesos de Positiva y juegos de autocuidado.
- En Calendario de Actividades se rediseñaron las tarjetas de actividades destacadas con interacción, descripción ampliada y CTA hacia el calendario.
- Se añadieron transiciones animadas entre secciones al navegar desde el menú lateral.
- Se integró la mascota Talibrí en todas las páginas públicas, con imagen recortada, mensajes aleatorios cada 2 minutos, animaciones, entrada visible y orientación hacia el contenido.
- Se agregaron versiones automáticas a `assets/css/main.css` y `assets/js/main.js` mediante `filemtime()` para evitar caché vieja en navegador.
