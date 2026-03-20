<p align="center">
  <img src="https://webfiable.com/wp-content/uploads/2025/01/icon2.png" alt="Webfiable" width="60">
</p>
<h1 align="center">Webfiable Info</h1>

<p align="center">
  <strong>El plugin que conecta tu WordPress con el servicio de seguridad de <a href="https://webfiable.com">Webfiable</a></strong>
</p>

<p align="center">
  <a href="https://webfiable.com">Web</a> ·
  <a href="https://app.webfiable.com">Evaluación rápida</a> ·
  <a href="https://wordpress.org/plugins/webfiable-info/">Plugin en WordPress.org</a>
</p>

<p align="center">
  <img src="https://img.shields.io/wordpress/plugin/v/webfiable-info?label=versi%C3%B3n&color=blue" alt="Versión">
  <img src="https://img.shields.io/wordpress/plugin/tested/webfiable-info?label=probado%20hasta%20WP&color=green" alt="Probado hasta WordPress">
  <img src="https://img.shields.io/badge/licencia-GPL%20v3-blue" alt="Licencia">
</p>

---

## Qué es Webfiable Info

Webfiable Info es un plugin ligero y respetuoso con la privacidad que registra tu sitio en [Webfiable](https://webfiable.com) y expone un inventario mínimo de software, versión de WordPress, plugins y temas instalados, y metadatos básicos, a través de un endpoint cifrado. A cambio, recibes por correo un informe completo y resúmenes periódicos con recomendaciones accionables de seguridad.

El proyecto está en fase beta pública. No hay costo, ni suscripción, ni registro externo: todo se gestiona desde el panel de ajustes del plugin. Si en el futuro se requiere suscripción, se avisará con antelación.

## Características

- **Registro en un clic:** introduce un email para los informes, da tu consentimiento y activa el endpoint. El plugin lo verifica y completa el registro automáticamente.
- **Endpoint opt-in:** el endpoint público `/webfiable` viene desactivado por defecto. Si la verificación o el registro fallan, se desactiva de forma segura.
- **Consentimiento reversible:** desactivar el consentimiento guarda tu preferencia y apaga el endpoint al instante. Puedes reactivarlo cuando quieras.
- **Ligero por diseño:** sin procesos pesados en segundo plano. El endpoint responde bajo demanda en milisegundos.
- **Seguro por defecto:** cifrado híbrido AES-256-CBC + RSA-2048 protege cada transmisión.
- **Parte del ecosistema Webfiable:** más información en [webfiable.com](https://webfiable.com).

## Seguridad

Webfiable Info está diseñado para que no tengas que confiar a ciegas:

- **Cifrado híbrido:** el inventario se cifra con AES-256-CBC; la clave AES se protege con RSA-2048. Solo Webfiable puede descifrar el contenido.
- **IV único por respuesta:** cada respuesta genera un nuevo vector de inicialización, garantizando que el cifrado sea siempre distinto.
- **Endpoint público, contenido privado:** el endpoint `/webfiable` es accesible, pero el payload solo lo descifra Webfiable.
- **Limitación por IP:** protección básica contra abuso mediante rate limiting.

## Instalación y configuración

1. Instala el plugin (subida de ZIP o desde código fuente).
2. Actívalo en WordPress.
3. Ve a **Ajustes → Webfiable Info**.
4. Introduce el correo del destinatario del informe y marca la casilla de consentimiento.
5. Activa el endpoint `/webfiable` y haz clic en **Guardar ajustes**.
6. El plugin verifica el endpoint y completa el registro. Si la verificación falla, un aviso te indica qué corregir y el endpoint se desactiva de forma segura.

## Preguntas frecuentes

### ¿Necesito una suscripción a Webfiable?
No durante la beta pública. El plugin registra tu sitio automáticamente y el servicio es gratuito. Si se introduce una suscripción en el futuro, recibirás aviso previo y una ruta clara de actualización. Consulta novedades en [webfiable.com](https://webfiable.com).

### ¿Cómo se protege mi información?
Los datos se cifran en tu sitio antes de cualquier transmisión con AES-256-CBC. La clave AES se cifra con RSA-2048, de modo que solo Webfiable puede leer el contenido.

### ¿Qué información se recopila?
Solo un inventario mínimo: URL del sitio, versión de WordPress, plugins y temas instalados (nombre, slug, versión, descripción breve), un identificador del sitio, marca de tiempo de consentimiento y el correo que proporcionas para los informes. Sin contenido de usuarios ni credenciales.

### ¿Qué pasa si desactivo el consentimiento?
Tu preferencia se guarda de inmediato y el endpoint `/webfiable` se apaga. Puedes reactivar el consentimiento y el endpoint en cualquier momento desde Ajustes.

### ¿Por qué podría fallar el registro?
El plugin verifica el endpoint antes de registrar. Si tu servidor bloquea peticiones loopback, los enlaces permanentes están mal configurados o falta la extensión PHP OpenSSL, la verificación puede fallar. Corrige el problema y pulsa **Guardar ajustes** de nuevo, el plugin reintentará.

## Contribuir

Issues y PRs son bienvenidos. Mantén los cambios enfocados y coherentes con el estilo de código existente.

## Licencia

GPL v3 o posterior. Consulta la [licencia completa](https://www.gnu.org/licenses/gpl-3.0.html).

## Changelog

Consulta el [changelog completo en WordPress.org](https://wordpress.org/plugins/webfiable-info/#developers).

---

<p align="center"><sub>Proyecto personal en fase beta · No comercial · Hecho con curiosidad y café ☕</sub></p>