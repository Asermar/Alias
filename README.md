# Alias

<p align="center">
  <a href="https://github.com/Asermar/Alias/tags"><img alt="Versión" src="https://img.shields.io/github/v/tag/Asermar/Alias?style=for-the-badge&label=Versi%C3%B3n&color=2E7D6E"></a>
  <img alt="FacturaScripts" src="https://img.shields.io/badge/FacturaScripts-core_2025%2B-2670c9?style=for-the-badge">
  <img alt="Dependencias" src="https://img.shields.io/badge/Plugin-base_sin_dependencias-6C4FD8?style=for-the-badge">
  <a href="LICENSE"><img alt="Licencia" src="https://img.shields.io/github/license/Asermar/Alias?style=for-the-badge&color=A42E2B"></a>
</p>

Plugin **base** de FacturaScripts que añade **alias polimórficos**: nombres alternativos —apodos,
denominaciones comerciales, abreviaturas— asociados a **cualquier** entidad del programa.

Un alias es una fila que apunta a su entidad mediante dos columnas: el **tipo** (`aliastype`, que dice
a qué clase de entidad apunta) y el **código** del registro concreto (`cod`). El modelo base es
**agnóstico** del destino: no conoce `Cliente`, `Pais` ni ninguna otra clase, solo el par
`(tipo, código)` y el texto del alias. Quien sabe a qué modelo corresponde cada tipo es el plugin que
lo registra.

## Funcionalidades

- **Catálogo de tipos de alias** (`aliastypes`): cada tipo declara a qué entidad se refiere y qué
  plugin lo mantiene. Con integridad real en base de datos y borrado restringido, así que no se puede
  eliminar un tipo que aún tenga alias.
- **Alias con texto de hasta 100 caracteres**, marcables como **favorito**, y con **auditoría** de alta
  y última modificación (fecha y usuario).
- **Un texto no se repite dentro del mismo tipo**: el índice único es por `(tipo, alias)`, de modo que
  dos entidades del mismo tipo no pueden compartir denominación. Es lo que hace que un alias sirva para
  **buscar** sin ambigüedad.
- **Listado y fichas propias** (`ListAlias`, `EditAlias`, `EditAliasType`) para gestionar tipos y alias
  sin depender de ningún satélite.
- **Sin dependencias**: es el plugin del que dependen los demás, no depende de ninguno.

## La familia

`Alias` no integra nada por sí solo: aporta el mecanismo, y cada **satélite** lo enchufa en un área del
programa registrando sus tipos y validando que el código apunte a un registro que existe.

| Plugin | Qué integra | Tipos que registra |
|---|---|---|
| [AliasClientes](https://github.com/Asermar/AliasClientes) | Fichas de clientes y proveedores | `client`, `supplier` |
| [AliasLocalizaciones](https://github.com/Asermar/AliasLocalizaciones) | Países, provincias, ciudades y puntos de interés | `country`, `province`, `city`, `poi` |
| AliasBusCanarias | Catálogos de navieras y líneas de BusCanarias | `shippingcompany`, `shippingline` |

## Integrarlo desde otro plugin

Un satélite hace tres cosas: **registra** sus tipos en el catálogo al instalarse, **extiende** las
fichas de sus entidades con una pestaña de alias, y **valida en tiempo de ejecución** que el código del
alias corresponda a un registro real (la relación con la entidad destino no la conoce la base de datos,
así que la comprueba el plugin) además de borrar sus alias en cascada al eliminar la entidad.

## Requisitos

- FacturaScripts con core **2025** o superior (usa `FacturaScripts\Core\Template\ModelClass`).
- PHP **8** o superior.

## Changelog

Cambios destacados por versión (la versión es la de `facturascripts.ini`, único punto de verdad).

### 1.0x — Alias polimórficos, estables

- **1.01** — El texto del alias pasa de 50 a **100 caracteres**. Cincuenta se quedaban cortos en uso
  real, y recortar el texto no era una salida: al acortarlo puede chocar con otro alias del mismo tipo
  y saltar el índice único. Se añade un test que comprueba que un alias de 100 caracteres no se recorta.
- **1.00** — Primera versión estable: licencia **GPL v3** y compatibilidad declarada de forma explícita
  (core **2025** y PHP **8**), que es la clase del core de la que depende el modelo.

> Las versiones `0.x` están en el historial de git; no se reconstruyen aquí para no inventar notas que
> nunca se escribieron.

## Licencia

GNU General Public License v3 — ver [LICENSE](LICENSE).

Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
@author Alexis Serafín <alexis@okodex.com>
