# Alias — guía para Claude Code

Plugin **base** de **FacturaScripts** que ofrece **alias polimórficos reutilizables**: nombres
alternativos (apodos, denominaciones comerciales, abreviaturas…) asociados a cualquier entidad del
programa. `version 1.00`, `min_version 2025` (requiere `FacturaScripts\Core\Template\ModelClass`,
introducida en el core v2025), `min_php 8`. **No tiene `require`**: es el plugin del que dependen
los satélites **AliasClientes** (clientes/proveedores), **AliasLocalizaciones** (países/provincias/
ciudades/PDI) y **AliasBusCanarias** (navieras/líneas de BusCanarias). Licencia **GPL v3** (open
source). Copyright **Okodex** / autor **Alexis Serafín**. Repo:
`git@github.com:Asermar/Alias.git` (submódulo dentro de Mesa_FS).

> Para desarrollar aquí, apoyarse en los agentes/skills **`fs-dev:*`** (docs-expert para el patrón
> del framework, backend-developer / extension-developer para modelos y extensiones, etc.) y que
> revisen la implementación.

## Concepto: alias polimórfico

Un **alias** es una fila de la tabla `alias` que apunta a **cualquier** entidad mediante dos
columnas discriminantes, sin FK real hacia la tabla destino:

- **`aliastype`** (varchar 30): el **tipo**, es decir *a qué clase de entidad* apunta el alias
  (`client`, `supplier`, `country`, `city`, `shippingcompany`…). Es FK **real** a `aliastypes`
  (catálogo, `ON DELETE RESTRICT ON UPDATE CASCADE`).
- **`cod`** (varchar 50): el **identificador** del registro concreto de esa entidad (su clave
  primaria / código: `codcliente`, id de país, id de naviera…). Es una **FK "simulada"**: la base
  de datos no la conoce; la valida en runtime cada plugin satélite (ver abajo).

Es el patrón *polymorphic association* (equivalente a `model`+`modelid`), pero aquí la parte "model"
se resuelve por el **catálogo `aliastypes`** en vez de guardar el nombre de clase: cada `aliastype`
tiene un `plugin` responsable, y ese plugin es quien sabe a qué modelo PHP corresponde el tipo. El
modelo base `Alias` es **agnóstico** del destino: no importa `Cliente`, `Pais` ni ninguna otra
clase; solo conoce `(aliastype, cod, alias)`.

### Columnas clave (`Table/alias.xml`)

- `id` serial PK; `alias` varchar(50) NOT NULL (el texto del alias); `aliastype` + `cod` (el par
  polimórfico, ambos NOT NULL); `favorite` boolean NOT NULL default false.
- **Único `(aliastype, alias)`** (`alias_aliastype_alias_uniq`): dentro de un tipo no se repite el
  mismo texto de alias. **Ojo**: el UNIQUE es por *tipo*, no por *entidad* — dos entidades distintas
  del mismo tipo no pueden compartir texto de alias.
- Auditoría: `creationdate`/`nick` (alta) y `lastupdate`/`lastnick` (última modificación), estos
  dos con FK a `users.nick` (`ON DELETE SET NULL`).

### Catálogo `aliastypes` (`Table/aliastypes.xml`, modelo `AliasType`)

- `id` serial PK; `aliastype` varchar(30) **UNIQUE** (el código del tipo, lo que referencia
  `alias.aliastype`); `description` varchar(100); `plugin` varchar(50) (plugin responsable);
  auditoría igual que `alias`.
- **`AliasType::ensure($code, $description, $plugin): self`** es el punto de entrada para los
  satélites. Idempotente: si el tipo existe y no tiene `plugin`, se lo rellena; si no existe, lo
  crea. Pensado para llamarse desde el `update()` de cualquier plugin que amplíe Alias.

## Modelo `Alias` (Model/Alias.php)

`ModelClass` + `ModelTrait`. Puntos a respetar:

- **`install()`** instancia `new AliasType()` antes de `parent::install()` para forzar que la tabla
  `aliastypes` exista primero (la FK `alias.aliastype → aliastypes` lo exige).
- **Un único favorito por entidad**: `favorite` marca el alias "principal" de una entidad. La
  unicidad se garantiza en **`save()`** (no en `test()`): tras persistir, si `favorite` está, llama
  a `clearOtherFavorites()`, que desmarca los demás alias con el mismo `(aliastype, cod)`.
  **Importante**: el ámbito del favorito es la **entidad** `(aliastype + cod)`, mientras que el
  UNIQUE de BD es por **tipo** `(aliastype + alias)` — son dos ámbitos distintos, no confundirlos.
