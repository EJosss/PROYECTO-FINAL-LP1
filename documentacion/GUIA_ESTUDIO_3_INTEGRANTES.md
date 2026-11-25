# Guía Profunda de Estudio del Código — División para 3 Integrantes
**Proyecto:** RE/MAX Zona Inmobiliaria (Laravel 13 + Livewire 4 + MySQL)  
**Objetivo:** Que cada integrante domine al 100 % un bloque completo e independiente del proyecto, sepa explicar *qué programó*, *por qué lo hizo así* y *en qué archivo y línea está*, listo para sustentación individual frente al profesor.

---

## 1. Mapa rápido de todo el sistema (lo que los 3 deben saber)

Antes de separarse a estudiar, los 3 deben tener claro cómo viaja una petición en este proyecto:

```text
Navegador del usuario
   │
   ▼
[bootstrap/app.php] ──► Middleware global: CabecerasSeguridad (CSP, HSTS, X-Frame-Options...)
   │
   ▼
[routes/web.php]
   ├── Rutas públicas (/, /propiedades, /oficinas/{slug}, /contacto, /sitemap.xml)
   │        └──► Componentes Full-Page de Livewire 4 (app/Livewire/Publico/*)
   │
   ├── Ruta de salud (/salud)
   │        └──► EstadoSistemaController (__invoke) ──► ServicioDisponibilidad
   │
   └── Rutas /admin/* (Middleware: auth + rol:ADMINISTRADOR,MARKETING)
            ├── VerificarRol (verifica sesión, activo=true y rol)
            └──► Componentes Livewire Admin (app/Livewire/Admin/*)
                     └──► Servicios (app/Services/*) ──► Modelos Eloquent (app/Models/*) ──► MySQL (CHECK + Triggers)
```

### Tabla de reparto (cero archivos repetidos)

| Integrante | Rol que defiende | Módulos propios | Pestañas clave en VS Code |
| :--- | :--- | :--- | :--- |
| **Integrante 1** | **Frontend Público, Contenidos Fijos, SEO y WebP** | Sitio público (6 páginas), Portada, Oficinas fijas, Contacto, SEO/Sitemap, WebP | `routes/web.php`, `PaginaPropiedades.php`, `ServicioOptimizacionImagen.php`, `TelefonoPeru.php`, `Oficina.php`, `ConfiguracionContacto.php` |
| **Integrante 2** | **Núcleo de Negocio, BD y Motor de Ranking Mensual** | Propiedades, Agentes, Ranking Mensual, Historial, Triggers MySQL, Concurrencia | `GestionPropiedades.php`, `ServicioRankingMensual.php`, `RankingMensual.php`, `PosicionRanking.php`, migraciones `0002/0004/0005/0013` |
| **Integrante 3** | **Seguridad, Auth/Roles/2FA, Respaldos, Salud y QA** | Login, RateLimiter, Roles, 2FA Fortify, Usuarios, Cabeceras, Respaldos SHA-256, `/salud`, PHPUnit | `VerificarRol.php`, `IniciarSesion.php`, `CabecerasSeguridad.php`, `bootstrap/app.php`, `AppServiceProvider.php`, `ServicioRespaldo.php`, `ServicioDisponibilidad.php` |

### Regla de oro para responder al profesor (Técnica en 3 pasos)
1. **Qué problema resuelve** (en 1 frase clara).
2. **Qué mecanismo técnico implementé** (el nombre real: `lockForUpdate()`, `RateLimiter`, `CHECK`, `#[Url]`, etc.).
3. **Abro el archivo y señalo la línea exacta** en VS Code.

---

# INTEGRANTE 1 — Sitio Público, Contenidos Fijos, SEO y Optimización WebP

## Tu discurso cuando el profesor pregunta: *"¿Qué hiciste tú en el proyecto?"*
> *"Yo programé toda la cara pública de RE/MAX Zona Inmobiliaria usando componentes Full-Page de Livewire 4: la portada, el catálogo con filtros reactivos sincronizados en la URL, las páginas de las 4 oficinas, empresa y contacto. También desarrollé los módulos administrativos de contenidos fijos (portada, oficinas inmutables y contacto con patrón Singleton), el SEO técnico (`sitemap.xml` dinámico y datos estructurados JSON-LD) y el servicio de optimización automática de imágenes a WebP con PHP GD nativo."*

---

## Estudio profundo archivo por archivo

