# Guía de Sustentación y Preguntas del Código — RE/MAX Zona Inmobiliaria

> **Versión imprimible para estudio:** [Guia_Sustentacion_Codigo_REMAX_Zona.pdf](Guia_Sustentacion_Codigo_REMAX_Zona.pdf) y [Guia_Sustentacion_Codigo_REMAX_Zona.html](Guia_Sustentacion_Codigo_REMAX_Zona.html).  
> **Complementa al Manual Técnico:** [MANUAL_TECNICO.md](MANUAL_TECNICO.md) / [Manual_Tecnico_REMAX_Zona.pdf](Manual_Tecnico_REMAX_Zona.pdf).

---

## Estrategia antes de exponer

1. **Ten 8 pestañas clave abiertas en VS Code:**
   - `routes/web.php`
   - `bootstrap/app.php`
   - `app/Http/Middleware/VerificarRol.php`
   - `app/Http/Middleware/CabecerasSeguridad.php`
   - `app/Livewire/Autenticacion/IniciarSesion.php`
   - `app/Services/ServicioRankingMensual.php`
   - `app/Models/RankingMensual.php`
   - `app/Services/ServicioRespaldo.php`
2. **Responde siempre en 3 pasos:** (1) Qué problema resuelve → (2) Qué mecanismo técnico se implementó → (3) Abre el archivo y señala la línea exacta.

---

## Bloque 1: Arquitectura, Laravel 13 y Livewire 4

### P01. ¿Por qué casi no hay Controladores en `app/Http/Controllers/` si es un proyecto Laravel?
- **Respuesta (30 s):** Usamos **Livewire 4** con componentes de página completa (`Full-Page Components`): las rutas en `web.php` apuntan directamente a clases Livewire que gestionan estado, validación y renderizado Blade sin controladores intermedios ni API REST separada.
- **Si repregunta:** El único controlador tradicional es `EstadoSistemaController` (`__invoke`) porque `GET /salud` responde JSON sin estado. La lógica pesada se desacopla en `app/Services/`.
- **Mostrar en código:** `routes/web.php` (L24–94) y `app/Http/Controllers/EstadoSistemaController.php` (L8–17).

### P02. ¿Cómo funcionan los filtros en tiempo real del catálogo sin recargar la página?
- **Respuesta (30 s):** Mediante `wire:model.live` (con `debounce.300ms` en búsqueda) y el atributo `#[Url(except: '')]` en `PaginaPropiedades` y `PaginaPropiedadesOficina`.
- **Si repregunta:** Livewire sincroniza el estado vía AJAX, re-ejecuta `render()` con consultas Eloquent parametrizadas y actualiza la URL para que el enlace filtrado pueda compartirse.
- **Mostrar en código:** `app/Livewire/Publico/PaginaPropiedades.php` (L16–50).

### P03. ¿Qué configuraciones globales críticas agregaron en `AppServiceProvider`?
- **Respuesta (30 s):** `Fortify::ignoreRoutes()` (cierra rutas genéricas/registro), `Date::use(CarbonImmutable::class)` (fechas inmutables), `DB::prohibitDestructiveCommands(app()->isProduction())` y `URL::forceScheme('https')` en producción.
- **Mostrar en código:** `app/Providers/AppServiceProvider.php` (L17–56).

### P04. ¿Por qué existe `app/Support/TelefonoPeru.php`?
- **Respuesta (30 s):** Centraliza (principio DRY) el formateo del prefijo `+51` visible y `51...` para enlaces `wa.me`, evitando duplicar `+51` al editar formularios mediante `quitarPrefijo()`.
- **Mostrar en código:** `app/Support/TelefonoPeru.php` (L5–56).

---

## Bloque 2: Base de Datos, Migraciones, Restricciones y Modelos

### P05. ¿Cómo garantizan que nunca exista una propiedad con precio sin moneda (o viceversa)?
- **Respuesta (30 s):** Validación condicional en `GestionPropiedades.php` + restricción MySQL `CHECK` (`chk_propiedades_precio_moneda`) en la migración.
- **Mostrar en código:** `database/migrations/0001_01_01_000002_crear_tabla_propiedades.php` (L26–36) y `app/Livewire/Admin/GestionPropiedades.php` (L76–86).

### P06. ¿Cómo implementaron el patrón Singleton en `configuracion_contacto` y `configuracion_portada`?
- **Respuesta (30 s):** Llave primaria fija `id = 1` (`$incrementing = false`), `CHECK (id = 1)` en MySQL, bloqueo de creación duplicada y borrado en `booted()`, y acceso vía `obtenerUnica()` (`firstOrCreate`).
- **Mostrar en código:** `app/Models/ConfiguracionContacto.php` (L12–59) y `app/Models/ConfiguracionPortada.php` (L13–48).

