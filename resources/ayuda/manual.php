<?php

/*
 * Manual de uso de H2O Control para el asistente de Ayuda.
 *
 * Cada sección: titulo, roles que la ven (socio, plomero, admin = Hacienda,
 * superadmin = presidente; "todos" = cualquiera), claves (palabras con que
 * la gente suele preguntar) y texto. El asistente responde SOLO con esto.
 * Al cambiar una pantalla del sistema, actualizar aquí su sección.
 */

$gestion = ['admin', 'superadmin'];

return [

    // ================= GENERAL =================

    [
        'titulo' => 'Ingresar al sistema',
        'roles' => ['todos'],
        'claves' => 'entrar login iniciar sesion acceder correo usuario clave',
        'texto' => 'Abre la página del sistema (o la app instalada con el ícono de la gota 💧), escribe tu correo y tu contraseña y toca **INGRESAR AL SISTEMA**. Cada persona entra a su pantalla: el socio a **Mi Consumo**, el plomero a **Carga de Lecturas**, Hacienda a **Lecturas de Agua** y el presidente a **Socios**. El ojito 👁️ del campo de contraseña muestra lo que vas escribiendo.',
    ],
    [
        'titulo' => 'Olvidé mi contraseña',
        'roles' => ['todos'],
        'claves' => 'olvide perdi no recuerdo contraseña clave recuperar restablecer',
        'texto' => 'El sistema no envía correos para recuperar la contraseña. Pídela a la directiva: el presidente entra a **Socios**, toca **🔑 Clave** en tu fila y te genera una contraseña temporal (por ejemplo `H2O-4821-7305`) que puede enviarte por WhatsApp. Al ingresar con ella, el sistema te pedirá elegir una nueva.',
    ],
    [
        'titulo' => 'Contraseña temporal y cambiar mi contraseña',
        'roles' => ['todos'],
        'claves' => 'cambiar contraseña clave nueva temporal perfil seguridad',
        'texto' => 'Si te dieron una contraseña temporal, al ingresar el sistema te lleva a **Mi Perfil** y no te deja usar nada más hasta cambiarla. Para cambiarla en cualquier momento: menú → **Mi Perfil** → sección **Cambiar Contraseña**: escribe la actual, la nueva (mínimo 8 caracteres) y confírmala, y toca **Guardar**.',
    ],
    [
        'titulo' => 'Mi perfil: nombre y correo',
        'roles' => ['todos'],
        'claves' => 'perfil nombre correo email datos cambiar mis datos',
        'texto' => 'En el menú → **Mi Perfil** puedes cambiar tu nombre y tu correo. El carnet, el teléfono, la zona y el rol los registra el presidente en **Socios**; si alguno está mal, pídele que lo corrija.',
    ],
    [
        'titulo' => 'Instalar la app en el celular',
        'roles' => ['todos'],
        'claves' => 'instalar app aplicacion descargar celular icono pantalla inicio android iphone',
        'texto' => 'Es la misma página del sistema con un ícono en tu celular. **Android (Chrome):** toca **Instalar** en el aviso azul de arriba, o menú ☰ → **📲 Instalar app**, o el menú ⋮ de Chrome → *Instalar aplicación*. **iPhone (Safari):** Compartir ⬆️ → *Agregar a inicio* → *Agregar*. Es una sola app para todos: cada uno ve sus módulos según con quién inicie sesión. Si dice **✅ App instalada**, ya la tienes.',
    ],
    [
        'titulo' => 'Borré la app sin querer',
        'roles' => ['todos'],
        'claves' => 'borre elimine desinstale app perdi icono reinstalar',
        'texto' => 'No pierdes nada: tus datos están en el sistema, no en el celular. Abre el sistema en Chrome y vuelve a instalarla desde menú ☰ → **📲 Instalar app** (o el menú ⋮ de Chrome → *Instalar aplicación*). En iPhone: Safari → Compartir ⬆️ → *Agregar a inicio*.',
    ],
    [
        'titulo' => 'Avisos en el celular (notificaciones)',
        'roles' => ['todos'],
        'claves' => 'avisos notificaciones alertas activar celular recibir aviso lectura',
        'texto' => 'En **Mi Consumo** (socios) o en **Mi Perfil** está la tarjeta **🔔 Avisos en este celular**: toca **Activar avisos** y acepta el permiso. Cuando se registre tu lectura te llegará un aviso aunque la app esté cerrada. Con **Probar** recibes un aviso de prueba y con **Desactivar** los apagas. En iPhone primero hay que instalar la app. Si la tarjeta dice que no están disponibles, avisa a la directiva.',
    ],
    [
        'titulo' => 'Cerrar sesión',
        'roles' => ['todos'],
        'claves' => 'salir cerrar sesion desconectar otro usuario cambiar de usuario',
        'texto' => 'Menú (☰ en el celular, o tu nombre arriba a la derecha) → **Cerrar Sesión**. Si eres plomero y tienes lecturas guardadas en el celular sin enviar, el sistema te avisa antes de salir.',
    ],

    [
        'titulo' => 'Qué es H2O Control y qué puedo preguntar',
        'roles' => ['todos'],
        'claves' => 'que es sistema h2o ayuda asistente puedes hacer para que sirve',
        'texto' => 'H2O Control es el sistema de la OTB para el servicio de agua potable: registra las lecturas de los medidores, calcula el consumo y lo que paga cada socio, controla cobros, multas y finanzas. Puedes preguntarme cómo hacer algo en el sistema (pagar, ver recibos, cargar lecturas, etc.) o dudas básicas sobre el servicio de agua.',
    ],
    [
        'titulo' => 'Conceptos básicos: medidor, lectura, m³ y consumo',
        'roles' => ['todos'],
        'claves' => 'medidor lectura metro cubico m3 litros consumo que significa como leer numeros',
        'texto' => 'El **medidor** cuenta el agua que entra a tu casa. La **lectura** es el número que marca, en metros cúbicos (**m³**); 1 m³ son 1.000 litros. El **consumo** del mes es la lectura actual menos la del mes anterior. Para leer el medidor se toman los números negros (m³ enteros); los rojos o las agujas son fracciones.',
    ],
    [
        'titulo' => 'Cómo saber si tengo una fuga de agua',
        'roles' => ['todos'],
        'claves' => 'fuga perdida goteo detectar consumo alto agua se escapa revisar',
        'texto' => 'Cierra todas las llaves y no uses agua por 30 a 60 minutos. Anota la lectura del medidor antes y después: si los números avanzaron, hay una fuga. Revisa inodoros (agua que corre), llaves que gotean y cañerías con humedad. Si la fuga está antes del medidor o no la encuentras, avisa a la directiva o al plomero.',
    ],
    [
        'titulo' => 'Consejos para ahorrar agua',
        'roles' => ['todos'],
        'claves' => 'ahorrar agua consejos gastar menos consumo bajar cuenta',
        'texto' => 'Repara goteras e inodoros que pierden agua; cierra la llave al cepillarte o enjabonarte; usa balde en vez de manguera para lavar el auto o el patio; riega temprano o al atardecer; junta ropa para lavar con carga completa. Un inodoro que pierde puede gastar varios m³ al mes.',
    ],

    // ================= SOCIO =================

    [
        'titulo' => 'Ver mi consumo y lo que debo',
        'roles' => ['socio'],
        'claves' => 'cuanto debo deuda consumo lecturas mes pagar pendiente mi consumo',
        'texto' => 'Entra a **Mi Consumo**: cada mes muestra la lectura anterior y actual de tu medidor, tu consumo en m³, el **total a pagar** y el estado (🛑 Pendiente o ✅ Pagado). Las que dicen Pendiente son las que debes.',
    ],
    [
        'titulo' => 'Cómo se calcula lo que pago',
        'roles' => ['socio'],
        'claves' => 'por que pago tanto calculo tarifa monto metro cubico m3 cobro caro',
        'texto' => 'Monto = **consumo × tarifa por m³**. El consumo es la lectura actual menos la anterior de tu medidor. Ejemplo: de 1512 a 1527 son 15 m³; con tarifa de Bs. 2,00 pagas Bs. 30,00. Cada lectura conserva la tarifa que regía cuando se registró. Si crees que tu lectura está mal, consulta a Hacienda.',
    ],
    [
        'titulo' => 'Pagar con QR',
        'roles' => ['socio'],
        'claves' => 'pagar qr codigo transferencia banco pago movil',
        'texto' => 'En **Mi Consumo** toca **🖼️ Mostrar Código QR** y escanéalo con la app de tu banco por el monto que debes. Después envía tu comprobante al Secretario de Hacienda (el número está en Mis Pagos) para que confirme el pago. El QR solo se muestra mientras está vigente.',
    ],
    [
        'titulo' => 'Pagar en efectivo',
        'roles' => ['socio'],
        'claves' => 'pagar efectivo dinero en persona caja hacienda',
        'texto' => 'Paga directamente al Secretario de Hacienda de la OTB. Él registra tu pago en el sistema y te puede entregar el recibo; luego lo verás en **Mis Pagos**.',
    ],
    [
        'titulo' => 'Mis pagos y mis recibos',
        'roles' => ['socio'],
        'claves' => 'recibo comprobante mis pagos historial pdf descargar constancia',
        'texto' => 'Menú → **Mis Pagos**: lista tus pagos de agua y multas con fecha, método y quién los registró. En cada pago toca **📄 Recibo** para descargarlo en PDF, o **Historial en PDF** para todo el periodo filtrado. Si pagaste por QR y no aparece, envía tu comprobante a Hacienda para que lo confirme.',
    ],
    [
        'titulo' => 'Foto de mi medidor',
        'roles' => ['socio'],
        'claves' => 'foto medidor imagen ver foto observacion',
        'texto' => 'Si el plomero anotó una observación con foto (por ejemplo, posible fuga), en **Mi Consumo** verás **📷 Ver foto del medidor** junto a esa lectura.',
    ],
    [
        'titulo' => 'Mis multas',
        'roles' => ['socio'],
        'claves' => 'multa sancion multas inasistencia pagar multa',
        'texto' => 'Menú → **Multas y Sanciones** muestra solo tus multas: motivo, monto, fecha y estado. Se pagan igual que el agua (QR o efectivo a Hacienda) y, una vez pagadas, tienen su recibo en PDF.',
    ],
    [
        'titulo' => 'Finanzas de la OTB (transparencia)',
        'roles' => ['socio'],
        'claves' => 'finanzas balance ingresos gastos egresos caja cuentas transparencia',
        'texto' => 'Menú → **Finanzas**: puedes ver el balance de cada mes de la OTB (agua cobrada, multas, otros ingresos, egresos y saldo) y los comprobantes de respaldo.',
    ],
    [
        'titulo' => 'Mensajes de la directiva',
        'roles' => ['socio'],
        'claves' => 'mensajes mensaje comunicado comunicados aviso anuncio directiva asamblea reunion noticia',
        'texto' => 'Menú → **Mensajes**: ahí ves los comunicados de la directiva (asambleas, cortes de agua, faenas, etc.). Si hay mensajes nuevos, el menú muestra un número rojo. Si activaste los **avisos en el celular**, también te llega una notificación.',
    ],
    [
        'titulo' => 'Si no pago a tiempo',
        'roles' => ['socio'],
        'claves' => 'no pago atraso mora corte cortar agua deuda meses',
        'texto' => 'Las deudas se acumulan mes a mes. Con varios meses sin pagar (normalmente 3) la OTB puede avisarte el **corte del servicio**. Para evitarlo, paga lo pendiente o consulta a Hacienda.',
    ],
    [
        'titulo' => 'Creo que mi lectura está mal',
        'roles' => ['socio'],
        'claves' => 'reclamo lectura mal error consumo alto no gaste equivocado',
        'texto' => 'Revisa en **Mi Consumo** la lectura anterior y la actual y compáralas con tu medidor. Si no coinciden, comunícate con Hacienda: solo Hacienda puede corregir una lectura. Si hay foto del medidor, sirve como respaldo.',
    ],

    // ================= PLOMERO =================

    [
        'titulo' => 'Cargar las lecturas del mes',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'cargar registrar anotar lectura medidor planilla recorrido mes',
        'texto' => 'Abre **Carga de Lecturas** (o, en Hacienda, Lecturas → **📋 Carga rápida del mes**). Elige el mes y el año. En cada socio escribe la **lectura actual** del medidor: la anterior la completa el sistema. Enter pasa al siguiente socio. Al terminar toca **💾 Guardar lecturas** y confirma. Puedes guardar de a tandas; los que ya están registrados aparecen con ✅.',
    ],
    [
        'titulo' => 'Cargar lecturas sin señal',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'sin señal sin internet offline sin datos conexion enviar despues',
        'texto' => 'Antes de salir, abre la planilla del mes **con señal** (queda guardada en el celular). En la calle anota normalmente: lo escrito se guarda en el celular. Si tocas Guardar sin señal verás *📶 Sin señal: N lecturas guardadas en este celular*; se envían solas cuando vuelva la señal (o toca **Enviar ahora**). No cierres sesión con lecturas pendientes.',
    ],
    [
        'titulo' => 'Primera lectura de un socio (lectura inicial)',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'primera lectura inicial socio nuevo medidor nuevo anterior vacia',
        'texto' => 'Si el socio nunca tuvo lecturas, la casilla **Lectura anterior** aparece en amarillo con *Inicial*: escribe lo que marcaba el medidor al empezar y luego la lectura actual.',
    ],
    [
        'titulo' => 'La lectura actual es menor que la anterior',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'error menor anterior rojo no deja guardar lectura invalida',
        'texto' => 'El sistema no acepta una lectura menor que la del mes anterior (se marca en rojo). Vuelve a mirar el medidor. Si el medidor se cambió o se reinició, anótalo en la observación y avisa a Hacienda para corregirlo.',
    ],
    [
        'titulo' => 'Aviso de consumo muy alto',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'consumo alto anormal advertencia fuga triangulo amarillo',
        'texto' => 'Si el consumo es más de 3 veces el promedio del socio (y más de 10 m³) aparece ⚠️ en amarillo. Puede ser una fuga o un error al anotar: revisa el medidor y, si es real, anota una observación (por ejemplo *Posible fuga*). Igual se puede guardar.',
    ],
    [
        'titulo' => 'Anotar observaciones y foto de lo observado',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'observacion nota comentario foto camara medidor dañado fuga empañado',
        'texto' => 'Debajo de cada socio está **📝 Observación (opcional)**, con sugerencias como *Medidor dañado* o *Posible fuga*. Al escribirla aparece **📷 Foto de lo observado**, que abre la cámara. La observación y la foto se guardan junto con la lectura (hace falta anotar la lectura) y las ve Hacienda.',
    ],
    [
        'titulo' => 'Orden del recorrido y zonas',
        'roles' => ['plomero', 'admin', 'superadmin'],
        'claves' => 'orden recorrido zona calle buscar socio filtrar faltan',
        'texto' => 'La planilla está agrupada por **zona** y ordenada por el número de recorrido de cada socio. Usa el buscador (nombre o C.I.), el filtro de zona y **Mostrar solo los que faltan**. El orden y la zona los define el presidente en la ficha de cada socio.',
    ],
    [
        'titulo' => 'Corregir una lectura ya registrada',
        'roles' => ['plomero'],
        'claves' => 'corregir editar cambiar lectura registrada equivoque',
        'texto' => 'El plomero no puede modificar lecturas ya guardadas: avisa a Hacienda indicando el socio, el mes y la lectura correcta para que la corrija.',
    ],

    // ================= HACIENDA (admin) =================

    [
        'titulo' => 'Registrar o corregir una lectura individual',
        'roles' => $gestion,
        'claves' => 'nueva lectura registrar editar corregir lectura individual borrar',
        'texto' => '**Lecturas de Agua** → **+ Registrar Nueva Lectura**: elige el socio, el mes y la lectura actual (la anterior se completa sola). Para corregir, toca **Editar** en la fila; si corriges un mes y el siguiente aún no está cobrado, el siguiente se ajusta solo. Un mes ya cobrado no puede quedar inconsistente con el siguiente.',
    ],
    [
        'titulo' => 'Cobrar una lectura (confirmar pago)',
        'roles' => $gestion,
        'claves' => 'cobrar confirmar pago registrar pago efectivo qr pagado',
        'texto' => 'En **Lecturas de Agua**, en la fila pendiente toca **Confirmar Pago** y elige **QR** o **Efectivo**. Queda guardado el monto, la fecha y quién cobró; se suma solo a Finanzas y al Arqueo. Luego aparece el botón **📄 Recibo** para descargarlo en PDF.',
    ],
    [
        'titulo' => 'Avisar a los socios por WhatsApp',
        'roles' => $gestion,
        'claves' => 'avisar whatsapp mensaje notificar socios lectura cobro recordatorio',
        'texto' => 'Lecturas → **📲 Avisar a los socios** (o desde la planilla después de cargar el mes). Cada botón abre WhatsApp con el mensaje escrito (lectura, consumo, monto, deudas anteriores y cómo pagar): solo toca Enviar. Queda marcado ✅ Avisado. Los socios sin celular aparecen como *Avisar en persona*. Para deudores, usa también **Reportes**.',
    ],
    [
        'titulo' => 'Enviar un mensaje a todos los socios',
        'roles' => $gestion,
        'claves' => 'mensaje mensajes comunicado comunicar avisar todos socios anuncio asamblea corte reunion',
        'texto' => 'Menú → **Mensajes**: escribe el **título** y el **mensaje** y toca **Enviar a todos los socios**. Marca **⚠️ Importante** para avisos urgentes (corte de agua, emergencia). Los socios lo ven en su módulo Mensajes con un aviso en el menú y, si activaron los avisos, les llega una notificación al celular. Puedes **🗑 Eliminar** un mensaje enviado por error.',
    ],
    [
        'titulo' => 'Multas: registrar, cobrar y condonar',
        'roles' => $gestion,
        'claves' => 'multa registrar multa masiva varios socios cobrar condonar perdonar',
        'texto' => '**Multas y Sanciones** → **➕ Registrar Nueva Multa**: individual o **masiva** (marca varios socios), elige la tarifa o *Otro* con motivo. Para cobrar toca **Pagar** y elige QR o efectivo. Para condonarla, **Editar** → estado **🔵 Condonado**. Las multas cobradas se suman solas a Finanzas.',
    ],
    [
        'titulo' => 'Tarifas de multas',
        'roles' => $gestion,
        'claves' => 'tarifa multa monto multa tipos de multa configurar',
        'texto' => 'En **Multas y Sanciones** toca **⚙️ Tarifas de multas** para crear los tipos de multa con su monto predeterminado (por ejemplo, inasistencia a asamblea). Al registrar una multa se elige una de ellas.',
    ],
    [
        'titulo' => 'Finanzas: balance del mes',
        'roles' => $gestion,
        'claves' => 'finanzas balance registrar balance ingresos egresos gastos comprobante saldo',
        'texto' => 'Desde octubre 2026 el **agua cobrada** y las **multas cobradas** se suman solas al balance del mes. En **Finanzas** → **Registrar Balance Mensual** (o **Editar** si ya existe) carga solo **Otros ingresos**, **Egresos**, el detalle y el comprobante. Saldo = agua + multas + otros − egresos. **🔎 Ver detalle de cobros** abre el arqueo del mes.',
    ],
    [
        'titulo' => 'Arqueo de caja y acta en PDF',
        'roles' => $gestion,
        'claves' => 'arqueo caja cierre mes efectivo qr acta pdf firmar rendicion',
        'texto' => '**Finanzas** → **🧾 Arqueo mensual**: lo cobrado en el mes por QR y en efectivo, con el detalle de cada cobro. **Acta en PDF** genera el acta con el efectivo que debe haber en caja y espacio para las firmas de Hacienda y Presidencia.',
    ],
    [
        'titulo' => 'QR de pago de la OTB',
        'roles' => $gestion,
        'claves' => 'subir qr cambiar qr vencimiento qr pago codigo vence',
        'texto' => 'En **Finanzas**, sección **QR de Pago de la OTB**: elige la imagen del QR, su fecha de vencimiento y toca **Guardar QR**. Los socios lo ven en Mi Consumo mientras esté vigente; el sistema avisa 7 días antes de que venza.',
    ],
    [
        'titulo' => 'Reportes y morosos',
        'roles' => $gestion,
        'claves' => 'reportes morosos deudores deuda recaudacion corte aviso de corte',
        'texto' => '**Reportes** muestra lo cobrado y lo pendiente por gestión y mes y la **lista de deudores**. En cada deudor, el botón de WhatsApp abre un recordatorio o, desde 3 meses de deuda, un **aviso de corte** ya redactado.',
    ],
    [
        'titulo' => 'Exportar a Excel',
        'roles' => $gestion,
        'claves' => 'excel exportar descargar lista csv planilla',
        'texto' => 'Las listas (Lecturas, Multas, Finanzas, Reportes, Arqueo, Historial, Socios) tienen el botón **📥 Exportar a Excel**, que descarga lo que estás viendo con los filtros aplicados.',
    ],
    [
        'titulo' => 'Historial de actividades',
        'roles' => $gestion,
        'claves' => 'historial actividades auditoria quien hizo registro cambios',
        'texto' => '**Historial** registra quién hizo qué y cuándo (lecturas, cobros, multas, avisos, respaldos, ingresos al sistema). Se puede filtrar y exportar. Solo el presidente puede borrar registros.',
    ],
    [
        'titulo' => 'Respaldos (copia de seguridad)',
        'roles' => $gestion,
        'claves' => 'respaldo backup copia seguridad descargar base de datos',
        'texto' => '**Respaldos** → escribe tu contraseña → **📥 Descargar respaldo**: baja a tu dispositivo un archivo con todo el sistema. Guárdalo fuera del servidor (otra computadora, pendrive o nube personal) y hazlo al menos una vez por semana. Restaurar un respaldo solo puede hacerlo el presidente.',
    ],

    // ================= PRESIDENTE (superadmin) =================

    [
        'titulo' => 'Registrar y editar socios',
        'roles' => ['superadmin'],
        'claves' => 'registrar socio nuevo usuario crear editar dar de baja eliminar socio',
        'texto' => '**Socios** → **Registrar Nuevo Socio**: nombre (solo letras), C.I., celular de 8 dígitos, correo, contraseña temporal, zona y n.º de recorrido, y rol. Para corregir toca **Editar**; **Eliminar** da de baja al socio sin borrar su historial de lecturas y pagos.',
    ],
    [
        'titulo' => 'Roles del sistema',
        'roles' => ['superadmin'],
        'claves' => 'rol roles permisos administrador hacienda plomero socio presidente',
        'texto' => '**Socio**: ve su consumo, pagos, multas y finanzas. **Plomero**: solo carga lecturas. **Administrador (Hacienda)**: lecturas, cobros, multas, finanzas, reportes, historial y respaldos. **Superadministrador (presidente)**: todo, más socios, tarifa del agua y restaurar respaldos.',
    ],
    [
        'titulo' => 'Restablecer la contraseña de un socio',
        'roles' => ['superadmin'],
        'claves' => 'restablecer clave contraseña temporal socio olvido 🔑',
        'texto' => 'En **Socios** toca **🔑 Clave** en la fila del socio y confirma. Se muestra una sola vez una contraseña temporal con el botón **📲 Enviar por WhatsApp**. El socio deberá cambiarla al ingresar.',
    ],
    [
        'titulo' => 'Cambiar la tarifa del agua',
        'roles' => ['superadmin'],
        'claves' => 'tarifa agua precio metro cubico m3 cambiar tarifa subir',
        'texto' => 'Menú **Tarifa** → escribe la nueva tarifa por m³ → **Guardar nueva tarifa**. Se aplica a las lecturas que se registren desde ese momento; las ya registradas conservan su precio. Queda el historial de cambios.',
    ],
    [
        'titulo' => 'Restaurar un respaldo',
        'roles' => ['superadmin'],
        'claves' => 'restaurar recuperar respaldo backup volver datos',
        'texto' => '**Respaldos** → sección **♻️ Restaurar**: elige el archivo, escribe **RESTAURAR** y tu contraseña. Reemplaza todos los datos por los del archivo (si algo falla no cambia nada) y cierra la sesión. Antes descarga un respaldo de lo actual.',
    ],
];
