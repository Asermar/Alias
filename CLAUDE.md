# Alias — guía para Claude Code

Plugin **base** de **FacturaScripts** que ofrece **alias polimórficos reutilizables**: nombres
alternativos (apodos, denominaciones comerciales, abreviaturas…) asociados a cualquier entidad del
programa. `version 1.03`, `min_version 2025` (requiere `FacturaScripts\Core\Template\ModelClass`,
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
- **`AliasType::ensure($code, $description, $plugin): ?self`** es el punto de entrada para los
  satélites, y **el único camino de escritura del catálogo**. Idempotente: si el tipo existe y no
  tiene `plugin`, se lo rellena; si no existe, lo crea. Pensado para llamarse desde el `update()` de
  cualquier plugin que amplíe Alias. Si no puede guardar devuelve **`null`** y deja un error
  (`alias-type-ensure-error`) — sin excepción, porque puede correr dentro de una migración, que no la
  admite—; si el tipo ya existe con **otro** `plugin`, lo devuelve y avisa (`alias-type-other-plugin`).
- **El modelo rechaza lo demás**: `saveInsert()`/`saveUpdate()` fuera de `ensure()` devuelven false
  con aviso (`alias-type-only-by-plugin`), y `delete()` siempre (`alias-type-cannot-delete`). Así queda
  cerrado también para la API REST del core, que escribe a través del modelo. Ningún flujo de
  producción borra tipos (los `uninstall()` de los satélites están vacíos); los tests limpian sus
  tipos de prueba por SQL directo (`AliasType::table()->whereEq(...)->delete()`).

## Modelo `Alias` (Model/Alias.php)

`ModelClass` + `ModelTrait`. Puntos a respetar:

- **`install()`** instancia `new AliasType()` antes de `parent::install()` para forzar que la tabla
  `aliastypes` exista primero (la FK `alias.aliastype → aliastypes` lo exige).
- **Un único favorito por entidad**: `favorite` marca el alias "principal" de una entidad. La
  unicidad se garantiza en **`save()`** (no en `test()`): tras persistir, si `favorite` está, llama
  a `clearOtherFavorites()`, que desmarca los demás alias con el mismo `(aliastype, cod)` en **un
  único UPDATE** (si falla, error y `save()` devuelve false). Antes guardaba uno a uno e ignoraba el
  resultado, así que un alias que no se pudiera guardar seguía siendo favorito: dos en silencio.
  **Importante**: el ámbito del favorito es la **entidad** `(aliastype + cod)`, mientras que el
  UNIQUE de BD es por **tipo** `(aliastype + alias)` — son dos ámbitos distintos, no confundirlos.
- `test()` sanea con `Tools::noHtml()` y **mide la longitud después** (`ALIAS_MAX_LENGTH = 100`, la
  de la columna): cada `'` pasa a `&#39;`, así que 98 caracteres tecleados con dos apóstrofos son 106
  guardados; por encima rechaza con `alias-too-long`. También sella `creationdate`/`nick`; `saveInsert`/`saveUpdate`
  fijan `lastnick`/`lastupdate`. `clear()` inicializa `creationdate`, `favorite=false` y `nick` con
  el usuario de sesión.
- `primaryColumn()` = `id`; `primaryDescription()` = `alias`; `tableName()` = `alias`.

`AliasType` es un catálogo plano análogo (misma auditoría, `ensure()`, `primaryDescription()`
devuelve `description` y si no `aliastype`).

## Controladores (Controller/)

- **`ListAlias`** (`ListController`, menú `admin`): dos pestañas — `ListAlias` (con filtro por
  `aliastype` cuyas etiquetas se traducen con `Tools::trans`, y checkbox `favorite`) y
  `ListAliasType` (con filtro por `plugin`). **La pestaña de tipos es sólo una lista**: sin `btnNew`,
  sin `btnDelete`, sin `checkBoxes` (el `ListController` borra con las casillas de la pestaña activa) y
  sin `clickable` (no hay ficha a la que ir).
- **`EditAlias`** (`EditController`): edición de un alias suelto.
- **`EditAliasType` se retiró en el hotfix de la auditoría de base.** **Regla de diseño**: los tipos de
  alias no se crean, cambian ni borran a mano; los registra el plugin que aporta operativa para ellos,
  vía `AliasType::ensure()` en su `update()`. Antes la ficha sólo escondía el botón «Nuevo»
  (`btnNew`), y `PanelController::insertAction` no lo mira, así que se podían crear por POST; y como
  `alias.aliastype` es `ON UPDATE CASCADE`, renombrar un tipo descolgaba sus alias de los satélites.
  Se retiró en vez de dejarla rechazando acciones porque el core limpia solo lo que deja un
  controlador borrado: al desplegar con `initControllers` (actualizar por zip un plugin activo, o
  activarlo) `PluginsDeploy::removeOldPages()` borra su fila de `pages`, la ruta sale de
  `routes.json` y la URL da 404. Quedan filas inocuas en `roles_access`/`pages_options`/`pages_filters`
  con ese nombre (no tienen FK a `pages`).

## Guarda de la pestaña de alias de un satélite (`Lib/AliasTabGuard`)

La pestaña que cada satélite añade a su ficha (EditListView sobre `Alias`) filtra en `loadData()` los
alias de la ficha, pero el formulario manda `id`, `aliastype` y `cod`, y las acciones del core cargan
el registro por su código. Sin guarda, con permiso en la ficha se podía crear, editar o borrar
**cualquier** alias del sistema. `AliasTabGuard::protect($request, $activeTab, $tabName, $aliasType,
$mainModel)` lo cierra para las tres vías:

- **alta**: el `aliastype` y el `cod` enviados tienen que ser los de la ficha (se **rechaza**, no se
  corrige: la interfaz legítima ya los manda, autorrellenados por el `Where` de `loadData()`);
- **edición**: además, el alias cargado por `code` tiene que ser de la ficha, y un `id` enviado tiene
  que coincidir con `code`;
- **borrado**: cada alias de `codes[]` (o el `code` único, como `BaseController::deleteAction()`).

Cada satélite la llama en su extensión **`createViews()`**, después de `addEditListView()`. Es lo
último que corre antes de las acciones: el gancho `execPreviousAction` de una extensión corre
**después** de que el core haya escrito (`PanelController::privateCore`). La ficha se carga con la
misma expresión que `EditController::loadData()`, porque en ese momento aún no está cargada.

Si algo no cuadra, avisa (`alias-tab-out-of-scope`) y **anula la acción vaciando `action` en el
cuerpo y en la query**, que es de donde la lee el core (`inputOrQuery`). No toca
`allowUpdate`/`allowDelete`: ese objeto es el que el core guarda en la sesión, y cambiaría también lo
que pinta la página. El token de formulario no llega a consumirse (sólo lo validan las acciones) y la
página se pinta con uno nuevo, así que el usuario sigue trabajando con normalidad.

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

`install-plugins.txt` = solo `Alias`. Cubre: que los tipos **no** se crean, cambian ni borran a mano
y que `ensure()` los crea, avisa si el tipo es de otro plugin y devuelve `null` si no puede guardar
(con una extensión de prueba `saveBefore`); que la clase `EditAliasType` ya no existe y que la pestaña
`ListAliasType` es de sólo lectura (Reflection sobre `createViews()`); alta/baja de `Alias`,
`favorite=false` por defecto, **un único favorito por entidad** también cuando el otro favorito no se
puede guardar; **UNIQUE por tipo**; y la longitud medida tras escapar. Las aserciones sobre el log van
por la clave sin traducir (`MiniLog::read()[...]['original']`). Los tipos de prueba (`unittest*`) se
crean con `ensure()` y se limpian por SQL; `setUp` borra antes los alias de prueba que hubiera dejado
una pasada muerta a medias.

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
- **Tipos solo por código**: `AliasType::ensure()` en el `update()` del plugin responsable; el modelo
  rechaza cualquier otra alta, cambio o borrado.
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

`Model/` (`Alias`, `AliasType`), `Lib/` (`AliasTabGuard`), `Table/` (XML de tablas), `Controller/` + `XMLView/` (Edit/List de
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
