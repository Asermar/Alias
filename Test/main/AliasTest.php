<?php
/**
 * Copyright (C) 2026 Oko Digital Experts, S.L.L. (Okodex)
 */

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Alias;
use FacturaScripts\Dinamic\Model\AliasType;
use FacturaScripts\Plugins\Alias\Controller\EditAliasType;
use FacturaScripts\Plugins\Alias\Controller\ListAlias;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Alexis Serafín <alexis@okodex.com>
 *
 * @description
 * ## Alias polimórficos
 *
 * Valida el modelo base `Alias` y sus reglas:
 *
 * - Alta/baja de tipos de alias (`AliasType`) y de alias.
 * - **Un solo favorito** por entidad (`aliastype` + `cod`).
 * - Alias **único** por tipo (restricción `UNIQUE`).
 * - Las vistas de *tipos* de alias no permiten crear registros nuevos.
 */
final class AliasTest extends TestCase
{
    use LogErrorsTrait;

    const TYPE = 'unittest';

    public static function tearDownAfterClass(): void
    {
        // limpiamos el tipo de alias de pruebas
        foreach (AliasType::all([Where::eq('aliastype', self::TYPE)]) as $type) {
            $type->delete();
        }
    }

    /**
     * @description Alta, existencia y borrado de un **tipo de alias** (`AliasType`).
     */
    public function testAliasType(): void
    {
        $type = new AliasType();
        $type->aliastype = 'unittest2';
        $type->description = 'Otro tipo';
        $this->assertTrue($type->save(), 'no se pudo guardar el tipo de alias');
        $this->assertTrue($type->exists());
        $this->assertTrue($type->delete());
    }

    /**
     * @description Crea y borra un alias; `favorite` debe ser **false** por defecto.
     */
    public function testCreateAndDeleteAlias(): void
    {
        $alias = new Alias();
        $alias->aliastype = self::TYPE;
        $alias->cod = 'COD1';
        $alias->alias = 'ALIAS1';
        $this->assertTrue($alias->save(), 'no se pudo guardar el alias');
        $this->assertTrue($alias->exists());
        $this->assertFalse((bool)$alias->favorite, 'favorite debe ser false por defecto');
        $this->assertTrue($alias->delete());
    }

    /**
     * @description La vista `EditAliasType` **no** permite crear nuevos (`btnNew` desactivado).
     */
    public function testEditAliasTypeHasNoNewButton(): void
    {
        $controller = new EditAliasType('EditAliasType');
        $method = new \ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        $this->assertArrayHasKey('EditAliasType', $controller->views);
        $this->assertFalse(
            $controller->views['EditAliasType']->settings['btnNew'],
            'la vista de edición de tipo no debe permitir crear nuevos'
        );
    }

    /**
     * @description En `ListAlias`, la pestaña de *tipos* no permite crear; la de *alias* sí.
     */
    public function testListAliasTypeHasNoNewButton(): void
    {
        $controller = new ListAlias('ListAlias');
        $method = new \ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        // la vista de tipos NO permite crear
        $this->assertArrayHasKey('ListAliasType', $controller->views);
        $this->assertFalse(
            $controller->views['ListAliasType']->settings['btnNew'],
            'la vista de tipos de alias no debe permitir crear nuevos'
        );

        // la vista de alias SÍ sigue permitiendo crear (no se ve afectada)
        $this->assertTrue($controller->views['ListAlias']->settings['btnNew']);
    }

    /**
     * @description Al marcar un segundo favorito de la misma entidad, el anterior se **desmarca**.
     */
    public function testOnlyOneFavoritePerEntity(): void
    {
        $a1 = new Alias();
        $a1->aliastype = self::TYPE;
        $a1->cod = 'FAV';
        $a1->alias = 'F1';
        $a1->favorite = true;
        $this->assertTrue($a1->save());

        // segundo favorito de la misma entidad (aliastype + cod)
        $a2 = new Alias();
        $a2->aliastype = self::TYPE;
        $a2->cod = 'FAV';
        $a2->alias = 'F2';
        $a2->favorite = true;
        $this->assertTrue($a2->save());

        // el primero debe haber quedado desmarcado
        $reload = new Alias();
        $reload->load($a1->id);
        $this->assertFalse((bool)$reload->favorite, 'solo debe haber un favorito por entidad');
        $this->assertTrue((bool)$a2->favorite);

        $this->assertTrue($a1->delete());
        $this->assertTrue($a2->delete());
    }

    /**
     * @description Dos alias con el mismo `(aliastype, alias)` se rechazan por la restricción `UNIQUE`.
     */
    public function testUniqueAliasPerType(): void
    {
        $a1 = new Alias();
        $a1->aliastype = self::TYPE;
        $a1->cod = 'C1';
        $a1->alias = 'DUP';
        $this->assertTrue($a1->save());

        // mismo (aliastype, alias) -> debe rechazarse por el UNIQUE
        $a2 = new Alias();
        $a2->aliastype = self::TYPE;
        $a2->cod = 'C2';
        $a2->alias = 'DUP';
        $this->assertFalse($a2->save(), 'el alias duplicado no debería guardarse');

        // la violación de UNIQUE registra un error en el log; lo limpiamos
        MiniLog::clear();

        $this->assertTrue($a1->delete());
    }

    protected function setUp(): void
    {
        // tipo de alias usado por las pruebas (FK alias.aliastype -> aliastypes)
        $type = new AliasType();
        if (false === $type->loadWhere([Where::eq('aliastype', self::TYPE)])) {
            $type->aliastype = self::TYPE;
            $type->description = 'Unit test';
            $type->save();
        }
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
