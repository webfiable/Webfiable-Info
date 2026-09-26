# Lista de publicación de Webfiable Análisis de Sitios

Esta lista se recorre entera, en orden, cada vez que se publica una versión del plugin en
wordpress.org. Un paso no empieza hasta que el anterior está comprobado. Está escrita a partir de la
publicación de 2.2.0 (sprint 8); la columna «Verificado en 2.2.0» dice qué se midió en esa versión y
dónde está la prueba.

Tres hechos que conviene tener delante antes de empezar:

- **Una etiqueta `vX.Y.Z` publica, y publicar es irreversible.** wordpress.org nunca retira una
  versión publicada. Todo lo que se pueda comprobar se comprueba antes del paso 8.
- **Hoy la publicación está protegida solo por proceso.** Nada en GitHub impide que alguien con
  permiso de escritura en `webfiable/Webfiable-Info`, incluido el token del VPS, empuje una etiqueta y
  publique, o lea la contraseña de SVN desde un flujo en cualquier rama: no hay entorno protegido, los
  secretos `WPORG_USERNAME`/`WPORG_PASSWORD` son de repositorio y `main` no tiene protección. La única
  guarda es el paso 7 (el «Ship» de Fernando). Así seguirá hasta que se haga la tarea **S8-T1**
  (`POST-RELEASE-TODO.md`: entorno `wordpress-org` con Fernando como revisor obligatorio, solo
  etiquetas `v*.*.*`, los secretos dentro del entorno y `environment: wordpress-org` en el trabajo
  `deploy`), prevista antes de la siguiente publicación.
- **Las comprobaciones son de `bin/release_checks.py`** (Python 3.10 o posterior, sin dependencias):
  `coherence`, `zip`, `i18n`, `php74`, `samezip` y `selftest`, que demuestra que cada comprobación sabe
  fallar. PHP, Composer y gettext no hacen falta en la máquina: los pone CI.

## Resumen

| # | Paso | Quién | Comprobación | Verificado en 2.2.0 |
|---|---|---|---|---|
| 1 | La pregunta del número mayor | quien prepara la versión | la promesa, abajo; para una versión mayor, SiteAudit en producción con el protocolo nuevo | 2.2.0 es menor: la llamada de registro es la misma (puerta de dev, F6: los cinco elementos de identidad iguales en S0, F1 y F1b); la puerta de SiteAudit probada en dev (F8a, F8b) |
| 2 | Versión en los tres sitios, changelog y aviso de actualización | quien prepara la versión | `python3 bin/release_checks.py coherence` | `coherence: OK` en `ccf720a`; aviso de 2.2.0 de 248 caracteres (máximo 300) |
| 3 | «Tested up to» releído | quien prepara la versión | `coherence` lo compara con la versión actual de WordPress | `Tested up to: 7.1` contra WordPress `7.1.2` |
| 4 | CI en verde en `main` | CI | los tres trabajos de `ci.yml` en verde tras la fusión | en la cabeza de la PR, ejecución 36176053051 (`ccf720a`) en verde; en `main`, pendiente de la fusión |
| 5 | Zip de CI descargado, abierto y leído | quien prepara la versión | `release_checks.py zip` sobre el artefacto `webfiable-info-zip` | 20 ficheros, ninguno de desarrollo; zip de `ccf720a` sha256 `0d5cbb23bf93288249095244a272427562e2f54a57e66f46ba1566b3ea52a311`, instalado en el WordPress de pruebas |
| 6 | Commits fijados de las acciones, releídos | quien prepara la versión | tabla del paso 6 contra las líneas `uses:` de los dos flujos | 20 de 20 líneas `uses:` fijadas a un commit completo; tabla leída el 2026-09-24 21:28:57Z (`download-artifact`, añadida después, el 2026-09-25) |
| 7 | «Ship» de Fernando para la etiqueta | Fernando | palabra explícita, nombrando la versión | pendiente |
| 8 | Etiqueta `vX.Y.Z` sobre `main` | quien tenga el «Ship» | `verify`, `deploy` y `compare` de `release.yml` en verde | pendiente |
| 9 | Ventana de vigilancia, con el paquete que sirve wordpress.org | quien publicó | lecturas del paso 9 | pendiente; la prueba de que la comprobación del paquete servido sabe fallar ya está hecha (2.1.1, abajo) |
| 10 | Retroceso: qué palanca hay y quién la tiene | Fernando | leer el paso 10 antes del paso 8 | escrito; tiempo real de una publicación: <medido en SPRINT-8-SHIP.md> |