### 1. Arquitectura Full-Page Livewire 4 y Sitemap dinámico
- **Archivo:** `routes/web.php` (L24–62)
- **Qué hace:**
  - Las rutas públicas (`/`, `/empresa`, `/propiedades`, `/oficinas`, `/oficinas/{oficina}`, `/contacto`) apuntan **directamente a clases Livewire** (`PaginaInicio::class`, `PaginaPropiedades::class`, etc.), sin pasar por un Controller tradicional.
  - En `GET /sitemap.xml` (L33–62) se construye dinámicamente un XML estándar (`sitemaps.org/schemas/sitemap/0.9`) recorriendo las rutas fijas más los 4 slugs de `Propiedad::SLUGS_OFICINAS` (`arequipa`, `lima`, `moquegua`, `puno`), escapando cada URL con `e()` y devolviendo cabecera `Content-Type: application/xml; charset=UTF-8`.
- **Por qué se hizo así:**
  - Livewire 4 permite interactividad en tiempo real (filtros, búsqueda, formularios) escribiendo solo PHP/Blade, evitando construir y mantener una API REST + frontend SPA separado.

### 2. Catálogo con filtros en tiempo real y URL compartible
- **Archivo:** `app/Livewire/Publico/PaginaPropiedades.php` (L12–72)
- **Detalles técnicos clave:**
  - **Atributos de clase (L12–13):** `#[Layout('components.layouts.publico')]` y `#[Title('Propiedades | RE/MAX Zona')]` definen el layout y título sin código repetitivo.
  - **Sincronización con la URL (L16–23):** `#[Url(except: '')]` en `$busqueda`, `$ubicacion` y `$moneda`. Cuando el usuario filtra, la URL cambia (ej. `/propiedades?moneda=USD&ubicacion=Puno`) sin recargar la página; si limpia el filtro (`except: ''`), el parámetro desaparece para mantener la URL limpia.
  - **Consultas seguras (L32–50):** Usa Eloquent (`where('nombre', 'like', '%' . $termino . '%')`), que usa **sentencias preparadas** automáticamente (cero riesgo de SQL Injection). Además valida la moneda contra una lista blanca (`in_array($this->moneda, [MONEDA_SOLES, MONEDA_DOLARES], true)`).
  - **SEO por página (L67–71):** `->layoutData([...])` inyecta `metaDescription`, `canonicalUrl` y `datosEstructurados` (JSON-LD `ItemList` de Schema.org) directamente al layout `publico.blade.php`.

### 3. Optimización WebP sin paquetes externos
- **Archivos:** `app/Services/ServicioOptimizacionImagen.php` (L10–163) y `resources/views/components/imagen-optimizada.blade.php`
- **Cómo funciona paso a paso:**
  1. **Constantes (L10–12):** Ancho máximo por defecto `1200 px` y calidad WebP `82`.
  2. **Evita trabajo innecesario (L20–44):** Ignora URLs externas (`http://`, `https://`) o archivos que ya son `.webp`. Si el `.webp` ya existe y su fecha (`filemtime`) es mayor o igual a la del original, **no reconvierte**, solo devuelve la URL.
  3. **PHP GD nativo (L95–159):** Verifica `extension_loaded('gd')` y `function_exists('imagewebp')`. Lee las dimensiones con `getimagesize()`, carga JPEG/PNG/WebP con `match ($tipo)`, calcula el alto proporcional si excede el ancho máximo (`round(($altoOriginal * $anchoMaximo) / $anchoOriginal)`), y **preserva la transparencia de los PNG** con `imagealphablending($lienzo, false)` + `imagesavealpha($lienzo, true)` + fondo transparente.
  4. **Conserva la imagen original:** Nunca borra el JPG/PNG original; sirve ambos mediante `<picture>` en `<x-imagen-optimizada>` (el navegador moderno carga WebP y uno antiguo cae al original). En CSS se aplica `picture { display: contents; }` para que la etiqueta `<picture>` no rompa los `grid` ni `flex` existentes.

### 4. Helper DRY para teléfonos de Perú (`+51`)
- **Archivo:** `app/Support/TelefonoPeru.php` (L10–55)
- **Problema que resuelve:** En Perú los usuarios a veces escriben `987654321`, otras veces `+51 987 654 321` y otras `51987654321`. Si el formulario agrega `+51` ciegamente, al editar queda `+51 +51 987...`.
- **Métodos:**
  - `quitarPrefijo()`: limpia `+51` o `51` inicial para editar solo el número local.
  - `formatearConPrefijo()`: devuelve siempre `+51 ...` limpio para mostrar en pantalla.
  - `formatearWhatsapp()`: deja solo dígitos con `51` delante (`51987654321`) listo para enlaces `https://wa.me/...`.

