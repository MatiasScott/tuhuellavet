<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Core\Session;
use App\Core\Request;
use App\Controllers\Gestion\HaciendaController;
use App\Controllers\Gestion\InventarioController;
use App\Models\ClinicalHistory;
use App\Models\Consultation;
use App\Models\Inventory;
use App\Services\ConsultationService;
use App\Services\EnvironmentModules;
use App\Services\HaciendaService;
use App\Services\HospitalizationService;
use App\Services\InventoryService;
use App\Services\PatientService;
use RuntimeException;
use Tests\TestCase;

class HaciendaWorkflowTest extends TestCase
{
    private int $env;
    private int $clinic;
    private int $user;
    private int $species;
    private int $unit;
    private array $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $_SESSION ?? [];
        $this->db->beginTransaction();
        $this->env = (int) $this->db->query("SELECT id FROM entornos WHERE codigo='HACIENDA_AGUSBELLA'")->fetchColumn();
        $this->clinic = (int) $this->db->query("SELECT id FROM entornos WHERE codigo='TU_HUELLA_VET'")->fetchColumn();
        $this->species = (int) $this->db->query("SELECT id FROM especies WHERE codigo='BOVINO'")->fetchColumn();
        $this->unit = (int) $this->db->query("SELECT id FROM unidades_medida WHERE codigo='KG'")->fetchColumn();
        $stmt = $this->db->prepare('INSERT INTO usuarios(nombres,apellidos,email) VALUES (?,?,?)');
        $stmt->execute(['QA Hacienda', 'Prueba', bin2hex(random_bytes(8)) . '@example.test']);
        $this->user = (int) $this->db->lastInsertId();
        Session::put('active_environment_id', $this->env);
        Session::put('active_environment', ['id' => $this->env, 'codigo' => 'HACIENDA_AGUSBELLA']);
        Session::put('auth_user', ['id' => $this->user, 'active_role' => 'SUPER_ADMINISTRADOR', 'roles' => ['SUPER_ADMINISTRADOR'], 'is_super_admin' => true]);
        (new InventoryService())->ensureDefault($this->env);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $_SESSION = $this->session;
        parent::tearDown();
    }

    private function animal(string $name = 'Animal', string $sex = 'MACHO', ?int $env = null): int
    {
        $stmt = $this->db->prepare('SELECT id FROM sexos_animales WHERE codigo=?');
        $stmt->execute([$sex]);
        return (new PatientService())->create([
            'nombre' => $name, 'especie_id' => $this->species, 'sexo_id' => $stmt->fetchColumn(),
            'metodo_identificacion' => 'Arete', 'origen' => 'Nacimiento en hacienda',
        ], [], $env ?? $this->env, $this->user);
    }

    private function lot(): int
    {
        return (new HaciendaService())->create('lotes', [
            'codigo' => bin2hex(random_bytes(6)), 'nombre' => 'Lote QA', 'cantidad' => 12,
            'especie_id' => $this->species, 'metodo_identificacion' => 'Corral', 'origen' => 'Crianza propia',
        ], $this->env, $this->user);
    }

    private function product(string $stock = '10', bool $lot = false): int
    {
        return (new InventoryService())->createProduct([
            'codigo' => 'HQA-' . bin2hex(random_bytes(6)), 'nombre' => 'Alimento QA',
            'unidad_base_id' => $this->unit, 'cantidad_inicial' => $stock,
            'stock_maximo_sin_limite' => 1,
            'controla_lote' => $lot ? 1 : 0, 'controla_vencimiento' => $lot ? 1 : 0,
            'numero_lote' => $lot ? 'QA-LOTE' : '', 'fecha_vencimiento' => $lot ? date('Y-m-d', strtotime('+1 year')) : '',
        ], $this->env, $this->user);
    }

    private function feeding(array $overrides = []): int
    {
        $product = $this->product();
        return (new HaciendaService())->create('alimentacion', $overrides + [
            'lote_id' => $this->lot(), 'tipo_alimento' => 'Balanceado', 'cantidad_consumida' => '2.25',
            'unidad_id' => $this->unit, 'fecha_inicio' => date('Y-m-d'),
            'inventario_id' => (new InventoryService())->ensureDefault($this->env), 'producto_id' => $product,
        ], $this->env, $this->user);
    }

    private function rejects(callable $action, string $contains): void
    {
        try {
            $action();
            self::fail('Se esperaba rechazo: ' . $contains);
        } catch (RuntimeException $e) {
            self::assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function testModulesAreBlockedEvenForSuperAdminOnlyInFarm(): void
    {
        foreach (['peluqueria.ver', 'formulas.calcular', 'ventas.crear', 'facturacion.facturar'] as $permission) {
            self::assertFalse(can($permission));
            self::assertTrue(EnvironmentModules::allows($permission, ['codigo' => 'TU_HUELLA_VET']));
        }
        self::assertTrue(can('produccion.ver'));
        self::assertFalse(EnvironmentModules::allows('produccion.ver', ['codigo' => 'TU_HUELLA_VET']));
        $this->rejects(fn () => (new HaciendaService())->rows('lotes', $this->clinic), 'solo esta disponible');
    }

    public function testIdentificationRequiredInFarmAndLegacyClinicStillWorks(): void
    {
        $this->rejects(fn () => (new PatientService())->create(['especie_id' => $this->species], [], $this->env, $this->user), 'obligatorios');
        $id = (new PatientService())->create(['especie_id' => $this->species], [], $this->clinic, $this->user);
        self::assertGreaterThan(0, $id);
        self::assertGreaterThan(0, $this->animal());
    }

    public function testProductionReportsSeparateUnitsAndDestinationsAndFilterDates(): void
    {
        $service = new HaciendaService();
        $animal = $this->animal();
        $lot = $this->lot();
        $base = ['tipo_producto' => 'Leche', 'cantidad' => '1.25', 'unidad_id' => $this->unit, 'fecha_registro' => date('Y-m-d\TH:i')];
        $service->create('produccion', ['paciente_id' => $animal] + $base, $this->env, $this->user);
        $service->create('produccion', ['paciente_id' => $animal] + $base, $this->env, $this->user);
        $service->create('produccion', ['lote_id' => $lot] + $base, $this->env, $this->user);
        $rows = $service->rows('produccion', $this->env, ['paciente_id' => $animal, 'desde' => date('Y-m-d'), 'hasta' => date('Y-m-d')]);
        self::assertCount(2, $rows);
        self::assertSame('2.50', $service->metrics('produccion', $rows)[0]['cantidad']);
        self::assertCount(2, $service->metrics('produccion', $service->rows('produccion', $this->env)));
        $countUnit = (int) $this->db->query("SELECT id FROM unidades_medida WHERE codigo='UND'")->fetchColumn();
        self::assertGreaterThan(0, $countUnit);
        $service->create('produccion', ['paciente_id' => $animal, 'unidad_id' => $countUnit] + $base, $this->env, $this->user);
        self::assertCount(3, $service->metrics('produccion', $service->rows('produccion', $this->env)));
        self::assertCount(0, $service->rows('produccion', $this->env, ['desde' => '2099-01-01']));
        $this->rejects(fn () => $service->create('produccion', ['paciente_id' => $animal, 'lote_id' => $lot] + $base, $this->env, $this->user), 'no ambos');
        $this->rejects(fn () => $service->create('produccion', ['paciente_id' => $this->animal('Otro', 'MACHO', $this->clinic)] + $base, $this->env, $this->user), 'este entorno');
        foreach (['0', '-1', 'NaN', '0.001', '100000000'] as $invalid) {
            $this->rejects(fn () => $service->create('produccion', ['paciente_id' => $animal, 'cantidad' => $invalid] + $base, $this->env, $this->user), 'cantidad debe');
        }
    }

    public function testPlanDoesNotConsumeAndConfirmationIsAtomicAndOnceOnly(): void
    {
        $service = new HaciendaService();
        $id = $this->feeding();
        $row = $this->db->query("SELECT * FROM alimentacion WHERE id=$id")->fetch();
        $stock = fn (): string => (string) $this->db->query('SELECT SUM(t.factor*m.cantidad) FROM movimientos_inventario m JOIN tipos_movimiento_inventario t ON t.id=m.tipo_movimiento_id WHERE producto_id=' . (int) $row['producto_id'])->fetchColumn();
        self::assertSame('10.0000', $stock());
        $movement = $service->confirmFeeding($id, $this->env, $this->user);
        self::assertGreaterThan(0, $movement);
        self::assertSame('7.7500', $stock());
        $this->rejects(fn () => $service->confirmFeeding($id, $this->env, $this->user), 'ya confirmado');
        self::assertSame('7.7500', $stock());
        $report = $service->rows('alimentacion', $this->env);
        self::assertSame('CONSUMIDA', $report[0]['estado']);
        self::assertSame($movement, (int) $report[0]['movimiento_id']);
        self::assertSame('Alimento QA', $report[0]['producto']);
        self::assertCount(1, $service->rows('alimentacion', $this->env, ['desde' => date('Y-m-d'), 'hasta' => date('Y-m-d')]));
    }

    public function testInsufficientStockRollsBackConsumptionAndLeavesPlan(): void
    {
        $id = $this->feeding(['cantidad_consumida' => '11.00']);
        $before = (int) $this->db->query('SELECT COUNT(*) FROM movimientos_inventario')->fetchColumn();
        $this->rejects(fn () => (new HaciendaService())->confirmFeeding($id, $this->env, $this->user), 'Stock insuficiente');
        self::assertSame($before, (int) $this->db->query('SELECT COUNT(*) FROM movimientos_inventario')->fetchColumn());
        self::assertSame('PLANIFICADA', $this->db->query("SELECT estado FROM alimentacion WHERE id=$id")->fetchColumn());
    }

    public function testFeedingUnitsDatesAndProductScopeAreValidated(): void
    {
        $otherUnit = (int) $this->db->query("SELECT id FROM unidades_medida WHERE codigo='G'")->fetchColumn();
        $this->rejects(fn () => $this->feeding(['unidad_id' => $otherUnit]), 'unidad base');
        $this->rejects(fn () => $this->feeding(['fecha_fin' => '2000-01-01']), 'fecha final');
        $this->rejects(fn () => $this->feeding(['fecha_inicio' => '2026-02-30']), 'Fecha no valida');
        $id = $this->feeding(['fecha_inicio' => '2099-01-01']);
        $this->rejects(fn () => (new HaciendaService())->confirmFeeding($id, $this->env, $this->user), 'consumo futuro');
        $product = $this->product();
        $this->rejects(fn () => (new InventoryService())->assertProductScope($product, $this->clinic), 'catalogo');
        self::assertContains($product, array_map('intval', array_column((new Inventory())->products(), 'id')));
    }

    public function testInventoryLotIsTracedAndExpiredLotCannotBeConsumed(): void
    {
        $product = $this->product('10', true);
        $lot = (int) $this->db->query("SELECT id FROM lotes_producto WHERE producto_id=$product")->fetchColumn();
        $id = $this->feeding(['producto_id' => $product, 'lote_producto_id' => $lot]);
        (new HaciendaService())->confirmFeeding($id, $this->env, $this->user);
        $stmt = $this->db->prepare('UPDATE lotes_producto SET fecha_vencimiento=? WHERE id=?');
        $stmt->execute(['2000-01-01', $lot]);
        $id2 = $this->feeding(['producto_id' => $product, 'lote_producto_id' => $lot]);
        $this->rejects(fn () => (new HaciendaService())->confirmFeeding($id2, $this->env, $this->user), 'vencido');
        $row = $this->db->query("SELECT m.lote_id FROM movimientos_inventario m JOIN alimentacion a ON a.movimiento_id=m.id WHERE a.id=$id")->fetch();
        self::assertSame($lot, (int) $row['lote_id']);
    }

    public function testGenealogyPreventsCyclesSelfParentAndKnownConsanguinity(): void
    {
        $service = new HaciendaService();
        $father = $this->animal('Padre');
        $mother = $this->animal('Madre', 'HEMBRA');
        $son = $this->animal('Hijo');
        $daughter = $this->animal('Hija', 'HEMBRA');
        $grandchild = $this->animal('Nieto');
        foreach ([$son, $daughter] as $child) {
            $service->create('genealogia', ['paciente_id' => $child, 'padre_id' => $father, 'madre_id' => $mother], $this->env, $this->user);
        }
        $this->rejects(fn () => $service->create('genealogia', ['paciente_id' => $father, 'padre_id' => $son], $this->env, $this->user), 'ciclo');
        $this->rejects(fn () => $service->create('genealogia', ['paciente_id' => $grandchild, 'padre_id' => $son, 'madre_id' => $daughter], $this->env, $this->user), 'consanguinidad');
        $this->rejects(fn () => $service->create('genealogia', ['paciente_id' => $father, 'padre_id' => $father], $this->env, $this->user), 'distintos');
        $this->rejects(fn () => $service->create('genealogia', ['paciente_id' => $son, 'padre_id' => $mother], $this->env, $this->user), 'sexo');
    }

    public function testClinicalConstantsPersistPerAttentionAndUpdatesAndLotHistory(): void
    {
        $patient = $this->animal();
        $service = new ConsultationService();
        $event = $service->create([
            'animal_id' => $patient, 'temperatura_c' => '38.25', 'mucosas' => 'Rosadas',
            'ritmo_cardiaco' => 'Regular', 'saturacion_oxigeno' => '97%', 'llenado_capilar' => 'Normal',
        ], $this->env, $this->user);
        $service->updateClinicalExam($event, ['mucosas' => 'Palidas', 'sonido' => 'Normal'], $this->env, $this->user);
        $exam = (new Consultation())->find($event, $this->env);
        self::assertSame('38.25', $exam['temperatura_c']);
        self::assertSame('Palidas', $exam['mucosas']);
        self::assertSame('97%', $exam['saturacion_oxigeno']);
        self::assertSame('Palidas', (new ClinicalHistory())->timeline($patient, $this->env)[0]['mucosas']);
        $lot = $this->lot();
        (new HaciendaService())->lotExam($lot, [
            'titulo' => 'Revision del lote', 'fecha_evento' => date('Y-m-d\TH:i'),
            'temperatura_c' => '39.10', 'sonido' => 'Sin alteraciones',
        ], $this->env, $this->user);
        $history = (new HaciendaService())->lotHistory($lot, $this->env);
        self::assertSame('39.10', $history[0]['temperatura_c']);
        self::assertSame('Sin alteraciones', $history[0]['sonido']);
        self::assertSame('38.25', (new Consultation())->find($event, $this->env)['temperatura_c']);
    }

    public function testPlansCanBeLinkedAndEditedBeforeButNotAfterConsumption(): void
    {
        $service = new HaciendaService();
        $id = $service->create('alimentacion', [
            'lote_id' => $this->lot(), 'tipo_alimento' => 'Balanceado', 'cantidad_consumida' => '2',
            'unidad_id' => $this->unit, 'fecha_inicio' => date('Y-m-d'),
        ], $this->env, $this->user);
        $product = $this->product();
        $service->updatePlan($id, [
            'producto_id' => $product,
            'inventario_id' => (new InventoryService())->ensureDefault($this->env),
            'cantidad_consumida' => '3.50',
        ], $this->env, $this->user);
        $service->confirmFeeding($id, $this->env, $this->user);
        $this->rejects(fn () => $service->updatePlan($id, ['cantidad_consumida' => '1'], $this->env, $this->user), 'no puede modificarse');
        self::assertSame('3.50', $this->db->query("SELECT cantidad_consumida FROM alimentacion WHERE id=$id")->fetchColumn());
    }

    public function testLotEditsAndNewScreensRenderWithoutLegacySalesOrGrooming(): void
    {
        $service = new HaciendaService();
        $lot = $this->lot();
        $service->updateLot($lot, [
            'nombre' => 'Lote actualizado', 'cantidad' => 15,
            'metodo_identificacion' => 'Corral 2', 'origen' => 'Propio',
        ], $this->env, $this->user);
        self::assertSame(15, (int) $service->lot($lot, $this->env)['cantidad']);
        $this->feeding();
        $request = new Request('GET', '/', [], [], [], []);
        foreach (HaciendaService::MODULES as $module) {
            ob_start();
            try {
                (new HaciendaController())->index($request, $module);
                $html = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertStringContainsString('Hacienda Agusbella', $html);
            self::assertStringNotContainsString('href="' . url('/peluquerias') . '"', $html);
            self::assertStringNotContainsString('href="' . url('/facturacion') . '"', $html);
            self::assertStringNotContainsString('href="' . url('/formulas') . '"', $html);
        }
        ob_start();
        try {
            (new InventarioController())->index($request);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('Catálogo exclusivo de Hacienda', $html);
        self::assertStringNotContainsString('name="precio_venta"', $html);
        self::assertStringNotContainsString('name="impuesto_tarifa_id"', $html);
    }

    public function testAncestorUpdatesCannotIntroduceConsanguinityInDescendants(): void
    {
        $service = new HaciendaService();
        $grandfather = $this->animal('Abuelo');
        $father = $this->animal('Padre');
        $mother = $this->animal('Madre', 'HEMBRA');
        $child = $this->animal('Hijo');
        $service->create('genealogia', ['paciente_id' => $father, 'padre_id' => $grandfather], $this->env, $this->user);
        $service->create('genealogia', ['paciente_id' => $child, 'padre_id' => $father, 'madre_id' => $mother], $this->env, $this->user);
        $this->rejects(fn () => $service->create('genealogia', ['paciente_id' => $mother, 'padre_id' => $grandfather], $this->env, $this->user), 'consanguinidad');
        $female = (int) $this->db->query("SELECT id FROM sexos_animales WHERE codigo='HEMBRA'")->fetchColumn();
        $this->rejects(fn () => (new PatientService())->update($father, ['sexo_id' => $female], $this->env, $this->user), 'parentesco genealogico');
    }

    public function testClinicInventoryStillWorksAndCatalogCodesAreIndependent(): void
    {
        $service = new InventoryService();
        $farmProduct = $this->product();
        $code = $this->db->query("SELECT codigo FROM productos WHERE id=$farmProduct")->fetchColumn();
        $service->updateProduct($farmProduct, [
            'codigo' => $code, 'nombre' => 'Alimento actualizado', 'unidad_base_id' => $this->unit,
            'stock_maximo_sin_limite' => 1,
        ], $this->env, $this->user);
        self::assertSame('Alimento actualizado', $this->db->query("SELECT nombre FROM productos WHERE id=$farmProduct")->fetchColumn());
        $service->ensureDefault($this->clinic);
        $clinicProduct = $service->createProduct([
            'codigo' => $code, 'nombre' => 'Producto clinico', 'unidad_base_id' => $this->unit,
            'cantidad_inicial' => '10', 'stock_maximo_sin_limite' => 1,
        ], $this->clinic, $this->user);
        $exit = (int) $this->db->query("SELECT id FROM tipos_movimiento_inventario WHERE codigo='SALIDA'")->fetchColumn();
        $movement = $service->movement(['producto_id' => $clinicProduct, 'tipo_movimiento_id' => $exit, 'cantidad' => 1], $this->clinic, $this->user);
        self::assertGreaterThan(0, $movement);
        self::assertNotSame($farmProduct, $clinicProduct);
        $this->rejects(fn () => $service->movement(['producto_id' => $clinicProduct, 'tipo_movimiento_id' => $exit, 'cantidad' => 1], $this->env, $this->user), 'catalogo');
        Session::put('active_environment_id', $this->clinic);
        Session::put('active_environment', ['codigo' => 'TU_HUELLA_VET']);
        self::assertTrue(can('ventas.ver'));
        self::assertTrue(can('peluqueria.ver'));
        self::assertNull($this->db->query("SELECT entorno_id FROM productos WHERE id=$clinicProduct")->fetchColumn());
    }

    public function testHospitalizationPreservesSerialPhysiologicalConstants(): void
    {
        $service = new HospitalizationService();
        $event = $service->create([
            'animal_id' => $this->animal(), 'mucosas' => 'Rosadas',
            'temperatura_c' => '38.50', 'motivo_ingreso' => 'Observacion',
        ], $this->env, $this->user);
        $service->addSigns($event, ['mucosas' => 'Palidas', 'sonido' => 'Normal', 'saturacion_oxigeno' => '96%'], $this->env, $this->user);
        $stmt = $this->db->prepare('SELECT * FROM hospitalizacion_signos WHERE hospitalizacion_evento_id=? ORDER BY id');
        $stmt->execute([$event]);
        $rows = $stmt->fetchAll();
        self::assertCount(2, $rows);
        self::assertSame('Rosadas', $rows[0]['mucosas']);
        self::assertSame('38.50', $rows[0]['temperatura_c']);
        self::assertSame('Palidas', $rows[1]['mucosas']);
        self::assertSame('96%', $rows[1]['saturacion_oxigeno']);
        $this->rejects(fn () => $service->addFluid($event, ['formula_ejecucion_id' => 1], $this->env, $this->user), 'desactivadas');
    }
}