- `test()` sanea con `Tools::noHtml()` y sella `creationdate`/`nick`; `saveInsert`/`saveUpdate`
  fijan `lastnick`/`lastupdate`. `clear()` inicializa `creationdate`, `favorite=false` y `nick` con
  el usuario de sesión.
- `primaryColumn()` = `id`; `primaryDescription()` = `alias`; `tableName()` = `alias`.

`AliasType` es un catálogo plano análogo (misma auditoría, `ensure()`, `primaryDescription()`
devuelve `description` y si no `aliastype`).

## Controladores (Controller/)

- **`ListAlias`** (`ListController`, menú `admin`): dos pestañas — `ListAlias` (con filtro por
  `aliastype` cuyas etiquetas se traducen con `Tools::trans`, y checkbox `favorite`) y
  `ListAliasType` (con filtro por `plugin`). **La pestaña de tipos tiene `btnNew` desactivado**.
- **`EditAlias`** (`EditController`): edición de un alias suelto.
- **`EditAliasType`** (`EditController`): edición de un tipo, con **`btnNew` desactivado** en
  `createViews()`. **Regla de diseño**: los tipos de alias **no** se crean a mano desde
  administración; solo los crea el plugin que aporta operativa para ellos, vía `AliasType::ensure()`
  en su `update()`. Al desarrollar un satélite, nunca insertes tipos por UI.

## Cómo lo integra un plugin satélite (patrón, CLAVE)

Los satélites **no** definen modelos ni tablas nuevas: reutilizan `Alias`/`aliastypes` y solo
aportan **tipos + validación + UI en la ficha destino**. Los tres existentes (AliasClientes,
AliasLocalizaciones, AliasBusCanarias) siguen el **mismo molde**. Para añadir alias a una entidad X:

1. **`facturascripts.ini`**: `require = 'Alias'` (y el plugin dueño de X si X no es del core, p. ej.
   AliasBusCanarias hace `require = 'Alias,BusCanarias'` con `min_version = 2026`).

2. **`Init::init()`** — registra por `loadExtension()`:
   - Una **extensión de controlador** por cada ficha destino (`Extension\Controller\EditX`).
   - Una **extensión de modelo destino** por cada X (`Extension\Model\X`) para el borrado en cascada.
   - **Una** extensión `Extension\Model\Alias` (validación de `cod`).

3. **`Init::update()`** — registra los tipos con `AliasType::ensure(CODIGO, 'Descripción', PLUGIN)`.
   Los códigos de tipo se guardan como **constantes** en el `Init` del satélite
   (`const ALIAS_TYPE_CLIENT = 'client'`, `PLUGIN_NAME = '...'`) y se reutilizan en las extensiones.

4. **Extensión del controlador destino** (`Extension\Controller\EditX`) — dos closures:
   - `createViews()` → `$this->addEditListView('EditAliasX', 'Alias', 'aliases', 'fa-solid fa-tags')`
     (añade una pestaña de tipo *EditList* sobre el modelo `Alias`).
   - `loadData($viewName, $view)` → si `$viewName === 'EditAliasX'`, construye un `Where` por
     `aliastype = TIPO` y `cod = $this->views[$mvn]->model->id()` (id del registro actual) y llama a
     `$view->loadData('', $where)`. **Ese mismo `Where` autorrellena `aliastype` y `cod`** en el
     formulario de alta de la EditListView, así el usuario solo escribe el texto del alias.

5. **Extensión del modelo destino** (`Extension\Model\X`) — closure `delete()` que **simula el
   `ON DELETE CASCADE`** ausente en BD: recorre `Alias::all([aliastype=TIPO, cod=$this->id...])` y
   borra cada alias. Sin esto quedarían alias huérfanos (no hay FK real que los limpie).

6. **Extensión `Extension\Model\Alias`** — closure `test()` que **valida la FK simulada**: mapea
   `aliastype → clase modelo` (`['client' => Cliente::class, ...]`); si el `aliastype` no está en su
   mapa, devuelve `true` (no es asunto suyo); si está, hace `$model->load($this->cod)` y si falla
   loguea `alias-cod-not-found` y devuelve `false`. **Convivencia de varios satélites**: cada plugin
   registra su propia extensión `test()` sobre `Alias`; el core las ejecuta **todas** como pipes, y
   como cada una devuelve `true` para los tipos que no conoce, no se pisan entre sí (patrón
   deliberado: por eso el "no conozco este tipo → true" es obligatorio en cada `test()`).