### 5. Patrón Singleton y Oficinas Inmutables
- **Archivos:** `app/Models/ConfiguracionContacto.php` (L12–59), `app/Models/ConfiguracionPortada.php` y `app/Models/Oficina.php` (L15–90)
- **Singleton (`ConfiguracionContacto` y `ConfiguracionPortada`):**
  - `$incrementing = false` y `id = 1` fijo.
  - En `booted()`: `creating` fuerza `id = 1` y lanza `DomainException` si ya existe el registro `1`; `deleting` lanza `DomainException` prohibiendo borrarlo.
  - `obtenerUnica()` usa `firstOrCreate(['id' => 1], [...])` garantizando que siempre exista exactamente 1 fila.
  - Respaldado en MySQL por `CHECK (id = 1)`.
- **4 Oficinas fijas (`Oficina.php`):**
  - Solo existen 4 sedes oficiales (`AREQUIPA`, `LIMA`, `MOQUEGUA`, `PUNO`) en `CODIGOS_SLUGS`.
  - `booted()` bloquea creación fuera de esas 4, bloquea modificación de `codigo` o `slug` (`$oficina->isDirty('codigo') || $oficina->isDirty('slug')`) y prohíbe eliminarlas, porque sus slugs son rutas SEO fijas (`/oficinas/puno`, etc.) y llaves de filtro del catálogo.
  - `obtenerTodas()` usa `ORDER BY CASE codigo ...` (L72–77) para mantener siempre el orden institucional exacto.

---

## Preguntas trampa del profesor para el Integrante 1

1. **¿Por qué casi no hay Controladores en `app/Http/Controllers/` si esto es Laravel?**
   - *Respuesta:* Porque usamos Livewire 4 con *Full-Page Components* en `routes/web.php`. El único controlador tradicional es `EstadoSistemaController` porque `/salud` responde JSON sin vista ni estado.
2. **Si filtro por "casa" y comparto el enlace por WhatsApp, ¿la otra persona ve el mismo filtro?**
   - *Respuesta:* Sí, porque `$busqueda`, `$ubicacion` y `$moneda` tienen el atributo `#[Url(except: '')]` en `PaginaPropiedades.php` (L16–23), que refleja el estado en la query string.
3. **¿Qué pasa si subo un PNG con fondo transparente? ¿Tu conversor WebP le pone fondo negro?**
   - *Respuesta:* No. En `ServicioOptimizacionImagen.php` (L136–139) desactivamos `imagealphablending`, activamos `imagesavealpha` y rellenamos el lienzo con canal alfa `127` (transparente) antes de `imagecopyresampled()`.
4. **¿Qué pasa si un navegador antiguo no soporta WebP o si el servidor no tiene GD?**
   - *Respuesta:* Si no hay GD, `convertirAWebp()` retorna `false` sin romper nada y nunca borramos el JPG/PNG original. Además `<x-imagen-optimizada>` usa `<picture>` con `<source type="image/webp">` y fallback `<img>` al original.
5. **¿Por qué no dejan crear una quinta oficina desde el panel?**
   - *Respuesta:* Porque las 4 oficinas tienen códigos y slugs fijos ligados a rutas SEO (`/oficinas/{slug}`), al `sitemap.xml` y a las propiedades. Tanto `Oficina::booted()` (L38–62) como el `CHECK` de MySQL impiden crear, renombrar slugs o eliminar sedes; solo se editan sus datos de contacto y foto.
6. **¿Cómo evitan que al editar un teléfono se duplique el `+51`?**
   - *Respuesta:* Con `TelefonoPeru::quitarPrefijo()` (`app/Support/TelefonoPeru.php`, L10–27) antes de cargar el input y `formatearConPrefijo()` al guardar.

### Checklist del Integrante 1 (antes de exponer)
- [ ] Sé explicar qué es un Full-Page Component en Livewire 4.
- [ ] Sé señalar `#[Url(except: '')]` en `PaginaPropiedades.php`.
- [ ] Sé explicar el flujo de `ServicioOptimizacionImagen.php` (cache por `filemtime`, GD, transparencia PNG).
- [ ] Sé explicar el patrón Singleton en `ConfiguracionContacto.php` y el bloqueo de `Oficina.php`.

---

# INTEGRANTE 2 — Núcleo de Negocio: Propiedades, Agentes y Motor del Ranking Mensual

## Tu discurso cuando el profesor pregunta: *"¿Qué hiciste tú en el proyecto?"*
> *"Yo programé el núcleo de negocio del panel administrativo: el CRUD de propiedades y agentes con validación condicional y limpieza de archivos huérfanos, las reglas de integridad en MySQL (`CHECK` y 5 Triggers), y todo el motor del Ranking Mensual con control de concurrencia (`DB::transaction` + `lockForUpdate`), bloqueo automático tras el día 3 del mes, snapshots históricos inmutables y validación de celulares duplicados y homónimos."*

