<?php
/**
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 */

namespace FacturaScripts\Plugins\Alias\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

/**
 * Catálogo de tipos de alias.
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
     */
    public static function ensure(string $code, string $description, string $plugin): self
    {
        $type = new self();
        if ($type->loadWhere([Where::eq('aliastype', $code)])) {
            if (empty($type->plugin)) {
                $type->plugin = $plugin;
                $type->save();
            }
            return $type;
        }

        $type->aliastype = $code;
        $type->description = $description;
        $type->plugin = $plugin;
        $type->save();
        return $type;
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
