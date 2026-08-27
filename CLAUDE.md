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

## Dónde se desarrolla

**Cliente prototipo: `Mesa_FS`.** Aquí se desarrolla este plugin. Las demás instalaciones lo
**consumen** como submódulo fijado a un tag, y son fuente de mejoras y arreglos.

Si lees esto desde otra instalación estás en un consumidor, y eso **no te prohíbe trabajar aquí**:
a veces el fallo solo se reproduce en este entorno y arreglarlo desde el prototipo sería trabajar a
ciegas. Lo que se pide es **preguntarlo antes**, no decidirlo en silencio — ni negarse.

Se trabaje donde se trabaje, el arreglo vive en el repo del plugin y hay que **mover el pin** de
esta instalación para que lo reciba: commitear no basta.

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

- `id` serial PK; `alias` **varchar(100)** NOT NULL (el texto del alias); `aliastype` + `cod` (el par
  polimórfico, ambos NOT NULL); `favorite` boolean NOT NULL default false.
  - `alias` era varchar(50) hasta la v1.01. Se amplió porque el volcado de POI de la BD de reservas
    trae alias de hasta 56 caracteres, y recortarlos puede provocar colisiones nuevas contra el
    UNIQUE. `cod` sigue en varchar(50) (guarda identificadores) y `nick`/`lastnick` también, porque
    son FK a `users.nick`.
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
## Cómo lo integra un plugin satélite → está en el ÁRBOL

Ese conocimiento se movió a `arbol/FS/Alias/` del árbol de conocimiento, y no por orden: **quien más
lo necesita es quien menos lo veía**. Un agente trabajando en `src/Plugins/AliasBusCanarias` carga su
propio `CLAUDE.md` y el de la instalación, pero **no éste** — `Alias` es un hermano suyo, no un
ancestro. Desde el árbol sí le llega, porque su nodo cuelga de `Alias`.

Cuatro ficheros de hecho, que `okoconocimiento contexto` entrega solo:

- **`patron-de-integracion-de-un-satelite.md`** — los 7 pasos y el reparto de responsabilidades.
- **`cada-test-devuelve-true-para-los-tipos-ajenos.md`** — la regla de convivencia entre satélites.
- **`no-hay-clave-ajena-el-cascade-lo-simula-el-satelite.md`** — por qué el borrado es del satélite.
- **`codigos-de-tipo-ya-usados.md`** — qué códigos de `aliastype` están ocupados y por quién.

Aquí se queda lo que se lee **estando dentro**: el concepto, las columnas, el modelo, los
controladores, los tests y las convenciones.

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
- **⚠ Cambiar la longitud de `alias` trunca en los dos sentidos.** `DbUpdater` **sí** sincroniza el
  tipo de columna (`Core/DbUpdater.php:297-301` → `sqlAlterModifyColumn`, motivo
  `change-column-type`), y lo hace de forma **bidireccional**: desplegar una rama cuyo
  `Table/alias.xml` diga `varchar(50)` **contra una BD que ya tiene `varchar(100)`** emite un
  `MODIFY ... varchar(50)` y **MySQL trunca los alias largos sin avisar**. Es pérdida de datos
  silenciosa. Muerde al hacer `git checkout` a una rama vieja y desplegar sin reparar en que la BD
  no ha cambiado. Al alternar entre ramas con distinta longitud, comprobar antes a qué BD apunta
  `config.php`.
- **`maxlength` del widget va en cada satélite**: al cambiar la longitud de la columna hay que subir
  también el `maxlength` de `fieldname="alias"` en las XMLView de *todos* los satélites (hoy 6
  ficheros en 4 plugins), o el formulario cortará antes que la base de datos.

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