---

## Estudio profundo archivo por archivo

### 1. CRUD de Propiedades: validación condicional y cero archivos huérfanos
- **Archivo:** `app/Livewire/Admin/GestionPropiedades.php` (L71–155)
- **Detalles técnicos clave:**
  - **Precio y moneda acoplados (L76–85):** Si el usuario ingresa precio (`$tienePrecio === true`), `moneda` pasa a ser `['required', 'in:PEN,USD']`. Si deja precio vacío, tanto `precio` como `moneda` se guardan como `null` (L126–127). Esto se respalda además en MySQL con el `CHECK` `chk_propiedades_precio_moneda` en la migración `0002`.
  - **Seguridad en subida de imágenes (L88–90):** Valida en servidor `image|mimes:jpg,jpeg,png,webp|max:4096`, guarda con nombre aleatorio seguro (`$this->imagenArchivo->store('propiedades', 'public')`) y genera el `.webp` al vuelo (L114–115).
  - **Cero archivos huérfanos (L133–152):**
    - Si falla el `create()` o `update()` en BD, el bloque `catch (Throwable)` **borra inmediatamente** `$rutaImagenNueva` del disco para no dejar basura.
    - Si la propiedad se editó y cambió de foto, la foto vieja (`$imagenAnteriorParaBorrar`) **solo se elimina después** de que la BD confirmó el cambio (L150–152).

### 2. Defensa en profundidad en MySQL: `CHECK` y 5 Triggers
- **Archivos:** Migraciones `0001_01_01_000002` (propiedades), `0004` (rankings_mensuales), `0005` (posiciones_ranking) y `0013` (snapshots independientes).
- **Por qué existen los 5 Triggers si ya hay validaciones en Laravel:**
  - Los eventos `booted()` de Eloquent **solo se ejecutan cuando pasas por el ORM**. Si alguien ejecuta un `UPDATE` o `DELETE` directo en MySQL, phpMyAdmin o con `DB::table()->update()`, Eloquent no se entera.
  - Por eso las migraciones `0004` y `0005` crean **5 triggers `BEFORE`** en MySQL que lanzan `SIGNAL SQLSTATE '45000'` impidiendo alterar o borrar rankings cerrados/vencidos o sus posiciones directamente en la base de datos.
- **Qué hizo la migración `0013`:**
  - Desacopló `posiciones_ranking` de la tabla `vendedores` (`vendedor_id` nullable), agregó `codigo_interno` único (`PART-...`) y `celular_normalizado` con índice único por período (`uk_posicion_celular_periodo`), conservando los campos `*_snapshot` (`nombres_snapshot`, `apellidos_snapshot`, `celular_snapshot`, `foto_snapshot`) para que el historial de ganadores nunca se corrompa aunque cambien datos futuros.

### 3. Reglas temporales y transición controlada en `RankingMensual`
- **Archivo:** `app/Models/RankingMensual.php` (L58–154)
- **Estados del ciclo de vida:** `BORRADOR` → `PUBLICADO` → `CERRADO` (o `BORRADOR` → `VENCIDO` si nadie publicó a tiempo).
- **Cálculo del período (L99–110):** En `periodoActual()` se toma `now('America/Lima')` como mes de publicación, y se usa `startOfMonth()->subMonthNoOverflow()` para calcular el mes de desempeño (ej. el 2 de marzo se publica el ranking de desempeño de febrero; `subMonthNoOverflow()` evita el bug clásico del 31 de marzo → 3 de marzo).
- **Ventana de los días 1 al 3 (L116–124):** `dentroDePlazoDeGestion()` exige que hoy (reloj `America/Lima`) sea día 1, 2 o 3 del mes y año de publicación.
- **El problema inteligente que resuelve `esTransicionInternaDeCierre()` (L66–83 y L138–154):**
  - Pasado el día 3, `booted()` bloquea cualquier `updating`. **Pero entonces, ¿cómo pasa el sistema un ranking de `PUBLICADO` a `CERRADO` el día 4?**
  - Ahí entra `esTransicionInternaDeCierre()`: verifica dos cosas estrictas:
    1. Que el cambio de estado sea solo `PUBLICADO -> CERRADO` o `BORRADOR -> VENCIDO`.
    2. Usando `$this->getDirty()` y `array_diff($camposModificados, ['estado', 'fecha_cierre', 'updated_at'])`, garantiza que **ningún otro campo fue tocado**. Si alguien intenta cambiar el mes o el creador el día 4, lanza `DomainException`.