## 1. La pregunta del número mayor

¿Cambia el protocolo entre el plugin y la API? Entonces es versión mayor. ¿No cambia? Entonces nunca
lo es. El protocolo es el sobre, sus campos, el cifrado y la llamada de registro.

> **La promesa del número mayor.** El número mayor de la versión del plugin es el contrato del
> protocolo entre el plugin y la API. La API solo deja de analizar un sitio —y su ficha en el panel
> dice «Actualiza el plugin»— cuando el número mayor instalado es menor que el de la última versión
> publicada en wordpress.org. Una diferencia dentro del mismo número mayor se analiza con normalidad, y
> la ficha lo dice con una línea discreta, «Hay una versión nueva del plugin.», sin retener nada. Por
> eso cualquier cambio de protocolo —el sobre, sus campos, el cifrado o el registro— se publica como
> versión mayor, y una versión mayor no se publica por ningún otro motivo. Si wordpress.org no contesta
> o su versión no se puede leer, no se aplica ninguna puerta.

Para una versión mayor, antes de seguir: **¿está SiteAudit en producción aceptando el protocolo
nuevo?** Si no lo está, se para aquí. Primero va el cambio de SiteAudit a producción, y solo después
la etiqueta. Antes de la primera versión mayor hay además una tarea abierta, **S8-T4**: la ficha de un
sitio rechazado por número mayor inferior muestra el veredicto rojo «Vulnerable» al lado de «Actualiza
el plugin».

La regla vive en SiteAudit (`WebfiablePluginService` y `VersionsHelper`) y está escrita también en su
`README.md`. Si esta lista y ese README dicen cosas distintas, se para y se aclara antes de publicar.

**Verificado en 2.2.0.** 2.2.0 no cambia el protocolo. La puerta de dev del sprint 8
(`SPRINT-8-DEVGATE.md`, 13 de 13 flujos en verde) comparó la llamada de registro al guardar los ajustes
y la llamada tras actualizar: método, URL, sha256 del cuerpo, claves, tiempo de espera y tipo de
contenido son iguales (F6). La misma puerta probó la regla en SiteAudit de dev. Un sitio con 2.1.2
frente a 2.2.0 publicada se analiza, con estado 4 y la línea discreta una vez (F8a). Frente a una
3.0.0 simulada, el mismo sitio recibe `UpgradeRequired`, estado 3 y «Actualiza el plugin» (F8b).

## 2. La versión en los tres sitios, el changelog y el aviso de actualización

La versión va en la cabecera de `webfiable-info.php` (`Version:`), en `includes/constants.php`
(`WEBFIABLE_INFO_VERSION`) y en `readme.txt` (`Stable tag:`). El readme lleva `= X.Y.Z =` bajo
`== Changelog ==` y bajo `== Upgrade Notice ==`, y el aviso no pasa de 300 caracteres.

```
python3 bin/release_checks.py coherence
```

`coherence` falla si las tres versiones no coinciden, si falta la entrada del changelog o del aviso, si
el aviso pasa de 300 caracteres, si el nombre del plugin o «Requires at least» y «Requires PHP»
difieren entre la cabecera y el readme, o si faltan en la cabecera. CI lo ejecuta en cada PR; aquí se
ejecuta a mano para leer su salida.

**Verificado en 2.2.0.** En `ccf720a`: `header Version: 2.2.0; WEBFIABLE_INFO_VERSION: 2.2.0; readme
Stable tag: 2.2.0`. Además: `Requires at least` 5.3 y `Requires PHP` 7.4 en la cabecera y en el readme;
aviso de 2.2.0 de 248 caracteres; `coherence: OK`.

## 3. «Tested up to», releído

