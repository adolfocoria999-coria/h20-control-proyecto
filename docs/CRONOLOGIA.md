# Cronología del desarrollo de H2O Control

Sistema web de la OTB para administrar el servicio de agua potable: lecturas de medidores, cobros, multas, finanzas, reportes, app móvil para el plomero y asistente de ayuda.

**Inicio del desarrollo:** 12 de junio de 2026 · **Control de versiones con Git:** desde el 6 de octubre de 2026 · **En línea:** https://h20-control-proyecto.wasmer.app

> **De dónde salen las fechas.** Las de junio a septiembre provienen de los nombres de las migraciones de la base de datos (Laravel les pone la fecha y hora en que se crean, `AAAA_MM_DD_HHMMSS_...`) y de las fechas de creación de los archivos del proyecto. Desde el 6 de octubre provienen de los commits de Git (`git log`). Los archivos base de Laravel con fecha 14/03/2026 corresponden a la versión del framework, no al inicio del proyecto.

---

## Etapa 1 · Base del sistema (junio 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| **12/06/2026, ~16:55** | Se crea el proyecto con Laravel 12 | Fecha de creación de `resources/` y `storage/` |
| 12/06/2026, 17:22 | Inicio de sesión (Laravel Breeze), Tailwind CSS y Vite | `tailwind.config.js`, `vite.config.js` |
| 12/06/2026, 21:32 | Tabla de **roles**: superadministrador, administrador (Hacienda) y socio | `2026_06_12_213255_create_rols_table` |
| 12/06/2026, 21:50 | Datos del socio: carnet de identidad, teléfono y rol | `2026_06_12_215015_add_custom_fields_to_users_table` |
| 13/06/2026, 01:21 | Módulo de **lecturas de agua** (consumo por socio y mes) | `2026_06_13_012143_create_lecturas_table` |
| 13/06/2026, 05:31 | Estado de pago de las lecturas (pendiente / pagado) | `2026_06_13_053128_add_estado_to_lecturas_table` |

## Etapa 2 · Finanzas y multas (septiembre 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| 04/09/2026, 21:18 | Módulo de **finanzas**: balance mensual con ingresos, egresos y comprobante | `2026_09_04_211821_create_balance_mensuals_table` |
| 06/09/2026, 21:17 | **Tarifas de multas** configurables | `2026_09_06_211741_create_tarifas_multas_table` |
| 06/09/2026, 21:17 | Módulo de **multas**: individuales y masivas | `2026_09_06_211756_create_multas_table` |
| 07/09/2026, 01:19 | **Ajustes** del sistema: tarifa del agua por m³ | `2026_09_07_011958_create_ajustes_table` |

## Etapa 3 · Control de versiones, seguridad y publicación (6 y 7 de octubre de 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| **06/10/2026, 01:54** | **Inicio del control de versiones con Git** | `dbacff4` |
| 06/10/2026, 19:22 | Seguridad y deuda técnica: meses normalizados, historial de socios protegido (baja sin borrar), comprobantes privados, QR de pago con vencimiento, un balance por mes, multas en finanzas, **historial de actividades**, **arqueo de caja** y método de pago (QR / efectivo) | `341a5e2` · 9 migraciones del 06/10 |
| 06/10/2026, 20:51 | Preparación para el hosting: HTTPS, proxy y superadministrador inicial desde la configuración (sin clave fija) | `9f0e7b4` |
| 06/10/2026, 21:07 | Aplicación en la raíz del repositorio y seeders repetibles | `8d127d2`, `3df35ac` |
| 07/10/2026, 17:32 | Datos iniciales (roles, tarifas, superadministrador) creados por migración | `b42817f` |
| 07/10/2026, 17:57 | **Publicación en Wasmer**: sistema en línea | `9a0fd39` |