### 4. Concurrencia, duplicados y homónimos en `ServicioRankingMensual`
- **Archivo:** `app/Services/ServicioRankingMensual.php` (L20–316)
- **Concurrencia (`DB::transaction` + `lockForUpdate()`):**
  - En `sincronizarEstadosVencidos()` (L31–32), `guardarParticipantePuesto()` (L71, L91, L253) y `publicarRankingActual()` (L191–193), toda escritura corre dentro de una transacción con **bloqueo pesimista (`lockForUpdate()`)** sobre la fila de `rankings_mensuales` y sus `posiciones`.
  - Si dos administradores editan o publican al mismo milisegundo, MySQL pone al segundo en cola hasta que el primero haga commit, evitando condiciones de carrera (*race conditions*).
- **Unicidad por celular y aviso de homónimos (L96–118):**
  - `PosicionRanking::normalizarCelular()` (en `PosicionRanking.php`, L71–80) deja solo dígitos y recorta el `51` si es un celular peruano de 11 dígitos (`519...` → `9...`).
  - Se compara contra los otros puestos usando `hash_equals($otroCelular, $celularNormalizado)`: si coincide, **bloquea** con `DomainException` (doble seguro con el índice único en MySQL).
  - En cambio, si coinciden nombre y apellido normalizados (`Str::ascii` en minúsculas sin tildes ni dobles espacios), **no bloquea** porque pueden existir dos personas con el mismo nombre; devuelve `$advertenciaHomonimo` como alerta visual.
- **Publicación atómica (L189–229):** Solo permite publicar si existen exactamente 3 puestos (`[1, 2, 3]`), 3 celulares únicos y las 3 fotografías existen físicamente en `Storage::disk('public')`.
- **Limpieza post-commit de fotos (L168–183 y L284–316):**
  - Si falla la transacción, `catch (Throwable)` borra la foto nueva para no dejar huérfanos.
  - Si la transacción tiene éxito, `limpiarFotografiasReemplazadasPostCommit()` verifica que el ranking no esté `CERRADO`, que la ruta pertenezca a la carpeta del período (`rankings/AAAA/MM/`) y que ningún otro registro referencie esa foto (`PosicionRanking::where('foto_snapshot', ...)->exists()`), y recién borra tanto el original como su `.webp`.

---

## Preguntas trampa del profesor para el Integrante 2

1. **¿Qué pasa si dos usuarios le dan clic a "Guardar puesto 1" exactamente al mismo tiempo?**
   - *Respuesta:* `ServicioRankingMensual` envuelve la operación en `DB::transaction()` y aplica `lockForUpdate()` sobre el ranking y sus posiciones (L71, L91, L253). MySQL serializa ambas escrituras a nivel de fila y evita sobrescrituras inconsistentes o celulares duplicados.
2. **¿Por qué pusieron 5 Triggers en MySQL si ya tenían `booted()` en los Modelos?**
   - *Respuesta:* Defensa en profundidad. `booted()` solo actúa vía Eloquent; los triggers `BEFORE` con `SIGNAL SQLSTATE '45000'` protegen la integridad histórica incluso si alguien ejecuta SQL directo o `DB::table()`.
3. **Si el ranking se bloquea después del día 3, ¿cómo hace el sistema el día 4 para cambiarlo a `CERRADO`?**
   - *Respuesta:* Con la excepción controlada `esTransicionInternaDeCierre()` en `RankingMensual.php` (L138–154), que valida con `getDirty()` que solo cambien `estado`, `fecha_cierre` y `updated_at`, y únicamente hacia `CERRADO` o `VENCIDO`.
4. **¿Por qué guardan `nombres_snapshot` y `foto_snapshot` en vez de hacer solo un `JOIN` a `agentes` o `vendedores`?**
   - *Respuesta:* Porque el ranking histórico debe ser una foto fiel del mes en que ganó esa persona. Si meses después cambia de foto, de celular o deja la empresa, el historial no debe alterarse.
5. **¿Qué pasa si subo una foto de propiedad pero MySQL se cae justo al hacer `INSERT`?**
   - *Respuesta:* En `GestionPropiedades.php` (L133–148), el `try/catch (Throwable)` detecta el fallo y elimina inmediatamente `$rutaImagenNueva` del disco `public` para evitar archivos huérfanos.
6. **¿Por qué un homónimo solo muestra advertencia y un celular duplicado sí bloquea?**
   - *Respuesta:* Porque dos asesores distintos pueden llamarse igual (homónimos reales), pero no pueden compartir el mismo número de celular personal en dos puestos del mismo podio.
7. **¿Por qué usan `subMonthNoOverflow()` en `periodoActual()`?**
   - *Respuesta:* Para evitar el desbordamiento de fechas de Carbon (ej. restar 1 mes al 31 de marzo daría 3 de marzo en vez de febrero). Así siempre calcula el mes anterior exacto.
