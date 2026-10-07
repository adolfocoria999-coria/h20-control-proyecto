# H2O Control

Sistema web para que una **OTB** administre su servicio de agua potable: lecturas de medidores, cobros, multas, finanzas y reportes de morosidad.

Hecho con **Laravel 12**, Blade, Tailwind CSS 3 (compilado con Vite) y Alpine.js. Base de datos MySQL/MariaDB.

---

## Roles

| Rol | Puede |
|---|---|
| **Superadministrador** (presidente) | Todo, incluida la gestión de socios y la eliminación de balances |
| **Administrador** (Secretario de Hacienda) | Lecturas, cobros, multas, tarifas, finanzas, QR de pago y reportes |
| **Socio** | Ver su consumo y sus deudas, sus multas, las finanzas de la OTB y el QR de pago |

Los permisos se controlan con el middleware `role` (`app/Http/Middleware/EnsureUserHasRole.php`) y el enum `App\Enums\RolUsuario`. No hay registro público: los socios los crea el superadministrador.

## Módulos

- **Lecturas**: una lectura por socio y mes. El consumo y el monto se calculan solos; la tarifa por m³ está en la tabla `ajustes` (`tarifa_m3`).
- **Multas**: individuales o masivas, con tarifas configurables. Una multa cobrada se suma sola al balance del mes en Finanzas (columna "Multas cobradas").
- **Finanzas**: un balance por mes, con comprobantes privados (solo se ven con sesión iniciada) y el **QR de pago** de la OTB, que tiene fecha de vencimiento y avisa 7 días antes.
- **Reportes**: totales cobrados y pendientes por gestión y mes, y lista de socios morosos. Se exportan a Excel (CSV).
- **Socios**: alta, edición y baja. Dar de baja a un socio **no borra su historial** (SoftDeletes).

---

## Requisitos

- PHP 8.2 o superior, con las extensiones `pdo_mysql`, `mbstring`, `fileinfo` y `openssl`
- Composer 2
- Node.js 20 o superior y npm
- MySQL 8 o MariaDB 10.4 o superior (por ejemplo, XAMPP)

## Instalación

```bash
composer install
npm install

cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
```

Edita `.env` con los datos de tu base (crea antes la base vacía `h2o_control`):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=h2o_control
DB_USERNAME=root
DB_PASSWORD=
```

Luego:

```bash
php artisan migrate --seed    # tablas, roles, tarifas de multas y el primer superadministrador
php artisan storage:link      # hace visible el QR de pago
npm run build                 # compila CSS y JS
php artisan serve             # http://127.0.0.1:8000
```

### Primer acceso

El seeder crea al superadministrador:

- Correo: `admin@otb.com`
- Contraseña: `12345678`

**Cámbiala apenas entres** (menú → Mi Perfil).

---

## Uso diario (desarrollo)

| Comando | Para qué |
|---|---|
| `npm run dev` | Recompila los estilos al guardar. Úsalo mientras editas vistas |
| `npm run build` | Compila los estilos para publicar. **Obligatorio** si cambiaste clases de Tailwind: `public/build` se sube al repositorio porque el hosting puede no tener Node |
| `php artisan migrate` | Aplica migraciones nuevas. Ejecútalo después de cada actualización del código |
| `php artisan test` | Ejecuta las pruebas (usan SQLite en memoria, no tocan tu base) |
| `vendor/bin/pint` | Da formato al código PHP |
| `vendor/bin/phpstan analyse` | Análisis estático (Larastan) |

Las pruebas, Pint y PHPStan se ejecutan solos en GitHub en cada push (`.github/workflows/ci.yml`).

### Activar el análisis estático (una sola vez)

PHPStan pesa unos 100 MB; instálalo con buena conexión:

```bash
composer install
vendor/bin/phpstan analyse --generate-baseline   # guarda los avisos actuales en phpstan-baseline.neon
```

Agrega `- phpstan-baseline.neon` a `includes` en `phpstan.neon` y quita `continue-on-error: true` del paso de Larastan en `.github/workflows/ci.yml`. Desde ese momento, cualquier aviso **nuevo** hará fallar la CI.

## Respaldos

Antes de migrar o actualizar, saca una copia de la base:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root h2o_control --result-file=respaldo_h2o.sql
```

Para restaurarla:

```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root h2o_control < respaldo_h2o.sql"
```

Los archivos `.sql` están en `.gitignore`: contienen datos personales de los socios y **nunca deben subirse al repositorio**.

---

## Estructura

```
app/
  Enums/RolUsuario.php          Roles del sistema
  Http/Controllers/             Un controlador por módulo
  Http/Requests/                Validación de formularios
  Http/Middleware/              Control de acceso por rol
  Models/                       Lectura, Multa, BalanceMensual, TarifaMulta, Ajuste, User, Rol
  Services/
    CsvExporter.php             Exportación a Excel (UTF-8, separador ;)
    ReporteFinanciero.php       Cálculos de cobranza y morosidad
    QrPago.php                  QR de pago y su vencimiento
  Support/Meses.php             Nombres de los meses (se guardan como número 1-12)
lang/es/, lang/es.json          Textos en español
tests/Feature/                  Pruebas por módulo
```

## Configuración regional

La aplicación está en español (`APP_LOCALE=es`) y en hora de Bolivia (`APP_TIMEZONE=America/La_Paz`).

## Para publicar en un servidor

- `APP_ENV=production` y `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- Usar HTTPS y `SESSION_SECURE_COOKIE=true`
- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build`
- `php artisan migrate --force`
- `php artisan optimize`
- Respaldo automático diario de la base de datos
