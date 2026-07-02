<?php
/**
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 */

namespace FacturaScripts\Plugins\Alias\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Alexis Serafín <alexis@okodex.com>
 */
class EditAlias extends EditController
{
    public function getModelClassName(): string
    {
        return 'Alias';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'alias';
        $data['icon'] = 'fa-solid fa-tags';
        return $data;
    }
}