7. **`XMLView/EditAliasX.xml`** — vista de la EditListView: columnas `aliastype` y `cod` en
   `display="none"` (las rellena el `Where` del paso 4), más `alias` y `favorite` visibles.

### Resumen del reparto de responsabilidades

| Pieza | Dónde vive | Qué hace |
|-------|-----------|----------|
| Tabla `alias`/`aliastypes`, modelo `Alias`/`AliasType`, favorito único | **Alias** (base) | Almacén y reglas genéricas |
| `aliastype → modelo destino` | satélite (`Extension\Model\Alias::test`) | Valida `cod` (FK simulada) |
| Borrado en cascada de alias | satélite (`Extension\Model\X::delete`) | Limpia huérfanos |
| Pestaña de alias en la ficha | satélite (`Extension\Controller\EditX` + `XMLView`) | UI |
| Alta de tipos | satélite (`Init::update` → `AliasType::ensure`) | Registra el tipo con su plugin |

## Tipos registrados por los satélites (referencia)

- **AliasClientes**: `client` → `Cliente`, `supplier` → `Proveedor`. Extiende `EditCliente`/
  `EditProveedor`. Tiene además `Cron.php` (plantilla vacía) y traducciones a muchos idiomas.
- **AliasLocalizaciones**: `country` → `Pais`, `province` → `Provincia`, `city` → `Ciudad`,
  `poi` → `PuntoInteresCiudad`. Un solo `XMLView/EditAliasLocalizacion.xml` compartido por las
  cuatro fichas.
- **AliasBusCanarias**: `shippingcompany` → `NavieraServicioTour`, `shippingline` →
  `LineaServicioTour` (catálogos de BusCanarias). `require = 'Alias,BusCanarias'`, `min_version 2026`.

## Tests (Test/main/AliasTest.php)

`install-plugins.txt` = solo `Alias`. Cubre: alta/baja de `AliasType` y `Alias`; `favorite=false`
por defecto; **un único favorito por entidad** (`testOnlyOneFavoritePerEntity`); **UNIQUE por tipo**
(`testUniqueAliasPerType`, limpia el MiniLog tras la violación esperada); y que `EditAliasType` /
`ListAliasType` **no** permiten crear (`btnNew=false`) vía Reflection sobre `createViews()`. Usa un
tipo de pruebas `unittest` creado en `setUp` y borrado en `tearDownAfterClass`.

## Convenciones y gotchas

- **Cabeceras de autoría**: archivos nuevos → autor Alexis (Okodex); modificados → añadir a Alexis.
  Licencia GPL v3.
- **Rebuild/deploy** tras tocar `Init.php`, modelos o extensiones (regenera `Dinamic`; si no, la
  extensión no se registra). Es submódulo dentro de Mesa_FS: commitear aquí y actualizar el puntero
  en el padre.
- **`test()` de extensión debe devolver `true` para tipos ajenos**: es lo que permite que varios
  satélites coexistan sobre el mismo `Alias`. No romper esta invariante al añadir un satélite nuevo.
- **No hay FK real en `cod`**: el borrado en cascada y la validación son **responsabilidad del
  satélite**. Si añades un tipo y olvidas la extensión `delete()` del modelo destino, tendrás alias
  huérfanos; si olvidas la extensión `test()`, se admitirán `cod` inexistentes.
- **Favorito (por entidad) vs. UNIQUE (por tipo)**: dos ámbitos distintos, error fácil al razonar
  sobre duplicados.
- **Tipos solo por código**: no crear `aliastypes` desde la UI; siempre `AliasType::ensure()` en el
  `update()` del plugin responsable.

## Otras carpetas

`Model/` (`Alias`, `AliasType`), `Table/` (XML de tablas), `Controller/` + `XMLView/` (Edit/List de
ambas entidades), `Translation/` (es_ES + en_EN), `Test/main/`, `Init.php` (vacío: el plugin base no
registra extensiones ni tipos; eso lo hacen los satélites).

## Dudas / notas para verificar

- El `Init.php` del plugin base está **completamente vacío** (init/uninstall/update sin cuerpo): es
  correcto — el plugin base no aporta tipos ni operativa, solo el almacén y los controladores de
  administración. Toda la lógica de integración vive en los satélites.
- `AliasClientes` trae `Cron.php` con la plantilla estándar **sin lógica** (no ejecuta nada); parece
  arrastre del generador de plugins, no funcionalidad real.
- La descripción del `favorite` como "principal" es inferida del comportamiento (único por entidad);
  el código no documenta su semántica de negocio más allá de eso.
