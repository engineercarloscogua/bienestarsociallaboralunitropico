# Checklist de publicación en Hostinger hPanel

## Antes de desplegar desde GitHub

- Rotar en hPanel cualquier contraseña de base de datos que se haya compartido en chats o capturas.
- Descargar un respaldo de `public_html/data/data.json`.
- Confirmar que `.htaccess`, `database/schema.sql` y `config/database.example.php` estén en el despliegue.
- No incluir ZIP, `.env`, `config/database.local.php` ni credenciales.
- Confirmar que los respaldos de Hostinger incluyan la base de datos y `public_html/assets/uploads/`.
- Crear un widget gratuito de Cloudflare Turnstile limitado al dominio de producción.

## Preparar MariaDB

1. En hPanel abre **Bases de datos -> Administración** y conserva la base y el usuario ya creados.
2. Cambia la contraseña de ese usuario por una nueva y privada.
3. Abre phpMyAdmin, selecciona la base correcta e importa `database/schema.sql`.
4. En el Administrador de archivos crea una carpeta `private` al lado de `public_html`.
5. Copia el contenido de `config/database.example.php` a `private/bienestar-database.php`.
6. Completa host `localhost`, puerto `3306`, nombre, usuario y la contraseña nueva.
7. Completa `turnstile_site_key` y `turnstile_secret_key` con las claves del widget. La clave secreta debe permanecer únicamente en este archivo privado.
8. Mantén inicialmente `storage => 'json'`.

Si no recuerdas la contraseña del panel, agrega temporalmente una línea como esta, usando un token aleatorio propio de 32 caracteres o más:

```php
'admin_recovery_token' => 'TOKEN_ALEATORIO_DE_UN_SOLO_USO',
```

Después abre `/admin/recover.php`, define la nueva contraseña y elimina inmediatamente esa línea o déjala vacía. El mismo token no podrá utilizarse dos veces.

## Importar sin perder datos

1. Ejecuta el despliegue desde GitHub.
2. Comprueba que `public_html/data/data.json` siga presente; si falta, restaura el respaldo antes de abrir el panel.
3. Recupera primero el acceso desde `/admin/recover.php` si fuera necesario.
4. Entra en `/admin/database.php`.
5. Verifica que la conexión y el esquema aparezcan como listos.
6. Autoriza **Importar JSON a MariaDB y verificar** con la contraseña actual del administrador.
7. Confirma que los conteos de ambos lados coincidan.
8. Edita el archivo privado y cambia solamente `storage => 'mysql'`.

## Prueba posterior

1. Abrir `/index.php` y recorrer las secciones.
2. Entrar a `/admin/login.php` con la contraseña vigente.
3. Editar una tarjeta y comprobar el cambio público.
4. Enviar un comentario de prueba, comprobar la validación anti-bots y moderarlo.
5. Navegar varias páginas y revisar la analítica del Dashboard.
6. Subir una imagen pequeña desde `Admin -> Imágenes`.

## Permisos

- Directorios: `755`.
- Archivos: `644`.
- `assets/uploads/`: `755` o `775` si PHP no puede escribir.
- `data/`: requiere escritura solo mientras se use JSON o se conserve su funcionamiento de respaldo.

Evita permisos `777`.

## Recuperación rápida

Si MariaDB presenta un problema antes de haber recibido datos nuevos:

1. Cambia `storage => 'json'` en el archivo privado.
2. Confirma que `data/data.json` sea la copia correcta.
3. Diagnostica la conexión desde `Admin -> Base de datos`.

No vuelvas a JSON después de recibir comentarios o cambios nuevos en MariaDB sin exportarlos primero, porque el JSON ya estaría desactualizado.
