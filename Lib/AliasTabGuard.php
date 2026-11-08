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

namespace FacturaScripts\Plugins\Alias\Lib;

use FacturaScripts\Core\Request;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Alias;

/**
 * Guarda de la pestaña de alias que cada satélite añade a una ficha (EditListView sobre Alias).
 *
 * Esa pestaña filtra en loadData() los alias de la ficha abierta, pero el formulario manda id,
 * aliastype y cod, y las acciones del core cargan el registro por su código: sin esta guarda, con
 * permiso en la ficha se puede crear, editar o borrar cualquier alias del sistema.
 *
 * Se llama desde la extensión createViews() del satélite, que es lo último que corre antes de las
 * acciones: el gancho execPreviousAction de una extensión corre DESPUÉS de que el core haya
 * escrito (PanelController::privateCore). Si la petición sale del alcance de la ficha, avisa y
 * anula la acción vaciando `action` en el cuerpo y en la query, que es de donde la lee el core;
 * los permisos no se tocan, porque ese objeto es el que el core guarda en la sesión.
 *
 * @author Alexis Serafín <alexis@okodex.com>
 */
class AliasTabGuard
{
    const DELETE_ACTIONS = ['delete', 'delete-document'];

    const WRITE_ACTIONS = ['delete', 'delete-document', 'edit', 'insert'];

    /**
     * Deja seguir la petición si la pestaña $tabName sólo escribe alias de tipo $aliasType cuyo cod
     * es el de la ficha abierta. Si no, la anula y devuelve false.
     *
     * @param Request $request la petición del controlador; se modifica si se anula la acción
     * @param string $activeTab la pestaña activa del controlador
     * @param string $tabName la pestaña de alias que se protege
     * @param string $aliasType el tipo de alias de esa ficha
     * @param ModelClass $mainModel el modelo de la vista principal de la ficha (cargado o no)
     */
    public static function protect(Request $request, string $activeTab, string $tabName, string $aliasType, ModelClass $mainModel): bool
    {
        if ($activeTab !== $tabName) {
            return true;
        }

        $action = (string)$request->inputOrQuery('action', '');
        if (false === in_array($action, self::WRITE_ACTIONS, true)) {
            return true;
        }

        // la ficha abierta, con la misma expresión con la que la carga EditController::loadData()
        $code = $request->query('code', $request->input($mainModel->primaryColumn()));
        $class = get_class($mainModel);
        $record = new $class();
        if (empty($code) || false === $record->load($code)) {
            return self::reject($request);
        }
        $cod = (string)$record->id();

        if (in_array($action, self::DELETE_ACTIONS, true)) {
            // como BaseController::deleteAction(): si llegan casillas, se ignora code
            $codes = $request->request->getArray('codes');
            if (empty($codes)) {
                $codes = [$request->input('code')];
            }
            foreach ($codes as $id) {
                if (false === self::inScope($id, $aliasType, $cod)) {
                    return self::reject($request);
                }
            }
            return true;
        }

        // alta y edición: lo que se va a guardar tiene que ser de la ficha
        if ((string)$request->input('aliastype') !== $aliasType || (string)$request->input('cod') !== $cod) {
            return self::reject($request);
        }

        if ($action === 'edit') {
            // el alias que se edita también, y sin cambiarle el id por el de otro
            $id = $request->input('code');
            $postedId = (string)$request->input('id', '');
            if (false === self::inScope($id, $aliasType, $cod) || ($postedId !== '' && $postedId !== (string)$id)) {
                return self::reject($request);
            }
        }

        return true;
    }

    private static function inScope($id, string $aliasType, string $cod): bool
    {
        $alias = new Alias();
        return false === empty($id)
            && $alias->load($id)
            && $alias->aliastype === $aliasType
            && (string)$alias->cod === $cod;
    }

    private static function reject(Request $request): bool
    {
        Tools::log()->warning('alias-tab-out-of-scope');
        $request->request->set('action', '');
        $request->query->set('action', '');
        return false;
    }
}
