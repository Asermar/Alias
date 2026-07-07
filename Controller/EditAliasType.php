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

namespace FacturaScripts\Plugins\Alias\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Alexis Serafín <alexis@okodex.com>
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
