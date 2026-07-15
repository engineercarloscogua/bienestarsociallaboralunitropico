# Checklist de publicación en cPanel

## Antes de subir

- Comprimir el contenido del proyecto, no la carpeta padre.
- No es necesario subir `tmp/`.
- No es necesario subir `knowledge/` si no quieres llevar notas internas al hosting.
- Mantener estos archivos:
  - `.htaccess`
  - `data/.htaccess`
  - `data/security.json`
  - `includes/.htaccess`
  - `assets/uploads/.htaccess`

## Permisos recomendados

- Directorios: `755`
- Archivos: `644`
- `data/`: `755` o, si el hosting lo requiere, `775`
- `assets/uploads/`: `755` o, si el hosting lo requiere, `775`

Evita `777` salvo que el hosting lo exija temporalmente. Si toca usarlo, vuelve a bajarlo después de probar.

## Prueba rápida después de subir

1. Abrir `/index.php`.
2. Entrar a `/admin/login.php`.
3. Cambiar contraseña del admin.
4. Subir una imagen pequeña desde `Admin -> Imágenes`.
5. Publicar un comentario de prueba y eliminarlo desde `Admin -> Comentarios`.
6. Navegar dos o tres secciones y revisar `Dashboard -> Visitas del portal`.

La contraseña inicial se entrega por separado con el paquete. Cambiala en el primer ingreso y no la dejes en correos, capturas o archivos públicos.

## Si algo no guarda

Revisa permisos de:

- `data/data.json`
- `data/`
- `assets/uploads/`

## Si aparece error 403

Verifica que no hayas subido el portal dentro de una carpeta con reglas `.htaccess` heredadas. También confirma que cPanel permita `Options -Indexes` y `Require all denied`.