8. **¿Cómo garantizan que no exista una propiedad con precio `150000` pero moneda `NULL`?**
   - *Respuesta:* Doble validación: regla condicional en `GestionPropiedades.php` (L76–85) más restricción `CHECK (chk_propiedades_precio_moneda)` en la migración `0002`.

### Checklist del Integrante 2 (antes de exponer)
- [ ] Sé explicar `lockForUpdate()` dentro de `DB::transaction()` en `ServicioRankingMensual.php`.
- [ ] Sé señalar `esTransicionInternaDeCierre()` y `getDirty()` en `RankingMensual.php`.
- [ ] Sé explicar la diferencia entre protección en Eloquent (`booted()`) y protección en MySQL (`CHECK` + 5 Triggers).
- [ ] Sé explicar cómo se evitan fotos huérfanas antes y después del commit.

---

# INTEGRANTE 3 — Seguridad, Autenticación/Roles/2FA, Respaldos, Disponibilidad y Pruebas

## Tu discurso cuando el profesor pregunta: *"¿Qué hiciste tú en el proyecto?"*
> *"Yo me encargué de toda la seguridad, operación y calidad del sistema: implementé el login con protección contra fuerza bruta (`RateLimiter`), el middleware de control de roles (`ADMINISTRADOR` y `MARKETING`) con suspensión inmediata de sesiones, la autenticación en dos pasos (2FA TOTP) con Laravel Fortify, las cabeceras HTTP de seguridad (CSP, HSTS, X-Frame-Options), el sistema de respaldos automáticos verificables con `SHA-256`, el endpoint de salud `/salud` y la suite de 36 pruebas automatizadas en PHPUnit."*

---

## Estudio profundo archivo por archivo

### 1. Control de Roles y revocación inmediata de sesión
- **Archivos:** `app/Http/Middleware/VerificarRol.php` (L17–46) y `routes/web.php` (L78–94)
- **Cómo funciona paso a paso:**
  1. Se registra con alias `'rol'` en `bootstrap/app.php` (L22–24).
  2. **Si el usuario no está autenticado (L22–24):** redirige a `/admin/login`.
  3. **Suspensión en vivo (L26–34):** Si el administrador desactiva una cuenta (`activo = false`) mientras el usuario está navegando, en la siguiente petición `VerificarRol` ejecuta inmediatamente `Auth::logout()`, `$request->session()->invalidate()` y `$request->session()->regenerateToken()`, expulsándolo al instante.
  4. **Control de permisos (L37–43):** `ADMINISTRADOR` pasa siempre; si `MARKETING` intenta entrar a `/admin/usuarios`, `/admin/oficinas`, `/admin/portada`, `/admin/configuracion-contacto` o `/admin/historial-rankings`, recibe `abort(403)`.

### 2. Login, Fuerza Bruta (`RateLimiter`) y 2FA con Laravel Fortify
- **Archivos:** `app/Livewire/Autenticacion/IniciarSesion.php` (L30–164) y `app/Livewire/Settings/Security.php`
- **Detalles técnicos clave:**
  - **Fuerza bruta en Login (L41–56):** Clave `Str::lower($this->correo) . '|' . request()->ip()`. Máximo **5 intentos por ventana de 60 segundos** (`RateLimiter::tooManyAttempts(..., 5)` y `RateLimiter::hit(..., 60)`). Si acierta, limpia el contador (`RateLimiter::clear`).
  - **Mensaje genérico anti-enumeración (L52–55):** Si el correo no existe O la contraseña falla (`! $usuario || ! Hash::check(...)`), devuelve exactamente el mismo mensaje (*"Las credenciales ingresadas no son válidas"*) para no revelar qué correos existen en la BD.
  - **Flujo 2FA (L66–139):**
    - Si `$usuario->hasEnabledTwoFactorAuthentication()` es `true`, **no inicia sesión todavía**: guarda temporalmente `login.id` en sesión y muestra el desafío de 6 dígitos.
    - El desafío 2FA tiene su **propio `RateLimiter`** (`'2fa|' . $usuario->id . '|' . request()->ip()`, L95–100).
    - Soporta código TOTP verificando con `Fortify::currentEncrypter()->decrypt($usuario->two_factor_secret)` + `$provider->verify($secreto, $codigo)` (L127–128), **y también códigos de recuperación** comparados en tiempo constante con `hash_equals()` y quemados tras un solo uso con `$usuario->replaceRecoveryCode()` (L109–119).
  - **Anti Session Fixation (L157–164):** Al completar el login siempre ejecuta `session()->regenerate()` para emitir un nuevo ID de sesión.

