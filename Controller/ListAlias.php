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

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

/**
 * Listado de alias (con filtro por tipo) y de tipos de alias.
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class ListAlias extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'aliases';
        $data['icon'] = 'fa-solid fa-tags';
        return $data;
    }

    protected function createViews(): void
    {
        $this->createViewsAlias();
        $this->createViewsAliasType();
    }

    protected function createViewsAlias(string $viewName = 'ListAlias'): void
    {
        $this->addView($viewName, 'Alias', 'aliases', 'fa-solid fa-tags')
            ->addOrderBy(['aliastype'], 'alias-type')
            ->addOrderBy(['alias'], 'alias')
            ->addOrderBy(['cod'], 'code')
            ->addOrderBy(['favorite'], 'favorite')
            ->addOrderBy(['id'], 'id', 2)
            ->addSearchFields(['alias', 'cod']);

        // filtro por tipo de alias (con la etiqueta traducida)
        $types = $this->codeModel->all('aliastypes', 'aliastype', 'aliastype');
        foreach ($types as $type) {
            $type->description = Tools::trans($type->code);
        }
        $this->listView($viewName)
            ->addFilterSelect('aliastype', 'alias-type', 'aliastype', $types)
            ->addFilterCheckbox('favorite', 'favorite', 'favorite');
    }

    protected function createViewsAliasType(string $viewName = 'ListAliasType'): void
    {
        $this->addView($viewName, 'AliasType', 'alias-types', 'fa-solid fa-tag')
            ->addOrderBy(['aliastype'], 'alias-type', 1)
            ->addOrderBy(['description'], 'description')
            ->addOrderBy(['plugin'], 'plugin')
            ->addSearchFields(['aliastype', 'description', 'plugin'])
            ->setSettings('btnNew', false);

        // filtro por plugin responsable
        $plugins = $this->codeModel->all('aliastypes', 'plugin', 'plugin');
        $this->listView($viewName)
            ->addFilterSelect('plugin', 'plugin', 'plugin', $plugins);
    }
}