### P07. ¿Por qué las 4 oficinas no pueden crearse ni eliminarse?
- **Respuesta (30 s):** Sus códigos y slugs (`/oficinas/{slug}`) son rutas SEO fijas y claves del catálogo; `Oficina::booted()` lanza `DomainException` ante creación, eliminación o cambio de código/slug, respaldado por `chk_oficinas_codigo_slug` en MySQL.
- **Mostrar en código:** `app/Models/Oficina.php` (L38–79) y `database/migrations/0001_01_01_000009_crear_tabla_oficinas.php` (L27–99).

### P08. ¿Qué son los campos `*_snapshot` en `posiciones_ranking` y qué hizo la migración 13?
- **Respuesta (30 s):** Congelan nombre, celular y fotografía exactos de cada ganador para mantener inmutable el historial. La migración 13 desacopló el ranking de `vendedores` (`vendedor_id` nullable, `codigo_interno` único `PART-...`, `celular_normalizado` único por período) sin perder registros históricos.
- **Mostrar en código:** `database/migrations/0001_01_01_000013_adaptar_posiciones_ranking_independiente.php` (L12–46).

### P09. ¿Por qué agregaron 5 Triggers en MySQL si ya tenían validaciones en Laravel?
- **Respuesta (30 s):** Defensa en profundidad de integridad: Eloquent `booted()` solo protege operaciones vía ORM, mientras que los 5 triggers `BEFORE` en `rankings_mensuales` y `posiciones_ranking` bloquean con `SIGNAL SQLSTATE '45000'` incluso modificaciones directas por SQL o Query Builder.
- **Mostrar en código:** `database/migrations/0001_01_01_000004_crear_tabla_rankings_mensuales.php` (L57–82) y `0001_01_01_000005_crear_tabla_posiciones_ranking.php` (L38–80).

---

## Bloque 3: Concurrencia, Transacciones y Ranking Mensual

### P10. ¿Qué pasa si dos usuarios editan o publican el Ranking Mensual al mismo tiempo?
- **Respuesta (30 s):** `ServicioRankingMensual` ejecuta todo dentro de `DB::transaction()` usando bloqueo pesimista `lockForUpdate()` sobre `rankings_mensuales` y sus `posiciones`, serializando escrituras concurrentes.
- **Mostrar en código:** `app/Services/ServicioRankingMensual.php` (L31–45, L71–166 y L191–228).

### P11. ¿Cómo funciona el bloqueo automático tras el día 3 sin romper la transición a `CERRADO`/`VENCIDO`?
- **Respuesta (30 s):** `dentroDePlazoDeGestion()` evalúa los días 1–3 contra `now('America/Lima')`. Pasado el día 3, `booted()` rechaza cambios excepto `esTransicionInternaDeCierre()`, que verifica con `getDirty()` que solo cambien `estado`, `fecha_cierre` y `updated_at`.
- **Mostrar en código:** `app/Models/RankingMensual.php` (L66–83 y L116–154).

### P12. ¿Cómo evitan archivos huérfanos en `storage` si falla el guardado en base de datos?
- **Respuesta (30 s):** En un `try/catch (Throwable)`, si falla la escritura en BD se borra inmediatamente la nueva foto subida; y si se reemplaza una foto existente, la anterior se elimina solo después del commit (`limpiarFotografiasReemplazadasPostCommit`).
- **Mostrar en código:** `app/Services/ServicioRankingMensual.php` (L168–183 y L284–316) y `app/Livewire/Admin/GestionPropiedades.php` (L133–152).

### P13. ¿Cómo validan celulares duplicados y detectan homónimos en el Ranking?
- **Respuesta (30 s):** `normalizarCelular()` reduce a dígitos y se compara con `hash_equals()` bloqueando duplicados (`DomainException` + índice único `uk_posicion_celular_periodo`). `normalizarNombreCompleto()` (`Str::ascii`) emite un aviso no bloqueante ante posibles homónimos.
- **Mostrar en código:** `app/Services/ServicioRankingMensual.php` (L96–118) y `app/Models/PosicionRanking.php` (L71–91).

---

## Bloque 4: Seguridad, Roles, 2FA y Ataques Web

### P14. ¿Qué ocurre si `MARKETING` intenta entrar a `/admin/usuarios` o si el Admin suspende una sesión activa?
- **Respuesta (30 s):** `VerificarRol` retorna `abort(403)` para roles no autorizados, y si `activo` es `false` cierra la sesión al instante (`Auth::logout()`, `session()->invalidate()`, `regenerateToken()`). Además, cada componente exclusivo vuelve a validar `esAdministrador()`.
- **Mostrar en código:** `app/Http/Middleware/VerificarRol.php` (L17–46) y `routes/web.php` (L78–94).

### P15. ¿Cómo protegen el login y el 2FA contra fuerza bruta?
- **Respuesta (30 s):** `RateLimiter` de máximo **5 intentos / 60 segundos** por correo+IP en login y por usuario+IP en 2FA; al autenticar se limpia el contador y se ejecuta `session()->regenerate()`.
- **Mostrar en código:** `app/Livewire/Autenticacion/IniciarSesion.php` (L41–63, L95–100 y L157–164).

