# Portal Bienestar Social Laboral - Unitrópico

Portal público y panel administrativo autogestionable en PHP 8. El contenido puede operar temporalmente desde JSON y, para producción, desde MariaDB/MySQL mediante PDO.

## Funcionalidades

- Páginas, tarjetas y subpáginas dinámicas.
- Biblioteca de imágenes y documentos.
- Comentarios públicos con respuestas, moderación y protección anti-bots.
- Analítica interna mensual basada en permanencia e interacción, con deduplicación.
- Configuración y cambio de contraseña desde el panel.
- Integraciones con Google Calendar, Google Forms y recursos externos.
- Reproductores adaptables para videos de YouTube y archivos compartidos desde Google Drive.
- Migraciones de contenido versionadas y almacenamiento transaccional.

## Requisitos en Hostinger hPanel

- PHP 8.0 o superior.
- Extensiones `pdo_mysql`, `json`, `session` y `fileinfo`.
- Base MariaDB/MySQL con InnoDB, `utf8mb4` y `utf8mb4_unicode_ci`.
- Permiso de escritura en `assets/uploads/`.
- Durante la transición JSON, permiso de escritura también en `data/`.

## Arquitectura de datos

La aplicación conserva un único contrato de datos y permite dos backends:

- `json`: modo de transición y respaldo; usa `data/data.json`.
- `mysql`: modo de producción; usa PDO y las tablas de `database/schema.sql`.

MariaDB almacena configuración, páginas, tarjetas, programas, usuario administrador, comentarios, analítica, seguridad y registro de migraciones. Las imágenes y PDF permanecen en `assets/uploads/`; la base guarda sus rutas.

En el editor de subpáginas, un bloque multimedia incrusta automáticamente enlaces válidos de YouTube o Google Drive. Los archivos de Drive deben permitir acceso a las personas que tengan el enlace. Otros dominios se muestran como botones externos y no se incrustan.

Todas las escrituras a MariaDB usan transacciones InnoDB y un bloqueo lógico para evitar que dos solicitudes simultáneas sobrescriban cambios. Si MariaDB falla, el portal muestra el error y no cambia silenciosamente a JSON, porque eso dividiría los datos.

## Configuración privada

Las credenciales no se guardan en Git. La aplicación busca la configuración en este orden:

1. Ruta indicada por la variable `APP_DB_CONFIG`.
2. `private/bienestar-database.php`, como carpeta hermana de la raíz del proyecto.
3. `config/database.local.php`, solo para desarrollo y excluido por `.gitignore`.

Usa [config/database.example.php](config/database.example.php) como plantilla. En Hostinger, si el proyecto está directamente en `public_html`, crea `private/` al lado de `public_html` y guarda allí `bienestar-database.php`.

No reutilices la contraseña que fue compartida en una conversación o captura: rótala en hPanel y escribe la nueva únicamente en el archivo privado.

### Protección anti-bots con Turnstile

Crea un widget gratuito de Cloudflare Turnstile para el dominio de producción y agrega al archivo privado:

```php
'turnstile_site_key' => 'CLAVE_PUBLICA_DEL_WIDGET',
'turnstile_secret_key' => 'CLAVE_SECRETA_PRIVADA',
```

La clave secreta nunca debe entrar al repositorio. En `localhost`, `127.0.0.1` y dominios `.test`, el portal utiliza automáticamente las claves oficiales de prueba de Cloudflare cuando no hay claves configuradas. En producción deben estar presentes las dos claves; si falta alguna, el backend rechaza nuevos comentarios para no dejar el formulario desprotegido.

### Recuperación administrativa de un solo uso

Si se perdió la contraseña online, agrega temporalmente al archivo privado un `admin_recovery_token` aleatorio de al menos 32 caracteres. Abre `/admin/recover.php`, introduce ese token y define la contraseña nueva. El portal registra el hash del token para impedir que vuelva a utilizarse, incluso después de migrar a MariaDB. Al terminar, elimina el token del archivo privado.

## Migración de JSON a MariaDB

1. Descarga un respaldo del `data/data.json` de producción.
2. Despliega el código manteniendo `storage => 'json'` en el archivo privado.
3. En phpMyAdmin selecciona la base e importa `database/schema.sql`.
4. Si no recuerdas la contraseña online, usa una sola vez `/admin/recover.php` y elimina el token privado.
5. Entra en `Admin -> Base de datos`.
6. Verifica conexión y esquema, escribe la contraseña vigente del administrador y ejecuta la importación.
7. Confirma que los conteos de JSON y MariaDB coincidan.
8. Cambia solo `storage => 'mysql'` en el archivo privado.
9. Prueba el portal, un comentario, el panel, una edición y la analítica.
10. Conserva el JSON como respaldo durante unos días; no lo elimines inmediatamente.

La importación reemplaza únicamente las tablas funcionales del portal dentro de una transacción. Si una consulta falla, MariaDB revierte la operación completa.

## Desarrollo local

Copia la plantilla como `config/database.local.php`, crea una base local e importa el esquema. Para verificar el ciclo completo:

```bash
php tests/database-roundtrip.php
php tests/bot-protection.php
php tests/analytics-engagement.php
```

El test importa el JSON local, lo reconstruye desde MariaDB y valida las actualizaciones transaccionales. Debe ejecutarse solo contra una base de pruebas.

## Datos y despliegues desde GitHub

`data/data.json`, `data/security.json`, los archivos de bloqueo, los ZIP y la configuración local están excluidos de Git. Por eso un despliegue no debe sobrescribir los comentarios ni las credenciales de producción.

Los nuevos archivos cargados desde el panel en `assets/uploads/` también quedan ignorados por Git. Los recursos institucionales que ya estaban versionados continúan normalmente en el repositorio. Aun así, la base de datos y `assets/uploads/` deben incluirse en los respaldos de Hostinger, porque Git no sustituye una copia de seguridad de los archivos generados en producción.

Los cambios de contenido versionados viven en `data/migrations/` y se aplican una sola vez tanto en JSON como en MariaDB.

## Rutas

- Portal: `/index.php`
- Administración: `/admin/login.php`
- Recuperación temporal: `/admin/recover.php`
- Estado y migración de base: `/admin/database.php`

La configuración privada, `data/`, `database/`, `includes/`, `tests/` y otras carpetas internas están bloqueadas desde `.htaccess`.