## Etapa 4 · Mejoras funcionales (15 de octubre de 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| 15/10/2026, 15:46 | QR de pago y comprobantes guardados en la base de datos (sobreviven a los redespliegues) | `3bb77bc` |
| 15/10/2026, 15:54 | Validación de los datos de los socios (nombre, C.I., celular, correo) | `19fbcd2` |
| 15/10/2026, 16:06 | **Recibos de pago, historial de pagos y acta de arqueo en PDF** | `d229843` |
| 15/10/2026, 16:25 | El agua cobrada pasa sola a Finanzas (desde octubre de 2026) | `dfcbb6e` |
| 15/10/2026, 16:44 | Lectura anterior calculada por el servidor, **tarifa guardada en cada lectura** y pantalla de tarifa para el presidente | `e374e45` |
| 15/10/2026, 16:54 | **Contraseñas temporales** y restablecimiento desde Socios; dependencia con vulnerabilidad actualizada | `b6f2d72` |
| 15/10/2026, 17:02 | **Carga rápida de lecturas del mes** en una planilla, con alerta de consumo anormal | `0eec4cb` |

## Etapa 5 · Plomero y app móvil (18 de octubre de 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| 18/10/2026, 15:09 | Rol para cargar lecturas desde el celular y **orden de recorrido** por zona | `15df464` |
| 18/10/2026, 15:21 | **App instalable (PWA) y carga de lecturas sin señal** con envío automático al volver la conexión | `129c42f` |
| 18/10/2026, 16:00 | Rol **Plomero** y **observaciones** en las lecturas | `7977255` |
| 18/10/2026, 16:26 | **Foto del medidor** desde el celular (comprimida en el dispositivo) | `2653171` |
| 18/10/2026, 17:01 | La foto acompaña a una observación; ajustes de la planilla para celular | `375e9dd` |

## Etapa 6 · Respaldos, avisos y asistente con IA (20 de octubre de 2026)

| Fecha | Qué se hizo | Evidencia |
|---|---|---|
| 20/10/2026, 00:31 | **Respaldos**: descarga completa al dispositivo y restauración en una sola transacción | `a6195bf` |
| 20/10/2026, 00:35 | Aviso de respaldo en tono de recomendación | `39d5096` |
| 20/10/2026, 00:41 | Botón "ojito" para ver la contraseña en todos los formularios | `ac5ded4` |
| 20/10/2026, 01:01 | **Aviso al socio por WhatsApp** "con un toque" cuando se registra su lectura | `c7ceae1` |
| 20/10/2026, 01:13 | **Notificaciones en el celular** del socio (Web Push) | `ed6d06d` |
| 20/10/2026, 01:25 | Instalar la app desde cualquier rol y reinstalarla si se borra | `e4a65df`, `7380654` |
| 20/10/2026, 02:00 | **Asistente "💬 Ayuda" con IA**: responde cómo usar el sistema según el rol, solo con el manual y sin datos personales | `3891e23` |
| 20/10/2026, 02:33 | El asistente saluda y responde preguntas básicas del servicio de agua | `2bb16c5` |
| 20/10/2026, 02:40 | El asistente conoce todos los temas del rol y no inventa funciones | `778d80e`, `f628754` |

---

## Resumen

| Etapa | Periodo | Resultado |
|---|---|---|
| 1. Base del sistema | Junio 2026 | Roles, socios y lecturas de agua |
| 2. Finanzas y multas | Septiembre 2026 | Balance mensual, multas y tarifa del agua |
| 3. Seguridad y publicación | 6–7 de octubre de 2026 | Git, historial, arqueo y sistema en línea |
| 4. Mejoras funcionales | 15 de octubre de 2026 | PDF, finanzas automáticas, tarifa por lectura y carga rápida |
| 5. Plomero y app móvil | 18 de octubre de 2026 | App instalable, trabajo sin señal, observaciones y fotos |
| 6. Respaldos, avisos e IA | 20 de octubre de 2026 | Respaldos, WhatsApp, notificaciones y asistente de ayuda |

**Calidad:** 231 pruebas automáticas (1145 verificaciones) se ejecutan en cada cambio con GitHub Actions. La lista completa de cambios está en `git log`.
