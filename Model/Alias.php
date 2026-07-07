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
use FacturaScripts\Core\Where;
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
        if ($this->favorite) {
            $this->clearOtherFavorites();
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
        $this->creationdate = $this->creationdate ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        return parent::test();
    }

    // desmarca el resto de favoritos de la misma entidad
    protected function clearOtherFavorites(): void
    {
        $where = [
            Where::eq('aliastype', $this->aliastype),
            Where::eq('cod', $this->cod),
            Where::eq('favorite', true),
            Where::column('id', $this->id, '!='),
        ];
        foreach (self::all($where) as $other) {
            $other->favorite = false;
            $other->save();
        }
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
