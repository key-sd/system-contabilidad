<?php
$base_path = '';
$page_title = 'Hogaris - Existencias';
$active_menu = 'inventario';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

$filas = $pdo->query("SELECT * FROM v_inventario ORDER BY categoria, id")->fetchAll();

$campos = ['cant_comprada', 'cant_vendida', 'cant_final', 'costo_final_total', 'costo_iva_final', 'valor_venta_final'];
$tot = array_fill_keys($campos, 0);
$por_cat = [];
foreach ($filas as $f) {
    foreach ($campos as $c) $tot[$c] += $f[$c];
    $por_cat[$f['categoria']][] = $f;
}

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Existencias', 'Inventario perpetuo: las cantidades y el costo se actualizan con cada compra y venta'); ?>

        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
        <?php if (!$filas): sin_datos('No hay productos registrados.'); else: ?>
            <table class="<?php echo CLS_TABLE; ?>">
                <thead>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-left">Marca</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Modelo / descripción</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Comprada</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Vendida</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Existencia</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo unit.</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo total</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo + IVA unit.</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo + IVA total</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Precio venta (c/IVA)</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Valor venta</th>
                    </tr>
                </thead>
                <?php foreach ($por_cat as $cat => $items): ?>
                <tbody>
                    <tr><td colspan="11" class="px-4 py-2 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200"><?php echo e($cat); ?></td></tr>
                    <?php foreach ($items as $f): ?>
                    <tr class="hover:bg-slate-50/60">
                        <td class="<?php echo CLS_TD; ?> font-medium text-slate-800"><?php echo e($f['marca']); ?></td>
                        <td class="<?php echo CLS_TD; ?> text-slate-600"><?php echo e($f['modelo']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo (int)$f['cant_comprada']; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo (int)$f['cant_vendida']; ?></td>
                        <td class="<?php echo CLS_NUM; ?> font-semibold <?php echo $f['cant_final'] == 0 ? 'text-rose-600' : ''; ?>"><?php echo (int)$f['cant_final']; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['costo_unit']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['costo_final_total']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['costo_iva_unit']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['costo_iva_final']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['precio_venta_iva']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($f['valor_venta_final']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php endforeach; ?>
                <tfoot>
                    <tr class="bg-slate-900 text-white font-semibold">
                        <td colspan="2" class="px-4 py-3 text-right">TOTAL GLOBAL</td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo (int)$tot['cant_comprada']; ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo (int)$tot['cant_vendida']; ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo (int)$tot['cant_final']; ?></td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot['costo_final_total']); ?></td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot['costo_iva_final']); ?></td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot['valor_venta_final']); ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>