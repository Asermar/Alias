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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Alias;
use FacturaScripts\Dinamic\Model\AliasType;
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
 * - Los tipos de alias (`AliasType`) son un catálogo que sólo llena `AliasType::ensure()`: a mano no
 *   se crean, no se cambian ni se borran, y la interfaz sólo los lista.
 * - Alta/baja de alias, **un solo favorito** por entidad (`aliastype` + `cod`) y alias **único** por
 *   tipo (restricción `UNIQUE`).
 * - La longitud del alias se mide **después** de escapar el HTML.
 */
final class AliasTest extends TestCase
{
    use LogErrorsTrait;

    const TYPE = 'unittest';

    const TEST_TYPES = ['unittest', 'unittest2', 'unittest3', 'unittest4'];

    public static function tearDownAfterClass(): void
    {
        // limpiamos por SQL los tipos de pruebas (el modelo no deja borrarlos) y antes
        // sus alias, que la FK RESTRICT exige
        foreach (self::TEST_TYPES as $code) {
            Alias::table()->whereEq('aliastype', $code)->delete();
            AliasType::table()->whereEq('aliastype', $code)->delete();
        }
    }

    /**
     * @description Un alias de **100 caracteres** se guarda y se relee **sin recortar**.
     */
    public function testAliasOf100CharsIsNotTruncated(): void
    {
        $largo = str_repeat('A', 100);

        $alias = new Alias();
        $alias->aliastype = self::TYPE;
        $alias->cod = 'LARGO';
        $alias->alias = $largo;
        $this->assertTrue($alias->save(), 'no se pudo guardar un alias de 100 caracteres');

        // releemos de la base de datos: es ahí donde se vería el recorte
        $reload = new Alias();
        $this->assertTrue($reload->load($alias->id));
        $this->assertSame(100, mb_strlen($reload->alias), 'el alias se ha recortado al guardarlo');
        $this->assertSame($largo, $reload->alias);

        $this->assertTrue($alias->delete());
    }

    /**
     * @description Un alias que tras escapar el HTML pasa de **100 caracteres** se rechaza con aviso.
     */
    public function testAliasTooLongAfterEscapingIsRejected(): void
    {
        // 96 + 2 apóstrofos = 98 tecleados; cada ' pasa a &#39; y el resultado mide 106
        $alias = new Alias();
        $alias->aliastype = self::TYPE;
        $alias->cod = 'LARGO2';
        $alias->alias = str_repeat('A', 48) . "'" . str_repeat('B', 48) . "'";

        MiniLog::clear();
        $this->assertFalse($alias->save(), 'un alias de más de 100 caracteres no debe guardarse');
        $this->assertContains('alias-too-long', $this->logKeys(['warning']));
        MiniLog::clear();
    }

    /**
     * @description Un **tipo de alias** no se puede crear a mano: sólo con `AliasType::ensure()`.
     */
    public function testAliasTypeCannotBeCreatedByHand(): void
    {
        $type = new AliasType();
        $type->aliastype = self::TYPE . '2';
        $type->description = 'Otro tipo';

        MiniLog::clear();
        $this->assertFalse($type->save(), 'un tipo de alias no debe crearse a mano');
        $this->assertContains('alias-type-only-by-plugin', $this->logKeys(['warning']));
        $this->assertFalse($type->exists());
        MiniLog::clear();
    }

    /**
     * @description Un **tipo de alias** existente no se puede cambiar a mano.
     */
    public function testAliasTypeCannotBeChangedByHand(): void
    {
        $type = new AliasType();
        $this->assertTrue($type->loadWhere([Where::eq('aliastype', self::TYPE)]));
        $before = $type->description;
        $type->description = 'Cambiado a mano';

        MiniLog::clear();
        $this->assertFalse($type->save(), 'un tipo de alias no debe cambiarse a mano');
        $this->assertContains('alias-type-only-by-plugin', $this->logKeys(['warning']));
        MiniLog::clear();

        $reload = new AliasType();
        $reload->loadWhere([Where::eq('aliastype', self::TYPE)]);
        $this->assertSame($before, $reload->description);
    }

    /**
     * @description Un **tipo de alias** no se puede borrar, aunque no tenga alias.
     */
    public function testAliasTypeCannotBeDeleted(): void
    {
        $type = AliasType::ensure(self::TYPE . '3', 'Tipo sin alias', 'Alias');
        $this->assertNotNull($type);

        MiniLog::clear();
        $this->assertFalse($type->delete(), 'un tipo de alias no debe borrarse');
        $this->assertContains('alias-type-cannot-delete', $this->logKeys(['warning']));
        $this->assertTrue($type->exists());
        MiniLog::clear();
    }