`coherence` lee la primera oferta de `https://api.wordpress.org/core/version-check/1.7/` y falla si el
major.minor de `Tested up to` es menor. Si la red falla, falla también, en voz alta, en vez de dejar
pasar. Si WordPress ha publicado una versión nueva desde la última PR, `readme.txt` cambia por una PR
nueva, con su CI, antes de la etiqueta. `release.yml` ejecuta `coherence --tag` y rechazaría la
etiqueta de todos modos.

**Verificado en 2.2.0.** `readme Tested up to: 7.1; current WordPress: 7.1.2`.

## 4. CI en verde en `main`

Tras fusionar la PR, `ci.yml` se ejecuta sobre `main`. Sus tres trabajos tienen que estar en verde:

- `checks`: coherencia; autoprueba; traducción inglesa frente a las cadenas españolas; pruebas
  unitarias en PHP 8.3; phpcs; y que phpcs examinó tantos ficheros PHP como publica el paquete.
- `php74`: PHP 7.4 analiza cada fichero PHP que se publica, con la prueba de que el análisis rechaza
  sintaxis de PHP 8.0; ninguna función solo de PHP 8.0; pruebas unitarias en PHP 7.4.
- `package`: el zip que publicaría el despliegue, construido por la acción de 10up en modo de prueba,
  inspeccionado fichero a fichero y pasado por Plugin Check.

```
GH_TOKEN="$(~/webfiable/appbuilder/bin/secret get --raw TOKEN:webfiable-info)" \
  gh run list --repo webfiable/Webfiable-Info --workflow ci.yml --branch main --limit 1
GH_TOKEN="$(~/webfiable/appbuilder/bin/secret get --raw TOKEN:webfiable-info)" \
  gh run view <id> --repo webfiable/Webfiable-Info
```

**Verificado en 2.2.0.** En la cabeza de la PR, ejecución 36176053051 sobre `ccf720a`, los tres trabajos
en verde: `selftest: 37/37`, `i18n: OK`, `tests/run.php: 58 assertions, 0 failed` en PHP 8.3 y 7.4,
`phpcs scanned 11 PHP files; the package ships 11` con 0 errores y 0 avisos, `20 files and 6 directory
entries`, `zip: OK`, y Plugin Check «Success: Checks complete. No errors found.». La ejecución sobre
`main` queda pendiente de la fusión.

## 5. El zip de CI: descargado, abierto y leído

```
GH_TOKEN="$(~/webfiable/appbuilder/bin/secret get --raw TOKEN:webfiable-info)" \
  gh run download <id> --repo webfiable/Webfiable-Info -n webfiable-info-zip -D <carpeta>
python3 bin/release_checks.py zip <carpeta>/webfiable-info.zip --expect-version X.Y.Z
```

`zip` imprime la lista ordenada de ficheros y falla si falta alguno de la lista del paquete, si sobra
alguno, si aparece un fichero o una carpeta de desarrollo, o si la versión de la cabecera empaquetada
no es la esperada. La lista del paquete (`REQUIRED` en `bin/release_checks.py`) son estos 20 ficheros,
todos bajo `webfiable-info/`:

`webfiable-info.php`, `uninstall.php`, `readme.txt`, `LICENSE`, `includes/admin.php`,
`includes/constants.php`, `includes/endpoint.php`, `includes/i18n.php`, `includes/logger.php`,
`includes/options.php`, `includes/registration.php`, `includes/routing.php`, `includes/update.php`,
`assets/css/admin.css`, `assets/css/notice.css`, `assets/img/icon.png`,
`assets/img/webfiable-lockup-light.svg`, `languages/webfiable-info.pot`,
`languages/webfiable-info-en_US.po`, `languages/webfiable-info-en_US.mo`.

Nunca entran `composer.json`, `composer.lock`, `phpcs.xml`, `README.md`, esta lista
(`RELEASE-CHECKLIST.md`), `.distignore`, `.gitignore`, `.gitattributes`, ni nada bajo `.github/`,
`bin/`, `tests/`, `.wordpress-org/`, `vendor/` o `node_modules/`. `.distignore` los deja fuera del
paquete y `zip` lo comprueba. Si una versión añade o quita un fichero publicado, el mismo commit
cambia `REQUIRED`.

