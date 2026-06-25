<?php
/**
 * Copyright (C) 2026 Alexis Serafin <alexis@okodex.com>
 */

namespace FacturaScripts\Plugins\Alias\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Alexis Serafin <alexis@okodex.com>
 */
class EditAliasType extends EditController
{
    public function getModelClassName(): string
    {
        return 'AliasType';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'alias-type';
        $data['icon'] = 'fa-solid fa-tag';
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();

        // los tipos de alias solo los crea el plugin que ofrece operativa para
        // ellos; desde la administración no se permite crearlos a mano.
        $this->setSettings('EditAliasType', 'btnNew', false);
    }
}