### 3. Cabeceras HTTP, `bootstrap/app.php` y `AppServiceProvider`
- **Archivo:** `app/Http/Middleware/CabecerasSeguridad.php` (L14–44)
  - Middleware global registrado en `bootstrap/app.php` (L19). Inyecta en **todas** las respuestas:
    - `X-Frame-Options: SAMEORIGIN` (anti Clickjacking).
    - `X-Content-Type-Options: nosniff` (evita MIME sniffing).
    - `Referrer-Policy: strict-origin-when-cross-origin`.
    - `Permissions-Policy: camera=(), microphone=(), geolocation=()` (bloquea hardware innecesario).
    - `X-Permitted-Cross-Domain-Policies: none`.
    - `Content-Security-Policy` (`default-src 'self'`, `frame-ancestors 'self'`, `form-action 'self'`, etc., mitigando XSS).
    - `Strict-Transport-Security` (HSTS por 1 año) cuando es HTTPS o producción.
- **Archivo:** `app/Providers/AppServiceProvider.php` (L17–56)
  - `Fortify::ignoreRoutes()` (L20): desactiva las rutas genéricas de Fortify (como `/register` público) para que solo existan nuestras rutas cerradas.
  - `URL::forceScheme('https')` en producción (L31–33).
  - `Date::use(CarbonImmutable::class)` (L41): evita mutaciones accidentales de fechas.
  - `DB::prohibitDestructiveCommands(app()->isProduction())` (L43–45): impide ejecutar `migrate:fresh` o `db:wipe` por error en producción.
  - `Password::defaults()` en producción (L47–55): exige mínimo 12 caracteres, mayúsculas/minúsculas, números, símbolos y verificación contra contraseñas filtradas (`uncompromised()`).
- **Archivo:** `bootstrap/app.php` (L26–34)
  - Intercepta cualquier `QueryException` o `PDOException` y la registra automáticamente en el canal de log diario `disponibilidad`.

### 4. Respaldos seguros con verificación `SHA-256`
- **Archivo:** `app/Services/ServicioRespaldo.php` (L19–262) y `routes/console.php`
- **Cómo funciona:**
  1. **Sin sobrescritura y en storage privado (L21–33):** Crea `storage/app/private/respaldos/{entorno}/{Y-m-d_His}` (fuera de `public/`, inaccesible por web). Si ya existe en el mismo segundo, añade sufijo aleatorio `bin2hex(random_bytes(3))`.
  2. **Volcado MySQL sin exponer la contraseña (L180–199):** Ejecuta `mysqldump` con `--single-transaction --routines --triggers` usando `Process::timeout(120)->env(['MYSQL_PWD' => ...])`. Pasar la clave por variable de entorno efímera `MYSQL_PWD` evita que aparezca en texto plano en `ps aux` o logs del sistema operativo.
  3. **Multimedia en ZIP (L217–262):** Empaqueta `storage/app/public` y `public/images` en `multimedia.zip`.
  4. **Manifiesto e integridad `SHA-256` (L58–80 y L100–144):** Calcula `hash_file('sha256', ...)` de cada archivo y lo guarda en `manifiesto.json`. El comando de verificación recalcula el hash y lo compara con `hash_equals()` (comparación en tiempo constante contra *timing attacks*).

### 5. Monitoreo de salud (`/salud`) y Pruebas Automatizadas (PHPUnit)
- **Archivos:** `app/Services/ServicioDisponibilidad.php` (L16–60), `app/Http/Controllers/EstadoSistemaController.php` y `tests/Feature/RequerimientosNoFuncionalesTest.php`
- **`GET /salud`:**
  - Comprueba 3 cosas: que la app no esté en mantenimiento (`isDownForMaintenance()`), que MySQL responda `SELECT 1` y que `storage/logs` y `storage/app/public` existan y tengan permisos de escritura.
  - Responde `HTTP 200` (`estado: operativo`) o `HTTP 503` (`estado: degradado`) con `Cache-Control: no-store` y **sin filtrar credenciales ni stack traces** (el detalle del error va solo al log `disponibilidad`).
- **Suite PHPUnit (`php artisan test`):**
  - **36 pruebas aprobadas y 157 aserciones**, validando automáticamente cabeceras de seguridad, error `403` por rol, `/salud`, generación y verificación `SHA-256` de respaldos, conversión WebP, SEO/sitemap y reglas del ranking.

---

## Preguntas trampa del profesor para el Integrante 3

