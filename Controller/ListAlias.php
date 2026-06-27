<?php
/**
 * Copyright (C) 2026 Alexis Serafin <alexis@okodex.com>
 */

namespace FacturaScripts\Plugins\Alias\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

/**
 * Listado de alias (con filtro por tipo) y de tipos de alias.
 *
 * @author Alexis Serafin <alexis@okodex.com>
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