Se guarda la lista impresa: los pasos 8 y 9 la comparan con la del zip desplegado y con la del zip que
sirve wordpress.org.

**Verificado en 2.2.0.** El zip de `ccf720a` (ejecución 36176053051, artefacto 10882391997) se
descargó, se abrió y se comprobó: 20 ficheros y 6 entradas de carpeta; `zip: OK`; sha256
`0d5cbb23bf93288249095244a272427562e2f54a57e66f46ba1566b3ea52a311`. Sus 20 ficheros son iguales a
los de git en `ccf720a`, y es el zip instalado en el WordPress de pruebas. Con zips anteriores de la
misma rama, la puerta de dev (13 de 13 flujos) y `/qa-only` recorrieron la actualización desde 2.1.2,
el registro tras actualizar, las precondiciones, el fallo, el sitio sin WP-Cron, el sitio quitado, el
sitio nuevo y las pantallas. El único fallo de `/qa-only` (el botón de guardar medía 40 px a 390) se
arregló en `ccf720a`: ahora mide 44 px.

## 6. Los commits fijados de las acciones, releídos

Cada `uses:` de `.github/workflows/ci.yml` y `release.yml` va fijado a un commit completo, con la
etiqueta en un comentario. Antes de etiquetar, se comparan las líneas `uses:` de `main` con esta tabla
(0 diferencias). Si una acción cambia de commit, se lee su código en el commit nuevo, se actualiza esta
tabla en una PR y se vuelve al paso 4.

| Acción | Etiqueta | Commit | Dónde |
|---|---|---|---|
| `actions/checkout` | v7.0.1 | `3d3c42e5aac5ba805825da76410c181273ba90b1` | ci ×3, release ×3 |
| `shivammathur/setup-php` | 2.37.2 | `f3e473d116dcccaddc5834248c87452386958240` | ci ×2, release ×2 |
| `WordPress/plugin-check-action` | v1.1.9 | `10857da14b6c2246d15402b3e69f777edcf8c12e` | ci ×1, release ×1 |
| `actions/upload-artifact` | v7.0.1 | `043fb46d1a93c77aae656e7c1c64a875d1fc6a0a` | ci ×1, release ×2 |
| `actions/download-artifact` | v8.0.1 | `3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c` | release ×2 (`compare`) |
| `10up/action-wordpress-plugin-deploy` | 2.3.0 | `54bd289b8525fd23a5c365ec369185f2966529c2` | ci ×1, release ×2 |

```
git grep -h "uses:" origin/main -- .github/workflows | sed 's/^ *- *//; s/^ *//' | sort | uniq -c
GH_TOKEN="$(~/webfiable/appbuilder/bin/secret get --raw TOKEN:webfiable-info)" \
  gh api repos/<dueño>/<acción>/git/refs/tags/<etiqueta> --jq '.object.type + " " + .object.sha'
```

`deploy.sh` de la acción de 10up en `54bd289…` tiene sha256
`e6c78ccee70195e879f04e567b19dfebe617231f28f6585aef3c90dc023d5fb2`: se relee cuando cambie el commit.
Queda una referencia móvil conocida: `plugin-check-action` instala `@wordpress/env` sin fijar y la
última versión de Plugin Check. Corre solo en trabajos sin secretos y con token de solo lectura
(`ci.package` y `release.verify`), nunca en `deploy`.

**Verificado en 2.2.0.** Tabla leída el 2026-09-24 a las 21:28:57Z: cinco etiquetas ligeras (`commit`),
todas iguales a lo fijado. `download-artifact` se añadió el 2026-09-25 con `compare`, y su código se
leyó en `3e5f45b2…`. En `ccf720a`, 20 de 20 líneas `uses:` están fijadas a un commit completo con su
comentario. La relectura sobre `main` es del paso de la etiqueta.

## 7. El «Ship» de Fernando

Fernando dice «Ship» para la etiqueta, nombrando la versión, después de ver los pasos 1 a 6 con sus
valores. Mientras S8-T1 no esté hecha, este paso es la única guarda de la publicación: nadie empuja una
etiqueta sin él.

## 8. La etiqueta `vX.Y.Z` sobre `main`