1. **Si un usuario de `MARKETING` ya inició sesión y el Administrador le desactiva la cuenta, ¿puede seguir editando hasta que cierre sesión?**
   - *Respuesta:* No. En su siguiente petición, `VerificarRol.php` (L26–34) comprueba `! $usuario->activo`, ejecuta `Auth::logout()`, invalida la sesión, regenera el token CSRF y lo expulsa al login.
2. **¿Cómo evitan que alguien haga fuerza bruta al login o al código de 6 dígitos del 2FA?**
   - *Respuesta:* Con `RateLimiter` de máximo 5 intentos cada 60 segundos en ambos flujos (`IniciarSesion.php`, L41–47 y L95–100), indexado por `correo|IP` en login y `2fa|usuario_id|IP` en 2FA.
3. **¿Por qué usan `hash_equals()` para comparar códigos de recuperación 2FA y hashes SHA-256 en lugar de `===`?**
   - *Respuesta:* Porque `hash_equals()` compara en tiempo constante y previene ataques de temporización (*timing attacks*).
4. **¿Por qué pasan la contraseña de MySQL por `MYSQL_PWD` en `ServicioRespaldo` y no con `-pCONTRASEÑA` en el comando?**
   - *Respuesta:* Porque pasar `-p` en la línea de comandos expone la contraseña en la lista de procesos del servidor (`ps aux`) y en logs. Con `->env(['MYSQL_PWD' => ...])` (`ServicioRespaldo.php`, L194–198) la clave vive solo en el entorno efímero del subproceso.
5. **¿Qué impide que alguien suba un archivo `shell.php` renombrado o acceda a los respaldos desde el navegador?**
   - *Respuesta:* Tres capas: (1) validación servidor `image|mimes:jpg,jpeg,png,webp` + `store()` con nombre aleatorio, (2) `storage/app/public/.htaccess` bloqueando ejecución de PHP, y (3) los respaldos se guardan en `storage/app/private/respaldos/`, fuera de la carpeta pública.
6. **¿Para qué sirve `Fortify::ignoreRoutes()` en `AppServiceProvider.php`?**
   - *Respuesta:* Para deshabilitar las rutas públicas por defecto de Fortify (como `/register` o `/login` genérico) y forzar que todo pase por nuestro login controlado `/admin/login` y nuestra gestión interna de usuarios.
7. **¿Por qué `GET /salud` usa un Controller tradicional y no Livewire?**
   - *Respuesta:* Porque `/salud` es un endpoint JSON sin estado pensado para monitores externos (UptimeRobot, balanceadores, etc.) que solo necesitan código `200` o `503` con `Cache-Control: no-store`.
8. **¿Qué evita que alguien ejecute `php artisan migrate:fresh` por accidente en producción?**
   - *Respuesta:* `DB::prohibitDestructiveCommands(app()->isProduction())` en `AppServiceProvider.php` (L43–45).

### Checklist del Integrante 3 (antes de exponer)
- [ ] Sé mostrar `VerificarRol.php` y explicar la expulsión inmediata si `activo = false`.
- [ ] Sé explicar `RateLimiter`, `session()->regenerate()` y el flujo 2FA en `IniciarSesion.php`.
- [ ] Sé nombrar al menos 4 cabeceras de `CabecerasSeguridad.php` y qué ataque mitiga cada una.
- [ ] Sé explicar `MYSQL_PWD` y `manifiesto.json` (`SHA-256`) en `ServicioRespaldo.php`.
- [ ] Sé ejecutar `php artisan test` en terminal y abrir `/salud` en el navegador en vivo.

---

## Plan de estudio rápido para los 3 (3 bloques de 45 min)

1. **Sesión 1 — Lectura individual (45 min):** Cada uno abre en VS Code **solo sus 5–6 archivos** de esta guía y los lee junto con las explicaciones de su sección.
2. **Sesión 2 — Prueba de fuego en vivo (45 min):** Corren `php artisan serve`, cada uno recorre su parte en el navegador mientras explica en voz alta qué archivo se está ejecutando detrás de cada clic.
3. **Sesión 3 — Simulacro de jurado (30 min):** Se intercambian las preguntas trampa de esta guía (el 1 le pregunta al 2, el 2 al 3, el 3 al 1). Quien duda, abre la línea exacta en VS Code y la repasa.

### Pases sugeridos durante la exposición
- **Integrante 1 → Integrante 2:** *"Todo este catálogo, oficinas y ranking que ve el cliente se gestionan desde el panel administrativo con reglas estrictas de integridad; ahora mi compañero les explicará cómo funciona ese núcleo de negocio y la base de datos."*
- **Integrante 2 → Integrante 3:** *"Para proteger toda esta operación contra accesos no autorizados, pérdida de datos o caídas del servidor, mi compañero les mostrará la capa de seguridad, respaldos y pruebas automatizadas."*
