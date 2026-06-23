<?php
/**
 * Copyright (C) 2026 Alexis Serafin <alexis@okodex.com>
 */

namespace FacturaScripts\Plugins\Alias\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;

/**
 * Catálogo de tipos de alias.
 *
 * @author Alexis Serafin <alexis@okodex.com>
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

    public function clear(): void
    {
        parent::clear();
        $this->creationdate = Tools::dateTime();
        $this->nick = Session::get('user')->nick ?? null;
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
        $this->creationdate = $this->creationdate ?? Tools::dateTime();
        $this->nick = $this->nick ?? Session::user()->nick;
        return parent::test();
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
