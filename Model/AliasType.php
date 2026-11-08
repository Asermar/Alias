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

/**
 * Catálogo de tipos de alias. Lo llena cada plugin con ensure() al instalarse: a mano
 * (interfaz, API REST, código) no se crean, no se cambian ni se borran.
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class AliasType extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $aliastype;

    /** @var string */
    public $creationdate;

    /** @var string */
    public $description;

    /** @var int */
    public $id;

    /** @var string */
    public $lastnick;

    /** @var string */
    public $lastupdate;

    /** @var string */
    public $nick;

    /** @var string */
    public $plugin;

    /** @var bool sólo ensure() la levanta, alrededor de su save() */
    private static $ensuring = false;

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
        $this->nick = Session::get('user')->nick ?? null;
    }

    /**
     * Asegura que un tipo de alias exista en el catálogo, registrando el plugin
     * responsable de crearlo. Si ya existe pero no tiene plugin asignado, lo rellena.
     * Pensado para llamarse desde el update() de cualquier plugin que amplíe Alias.
     *
     * Devuelve null si no pudo guardar, con un error en el log. No lanza excepción:
     * puede correr dentro de una migración, que no la admite.
     */
    public static function ensure(string $code, string $description, string $plugin): ?self
    {
        $type = new self();
        if ($type->loadWhere([Where::eq('aliastype', $code)])) {
            if (empty($type->plugin)) {
                $type->plugin = $plugin;
                return $type->saveFromEnsure() ? $type : null;
            }

            if ($type->plugin !== $plugin) {
                Tools::log()->warning('alias-type-other-plugin', [
                    '%aliastype%' => $code,
                    '%plugin%' => $type->plugin,
                    '%other%' => $plugin,
                ]);
            }
            return $type;
        }

        $type->aliastype = $code;
        $type->description = $description;
        $type->plugin = $plugin;
        return $type->saveFromEnsure() ? $type : null;
    }

    public function delete(): bool
    {
        Tools::log()->warning('alias-type-cannot-delete', ['%aliastype%' => $this->aliastype]);
        return false;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public function primaryDescription(): ?string
    {
        return $this->description ?? $this->aliastype ?? null;
    }

    public static function tableName(): string
    {
        return "aliastypes";
    }

    public function test(): bool
    {
        $this->aliastype = Tools::noHtml($this->aliastype);
        $this->description = Tools::noHtml($this->description);
        $this->plugin = Tools::noHtml($this->plugin);
        $this->creationdate = $this->creationdate ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        return parent::test();
    }

    protected function saveInsert(): bool
    {
        if (false === self::$ensuring) {
            Tools::log()->warning('alias-type-only-by-plugin', ['%aliastype%' => $this->aliastype]);
            return false;
        }

        $this->lastnick = Session::get('user')->nick ?? null;
        $this->lastupdate = Tools::dateTime();
        return parent::saveInsert();
    }

    protected function saveUpdate(): bool
    {
        if (false === self::$ensuring) {
            Tools::log()->warning('alias-type-only-by-plugin', ['%aliastype%' => $this->aliastype]);
            return false;
        }

        $this->lastnick = Session::get('user')->nick ?? null;
        $this->lastupdate = Tools::dateTime();
        return parent::saveUpdate();
    }

    private function saveFromEnsure(): bool
    {
        self::$ensuring = true;
        try {
            $saved = $this->save();
        } finally {
            self::$ensuring = false;
        }

        if (false === $saved) {
            Tools::log()->error('alias-type-ensure-error', ['%aliastype%' => $this->aliastype]);
        }
        return $saved;
    }
}
