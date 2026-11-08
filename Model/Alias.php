<?php
/**
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://www.gnu.org/licenses/.
 */

namespace FacturaScripts\Plugins\Alias\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AliasType;

/**
 * Alias genérico y polimórfico: asocia un 'alias' a una entidad identificada
 * por (aliastype, cod). El tipo se valida contra el catálogo aliastypes.
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class Alias extends ModelClass
{
    use ModelTrait;

    /** longitud de la columna alias (Table/alias.xml) */
    const ALIAS_MAX_LENGTH = 100;

    /** @var string */
    public $alias;

    /** @var string */
    public $aliastype;

    /** @var string */
    public $cod;

    /** @var string */
    public $creationdate;

    /** @var bool */
    public $favorite;

    /** @var int */
    public $id;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var string */
    public $nick;

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
        $this->favorite = false;
        $this->nick = Session::get('user')->nick ?? null;
    }

    public function install(): string
    {
        // la tabla aliastypes debe existir antes (FK aliastype)
        new AliasType();
        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public function primaryDescription(): ?string
    {
        return $this->alias ?? null;
    }

    public function save(): bool
    {
        if (false === parent::save()) {
            return false;
        }

        // solo puede haber un favorito por entidad (aliastype + cod)
        if ($this->favorite && false === $this->clearOtherFavorites()) {
            return false;
        }

        return true;
    }

    public static function tableName(): string
    {
        return "alias";
    }

    public function test(): bool
    {
        $this->alias = Tools::noHtml($this->alias);
        $this->aliastype = Tools::noHtml($this->aliastype);
        $this->cod = Tools::noHtml($this->cod);

        // se mide después de escapar: cada ' pasa a &#39; y el texto crece
        if (mb_strlen((string)$this->alias) > self::ALIAS_MAX_LENGTH) {
            Tools::log()->warning('alias-too-long', ['%max%' => self::ALIAS_MAX_LENGTH]);
            return false;
        }

        $this->creationdate = $this->creationdate ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        return parent::test();
    }

    // desmarca el resto de favoritos de la misma entidad, en un único UPDATE:
    // guardarlos uno a uno dejaba dos favoritos si alguno no se podía guardar
    protected function clearOtherFavorites(): bool
    {
        $done = self::table()
            ->whereEq('aliastype', $this->aliastype)
            ->whereEq('cod', $this->cod)
            ->whereEq('favorite', true)
            ->whereNotEq('id', $this->id)
            ->update(['favorite' => false]);
        if (false === $done) {
            Tools::log()->error('alias-favorite-clear-error', [
                '%aliastype%' => $this->aliastype,
                '%cod%' => $this->cod,
            ]);
            return false;
        }

        $this->clearCache();
        return true;
    }

    protected function saveInsert(): bool
    {
        $this->lastnick = Session::get('user')->nick ?? null;
        $this->lastupdate = Tools::dateTime();
        return parent::saveInsert();
    }

    protected function saveUpdate(): bool
    {
        $this->lastnick = Session::get('user')->nick ?? null;
        $this->lastupdate = Tools::dateTime();
        return parent::saveUpdate();
    }
}