La empuja quien tiene el «Ship», desde el clon del plugin y con sus credenciales guardadas. Nunca con
`gh auth login`.

```
cd ~/webfiable/webfiable-info
git fetch origin main --tags
test "$(git rev-parse origin/main)" = "<commit de fusión>" && echo "main = commit de fusión"
git tag -a vX.Y.Z <commit de fusión> -m "X.Y.Z"
git cat-file -p vX.Y.Z | sed -n '1,6p'
git push origin refs/tags/vX.Y.Z
```

`release.yml` hace tres trabajos:

- `verify`: la etiqueta dice la versión del código (`coherence --tag`) y el commit está en `main`;
  repite las comprobaciones de CI; construye el zip en modo de prueba, lo inspecciona con
  `--expect-version`, lo sube como `webfiable-info-vX.Y.Z-verified` antes de Plugin Check y lo pasa por
  Plugin Check.
- `deploy`: el único trabajo que ve los secretos de SVN. Reconstruye el zip desde el mismo commit, lo
  publica en SVN (código y `.wordpress-org/`) y lo sube como `webfiable-info-vX.Y.Z-deployed`.
- `compare`: sin secretos. Comprueba que el zip verificado y el desplegado tienen los mismos ficheros,
  byte a byte (`samezip`). Un `compare` en rojo después de un `deploy` en verde significa que lo
  publicado difiere de lo inspeccionado; se investiga antes de cerrar la vigilancia.

Después se descarga `webfiable-info-vX.Y.Z-deployed`, se pasa por `zip` y su lista se compara con la
del paso 5 (0 líneas de diferencia).

- **Si `verify` falla:** nada ha llegado a SVN. Se arregla en `main` por el camino normal (PR, CI,
  fusión). La etiqueta solo se borra (`git push origin :refs/tags/vX.Y.Z`) mientras `deploy` no se haya
  ejecutado, y con la palabra de Fernando.
- **Si `deploy` falla en `svn commit`:** nada se ha publicado. Fernando renueva `WPORG_USERNAME` y
  `WPORG_PASSWORD` en GitHub y se relanza solo ese trabajo (`gh run rerun <id> --job <id del trabajo>`).

## 9. La ventana de vigilancia

Los registros de la API de producción no sirven como detector: a nivel Production no escriben nada.
Estas son las lecturas que sí sirven:

| Cuándo | Qué | Cómo | Qué prueba |
|---|---|---|---|
| T+10 min | API de información del plugin | `curl -sL https://api.wordpress.org/plugins/info/1.0/webfiable-info.json` → `version`, `name`, `tested`, `requires` | la versión y el nombre nuevos publicados |
| T+10 min | etiqueta en SVN, por HTTP (sin cliente `svn`) | `curl -s https://plugins.svn.wordpress.org/webfiable-info/tags/ \| grep -c '"X.Y.Z/"'` → 1 | la etiqueta llegó a SVN (la misma orden con una versión anterior da 1) |
| T+1 h | página de la ficha | `curl -sL https://wordpress.org/plugins/webfiable-info/` → título, URL del icono y de los banners; `curl -sI` de cada uno → 200; sha256 del banner descargado igual al de `.wordpress-org/` | nombre, icono y banners en vivo |
| de T+10 min a T+2 h, cada 5 min | **el paquete que sirve wordpress.org** | ver abajo | lo que se instala es lo que se inspeccionó |
| T+1 h, luego cada 12 h | la consulta en caché de producción | `SELECT Timestamp FROM CachedHttpContent WHERE RequestUri = 'https://api.wordpress.org/plugins/info/1.0/webfiable-info.json'` | cuándo verá producción la versión nueva: `Timestamp + 2880 min`, leído y no supuesto |
| tras el refresco de esa caché | la sonda de producción sobre un sitio que sigue en la versión anterior | `GET /WebfiablePlugin/{sitio}` con la clave por la entrada estándar | misma versión mayor: `Supported` con `newerPluginVersionPublished: true`; el sitio se analiza, no se rechaza |
| antes de la etiqueta, T+24 h, T+72 h, T+7 d | estados del plugin en producción | `SELECT PluginState, COUNT(*) FROM Sites GROUP BY PluginState` | el número de sitios en estado 3 («Actualiza el plugin») no sube |
| T+24 h, T+72 h, T+7 d | registros | recuentos de `Sites` nuevos, re-registrados y enlazados desde la hora de la etiqueta (solo recuentos, nunca hosts ni correos) | cuántas instalaciones vuelven a registrarse; la actualización automática es opcional en WordPress |
| T+7 d | versiones instaladas | `curl -sL https://api.wordpress.org/stats/plugin/1.0/webfiable-info` y `active_installs` | la cuota de la versión nueva |

