# H2O Control

Sistema web para que una **OTB** administre su servicio de agua potable: lecturas de medidores, cobros, multas, finanzas y reportes de morosidad.

Hecho con **Laravel 12**, Blade, Tailwind CSS 3 (compilado con Vite) y Alpine.js. Base de datos MySQL/MariaDB.

---

## Roles

| Rol | Puede |
|---|---|
| **Superadministrador** (presidente) | Todo, incluida la gestión de socios, la tarifa del agua, restablecer contraseñas y la eliminación de balances |
| **Administrador** (Secretario de Hacienda) | Lecturas, cobros, multas, tarifas, finanzas, QR de pago y reportes |
| **Socio** | Ver su consumo y sus deudas, sus multas, las finanzas de la OTB y el QR de pago |

Los permisos se controlan con el middleware `role` (`app/Http/Middleware/EnsureUserHasRole.php`) y el enum `App\Enums\RolUsuario`. No hay registro público: los socios los crea el superadministrador.

**Contraseñas:** la que pone el superadministrador (al crear un socio o con el botón 🔑 *Clave* de la lista de socios) es temporal: el sistema obliga a cambiarla al ingresar. No hay recuperación por correo; quien la olvida la pide a la directiva, que puede enviarla por WhatsApp desde la misma pantalla.

## Módulos

- **Lecturas**: una lectura por socio y mes. La lectura anterior la completa el sistema con la del mes previo del socio (solo la primera se escribe a mano: lectura inicial del medidor) y la actual no puede ser menor. El consumo y el monto se calculan solos; recibo de pago en PDF.
- **Tarifa del agua**: precio por m³, lo cambia el superadministrador (menú *Tarifa*, con historial). Cada lectura guarda la tarifa con la que se registró: un cambio no altera lo ya cobrado ni las deudas emitidas.
- **Multas**: individuales o masivas, con tarifas configurables y recibo en PDF.
- **Finanzas**: un balance por mes. El **agua y las multas cobradas se suman solas** al mes en que se cobraron (el agua, desde octubre de 2026: ajuste `finanzas_agua_desde`); a mano solo se cargan *otros ingresos* y egresos. Comprobantes privados y **QR de pago** de la OTB con fecha de vencimiento (avisa 7 días antes).
- **Arqueo de caja**: lo cobrado en el mes por QR y en efectivo, con acta en PDF para firmar.
- **Reportes**: totales cobrados y pendientes por gestión y mes, y lista de socios morosos con aviso por WhatsApp. Se exportan a Excel (CSV).
- **Mis pagos** (socio): historial de pagos y recibos en PDF.
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

Define también el primer superadministrador en `.env` (contraseña de 10 caracteres o más):

```dotenv
ADMIN_NOMBRE="Superadministrador"
ADMIN_EMAIL=presidente@tu-otb.com
ADMIN_PASSWORD=una-clave-segura
```

Luego:

```bash
php artisan migrate           # tablas, roles, tarifas de multas y el superadministrador de ADMIN_EMAIL
npm run build                 # compila CSS y JS
php artisan serve             # http://127.0.0.1:8000
```

### Primer acceso

Ingresa con `ADMIN_EMAIL` y `ADMIN_PASSWORD`. Ya no existe una clave fija: si `ADMIN_EMAIL` no está definido, no se crea ningún superadministrador.

El QR de pago y los comprobantes se guardan en la base de datos (tabla `archivos`), así que no hace falta `storage:link` y sobreviven a los redespliegues del hosting.

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

Menú **Respaldos** (presidente y Hacienda):

- **Descargar respaldo:** pide la contraseña y descarga al dispositivo un archivo `respaldo_h2o_AAAA-MM-DD_HHMM.json.gz` con **toda** la base: socios, lecturas, pagos, multas, finanzas, tarifas, historial y los archivos (QR, comprobantes y fotos). No queda guardado en el servidor: guárdalo en otra computadora, un pendrive o una nube personal. Hazlo al menos **una vez por semana**; la pantalla avisa si pasaron más de 7 días.
- **Restaurar** (solo el presidente): sube un respaldo, escribe `RESTAURAR` y confirma la contraseña. Reemplaza todos los datos por los del archivo en una sola transacción (si algo falla, no se cambia nada) y cierra la sesión.
- Cada descarga y restauración queda en el Historial.

Desde la consola (archivos grandes o restaurar en otra máquina):

```bash
php artisan respaldo:crear                       # storage/app/respaldo_h2o_....json.gz
php artisan respaldo:restaurar ruta/al/respaldo.json.gz
```

Un respaldo solo se puede restaurar en una versión del sistema igual o más nueva que la que lo generó. Los respaldos contienen datos personales de los socios: **nunca deben subirse al repositorio** (`*.sql` y `*.gz` están en `.gitignore`).

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

La versión en línea está en **Wasmer** (`https://h20-control-proyecto.wasmer.app`): cada push a `main` la vuelve a desplegar y ejecuta las migraciones. Las variables (`ADMIN_*`, base de datos, `APP_KEY`) se configuran en el panel de Wasmer.

Para cualquier servidor:

- `APP_ENV=production` y `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- Usar HTTPS y `SESSION_SECURE_COOKIE=true`
- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build`
- `php artisan migrate --force`
- `php artisan optimize`
- Respaldo automático diario de la base de datos