    /**
     * @description `AliasType::ensure()` crea el tipo y es idempotente.
     */
    public function testEnsureCreatesType(): void
    {
        $type = AliasType::ensure(self::TYPE . '2', 'Otro tipo', 'Alias');
        $this->assertNotNull($type, 'ensure() debe crear el tipo');
        $this->assertTrue($type->exists());
        $this->assertSame('Alias', $type->plugin);

        $again = AliasType::ensure(self::TYPE . '2', 'Otro tipo', 'Alias');
        $this->assertEquals($type->id, $again->id);
    }

    /**
     * @description Si `ensure()` no puede guardar, devuelve **null** y deja un error en el log.
     */
    public function testEnsureReportsSaveFailure(): void
    {
        // una extensión de prueba que hace fallar cualquier guardado de tipos
        AliasType::addExtension(new class {
            public function saveBefore()
            {
                return function () {
                    return false;
                };
            }
        });

        MiniLog::clear();
        try {
            // un tipo que no existe, para que ensure() tenga que guardarlo
            $type = AliasType::ensure(self::TYPE . '4', 'Tipo que no se guarda', 'Alias');
        } finally {
            AliasType::removeExtension('saveBefore');
        }

        $this->assertNull($type, 'ensure() debe distinguir el fallo de save()');
        $this->assertContains('alias-type-ensure-error', $this->logKeys(['error']));
        MiniLog::clear();
    }

    /**
     * @description Si el tipo ya existe con **otro plugin**, `ensure()` avisa.
     */
    public function testEnsureWarnsIfTypeBelongsToOtherPlugin(): void
    {
        MiniLog::clear();
        $type = AliasType::ensure(self::TYPE, 'Unit test', 'OtroPlugin');
        $this->assertNotNull($type);
        $this->assertContains('alias-type-other-plugin', $this->logKeys(['warning']));
        MiniLog::clear();
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
     * @description La ficha `EditAliasType` **ya no existe**: los tipos no se editan.
     */
    public function testEditAliasTypeIsRetired(): void
    {
        $this->assertFalse(
            class_exists('FacturaScripts\\Plugins\\Alias\\Controller\\EditAliasType'),
            'la ficha de edición de tipos debe estar retirada'
        );
    }

    /**
     * @description En `ListAlias`, la pestaña de *tipos* es de **sólo lectura**; la de *alias* sí crea.
     */
    public function testListAliasTypeIsReadOnly(): void
    {
        $controller = new ListAlias('ListAlias');
        $method = new \ReflectionMethod($controller, 'createViews');
        $method->setAccessible(true);
        $method->invoke($controller);

        // la vista de tipos no crea, no borra, no marca y no enlaza a ninguna ficha
        $this->assertArrayHasKey('ListAliasType', $controller->views);
        foreach (['btnNew', 'btnDelete', 'checkBoxes', 'clickable'] as $setting) {
            $this->assertFalse(
                $controller->views['ListAliasType']->settings[$setting],
                'la vista de tipos de alias no debe tener ' . $setting
            );
        }

        // la vista de alias SÍ sigue permitiendo crear (no se ve afectada)
        $this->assertTrue($controller->views['ListAlias']->settings['btnNew']);
    }

    /**
     * @description Aunque el otro favorito no pueda guardarse, deja de ser favorito: nunca quedan dos.
     */
    public function testOnlyOneFavoriteEvenIfOtherCannotBeSaved(): void
    {
        $a1 = new Alias();
        $a1->aliastype = self::TYPE;
        $a1->cod = 'FAV2';
        $a1->alias = 'G1';
        $a1->favorite = true;
        $this->assertTrue($a1->save());

        // a partir de aquí, guardar G1 falla
        Alias::addExtension(new class {
            public function saveBefore()
            {
                return function () {
                    return $this->alias === 'G1' ? false : null;
                };
            }
        });

        try {
            $a2 = new Alias();
            $a2->aliastype = self::TYPE;
            $a2->cod = 'FAV2';
            $a2->alias = 'G2';
            $a2->favorite = true;
            $this->assertTrue($a2->save());
        } finally {
            Alias::removeExtension('saveBefore');
        }

        $reload = new Alias();
        $reload->load($a1->id);
        $this->assertFalse((bool)$reload->favorite, 'no pueden quedar dos favoritos de la misma entidad');

        $this->assertTrue($a1->delete());
        $this->assertTrue($a2->delete());
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
        // cada test parte sin alias de pruebas, aunque una pasada anterior muriera a medias
        foreach (self::TEST_TYPES as $code) {
            Alias::table()->whereEq('aliastype', $code)->delete();
        }

        // tipo de alias usado por las pruebas (FK alias.aliastype -> aliastypes)
        AliasType::ensure(self::TYPE, 'Unit test', 'Alias');
    }

    /** claves sin traducir de lo que hay en el log, de los niveles dados */
    private function logKeys(array $levels): array
    {
        return array_column(MiniLog::read('', $levels), 'original');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