**El paquete que sirve wordpress.org.** wordpress.org construye su propio zip a partir de SVN, y ese es
el que se instala. Desde T+10 min, cada 5 minutos:

```
curl -sL -o <carpeta>/servido.zip https://downloads.wordpress.org/plugin/webfiable-info.X.Y.Z.zip
python3 -m zipfile -l <carpeta>/servido.zip | head
```

Cuando la cabecera de `webfiable-info/webfiable-info.php` dentro del zip diga `Version: X.Y.Z`:

```
python3 bin/release_checks.py zip <carpeta>/servido.zip --expect-version X.Y.Z
```

La lista ordenada que imprime se compara con la del paso 5: 0 diferencias. **Límite: la hora de la
etiqueta + 2 h.** Un 404 o una cabecera con la versión anterior antes del límite es «todavía no»;
después del límite es ROJO y se avisa a Fernando.

La comprobación sabe fallar sobre un zip servido de verdad. El 2026-09-25 a las 23:22Z,
`webfiable-info.2.1.1.zip`, descargado de wordpress.org con 200, salió con código 1 y 9 problemas. 2.1.1
se publicó sin `assets/`, y la comprobación lo detecta: faltan `assets/css/admin.css`,
`assets/css/notice.css`, `assets/img/icon.png` y `assets/img/webfiable-lockup-light.svg`.

## 10. El retroceso, dicho claro

**Ninguna palanca devuelve a la versión anterior los sitios que ya se actualizaron.** wordpress.org no
retira versiones y WordPress no baja de versión un plugin instalado.

- **Antes de la etiqueta:** se revierte la fusión en `main`. No se ha publicado nada.
- **La palanca de SVN (`Stable tag`).** Consiste en cambiar `Stable tag` en `trunk/readme.txt` del SVN
  de wordpress.org a la versión anterior. Con eso, el directorio vuelve a ofrecer la versión anterior a
  las instalaciones nuevas y deja de ofrecer la nueva a quien sigue en la anterior. **No toca a quien ya
  actualizó.** La tiene Fernando: necesita sus credenciales de wordpress.org y un cliente `svn`, y el VPS
  no tiene `svn` (`command -v svn` no devuelve nada, releído el 2026-09-25 a las 23:22Z). Se hace desde
  su propia máquina:

  ```
  svn co https://plugins.svn.wordpress.org/webfiable-info/trunk webfiable-info-trunk
  # en webfiable-info-trunk/readme.txt: «Stable tag: <versión anterior>»
  svn ci webfiable-info-trunk -m "Stable tag: <versión anterior>" --username <usuario de wordpress.org>
  ```

- **La palanca que funciona es la siguiente versión de parche** (tras 2.2.0, la 2.2.1), recorriendo
  esta misma lista desde el paso 1. Arregla también a quien ya actualizó, si tiene la actualización
  automática o actualiza a mano. Tiempo medido de PR a CI, fusión, etiqueta y versión en vivo en esta
  publicación: <medido en SPRINT-8-SHIP.md>.

**El lado de SiteAudit.** El cambio de SiteAudit que acompaña a una publicación va a producción antes
de la etiqueta. Volver a la imagen del commit anterior de producción solo es válido mientras la
consulta en caché de producción (la fila de `CachedHttpContent` de la URL de información del plugin)
siga diciendo la versión anterior. Desde que esa caché se refresca con la versión nueva, un retroceso
de SiteAudit es un arreglo hacia delante que conserva la regla del número mayor, nunca la imagen
anterior. En 2.2.0, el commit anterior de producción es `133aac6`.