### P16. ¿Cómo funciona internamente el 2FA con Laravel Fortify?
- **Respuesta (30 s):** `Usuario` usa `TwoFactorAuthenticatable`. Se habilita previa confirmación de contraseña, genera secreto cifrado + QR SVG + códigos de recuperación, y solo queda activo al confirmar un código TOTP de 6 dígitos (`two_factor_confirmed_at`). Los códigos de recuperación se validan con `hash_equals()` y se reemplazan al usarse.
- **Mostrar en código:** `app/Livewire/Settings/Security.php` (L37–199) y `app/Livewire/Autenticacion/IniciarSesion.php` (L66–139).

### P17. ¿Qué cabeceras HTTP de seguridad implementaron?
- **Respuesta (30 s):** `CabecerasSeguridad` (middleware global en `bootstrap/app.php`) inyecta `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `X-Permitted-Cross-Domain-Policies`, `Content-Security-Policy` y HSTS en HTTPS/producción. JSON-LD usa `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- **Mostrar en código:** `app/Http/Middleware/CabecerasSeguridad.php` (L14–44).

### P18. ¿Cómo impiden subir un script PHP disfrazado de imagen?
- **Respuesta (30 s):** Validación en servidor (`image|mimes:jpg,jpeg,png,webp|max:4096`), nombres seguros con `store()`, verificación GD al convertir a WebP y `storage/app/public/.htaccess` bloqueando ejecución de scripts y listado de directorios.
- **Mostrar en código:** `storage/app/public/.htaccess` y `app/Livewire/Admin/GestionPropiedades.php` (L88–90).

---

## Bloque 5: Optimización WebP, Respaldos, SEO y Disponibilidad

### P19. ¿Cómo lograron optimización WebP sin paquetes externos ni romper el CSS?
- **Respuesta (30 s):** `ServicioOptimizacionImagen` usa PHP GD nativo (`imagewebp()` calidad 82, conservando originales) y `<x-imagen-optimizada>` renderiza `<picture>` con `picture { display: contents; }` en CSS para no alterar cuadrículas.
- **Mostrar en código:** `app/Services/ServicioOptimizacionImagen.php` (L18–163) y `resources/views/components/imagen-optimizada.blade.php`.

### P20. ¿Cómo funcionan los respaldos sin exponer credenciales ni sobrescribir copias?
- **Respuesta (30 s):** `ServicioRespaldo` crea carpetas únicas por entorno y fecha en `storage/app/private/respaldos/`, ejecuta `mysqldump` con contraseña en variable efímera `MYSQL_PWD`, comprime `multimedia.zip` y genera `manifiesto.json` verificable por `SHA-256` con `hash_equals()`.
- **Mostrar en código:** `app/Services/ServicioRespaldo.php` (L19–215).

### P21. ¿Qué verifica `GET /salud` y cómo registran fallos de BD?
- **Respuesta (30 s):** Verifica modo mantenimiento, `SELECT 1` en MySQL y escritura en `storage/logs` y `storage/app/public`, respondiendo `200`/`503` con `Cache-Control: no-store`. `bootstrap/app.php` reporta `QueryException`/`PDOException` al canal diario `disponibilidad`.
- **Mostrar en código:** `app/Services/ServicioDisponibilidad.php` (L16–60) y `bootstrap/app.php` (L26–34).

### P22. ¿Cómo implementaron el SEO técnico?
- **Respuesta (30 s):** Metadatos por página, canonical, Open Graph, Twitter Cards, JSON-LD `RealEstateAgent` e `ItemList` con datos reales, `robots.txt` y `GET /sitemap.xml` dinámico.
- **Mostrar en código:** `resources/views/components/layouts/publico.blade.php` (L4–57) y `routes/web.php` (L33–62).

---

## Bloque 6: Pruebas y Producción

### P23. ¿Cómo comprobaron automáticamente todos los requerimientos?
- **Respuesta (30 s):** Suite PHPUnit (`php artisan test`) con **36 pruebas aprobadas y 157 aserciones**, incluyendo `RequerimientosNoFuncionalesTest.php` que valida WCAG, SEO/sitemap, `/salud`, respaldos SHA-256, cabeceras, error `403` por rol y conversión WebP.
- **Mostrar en código:** `tests/Feature/RequerimientosNoFuncionalesTest.php`.

### P24. ¿Qué se debe configurar obligatoriamente al desplegar en producción?
- **Respuesta (30 s):** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, Cron `* * * * * php artisan schedule:run`, `mysqldump` disponible, módulos Apache (`rewrite`, `deflate`, `expires`, `headers`), monitor externo hacia `/salud` y réplica externa de respaldos.
- **Mostrar en código:** `routes/console.php` (L11–12) y `documentacion/DOCUMENTACION_IMPLEMENTACION_RNF025_RNF030.md` (L137–153).
