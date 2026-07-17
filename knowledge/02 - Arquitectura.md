# Arquitectura

## Stack

- PHP 8.x y PDO `pdo_mysql`.
- MariaDB/MySQL con InnoDB y `utf8mb4` en producción.
- JSON como transición y respaldo controlado.
- CSS propio y JavaScript vanilla.
- Sin frameworks ni dependencias npm.

## Estructura principal

- `index.php` y `pages/`: portal público.
- `admin/`: panel de administración y asistente de base de datos.
- `includes/functions.php`: contrato de negocio independiente del backend.
- `includes/database.php`: carga de configuración privada y conexión PDO.
- `includes/database-storage.php`: lectura, escritura, transacciones e importación.
- `database/schema.sql`: esquema MariaDB/MySQL.
- `data/data.json`: datos vivos solo cuando el backend activo es JSON.
- `data/data.example.json`: plantilla versionada para instalaciones nuevas.
- `assets/uploads/`: archivos binarios; la base almacena sus rutas.

## Flujo de contenido

`JSON o MariaDB` -> `includes/functions.php` -> `index.php` / `pages/*.php` / `admin/*.php`

El backend se selecciona con `storage => 'json'` o `storage => 'mysql'` en un archivo privado. Nunca se hace fallback silencioso si MariaDB falla.

## Persistencia y concurrencia

- Las operaciones generales usan transacciones y el bloqueo lógico `storage_locks.content`.
- Analítica y seguridad actualizan solamente su registro JSON de base, bajo bloqueo.
- Comentarios se insertan y eliminan directamente.
- Las migraciones versionadas se registran en `_meta.migrations` y funcionan en ambos backends.
- Las credenciales se buscan fuera de `public_html` o en un archivo local ignorado por Git.

Relacionado: [[04 - Autogestion CMS]], [[05 - Pendientes]]
