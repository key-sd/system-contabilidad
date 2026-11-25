<?php
$base_path = '';
$page_title = 'Hogaris - Ventas a contribuyentes';
$active_menu = 'ventas_contribuyentes';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

[$desde, $hasta, $etiqueta, $mes] = periodo_mes();

$stmt = $pdo->prepare("SELECT * FROM v_libro_ventas_contribuyentes WHERE fecha BETWEEN :d AND :h ORDER BY fecha, id");
$stmt->execute(['d' => $desde, 'h' => $hasta]);
$filas = $stmt->fetchAll();

$campos = ['ventas_exentas', 'ventas_gravadas', 'iva_debito', 'terceros_exentas', 'terceros_gravadas',
           'terceros_debito', 'iva_percibido', 'iva_retenido', 'total'];
$tot = array_fill_keys($campos, 0);
foreach ($filas as $f) foreach ($campos as $c) $tot[$c] += $f[$c];

$empresa = $pdo->query("SELECT razon_social, nrc FROM empresa WHERE id = 1")->fetch();

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Ventas a contribuyentes', e($empresa['razon_social'] ?? '') . ' · NRC ' . e($empresa['nrc'] ?? '') . ' · ' . $etiqueta, $mes); ?>

        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
        <?php if (!$filas): sin_datos(); else: ?>
            <table class="<?php echo CLS_TABLE; ?>">
                <thead>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-left">N°</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Fecha</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Documento</th>
                        <th class="<?php echo CLS_TH; ?> text-left">NRC</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Contribuyente</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Exentas</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Gravadas</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Débito fiscal</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Terceros exentas</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Terceros gravadas</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Terceros débito</th>
                        <th class="<?php echo CLS_TH; ?> text-right">IVA percibido</th>
                        <th class="<?php echo CLS_TH; ?> text-right">IVA retenido</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr class="hover:bg-slate-50/60">
                        <td class="<?php echo CLS_TD; ?> text-slate-400"><?php echo (int)$f['n']; ?></td>
                        <td class="<?php echo CLS_TD; ?>"><?php echo fecha_es($f['fecha']); ?></td>
                        <td class="<?php echo CLS_TD; ?> font-mono"><?php echo e($f['documento']); ?></td>
                        <td class="<?php echo CLS_TD; ?> font-mono text-slate-500"><?php echo e($f['nrc']); ?></td>
                        <td class="<?php echo CLS_TD; ?> text-slate-800"><?php echo e($f['contribuyente']); ?></td>
                        <?php foreach ($campos as $c): ?>
                            <td class="<?php echo CLS_NUM; ?> <?php echo $c === 'total' ? 'font-semibold' : ''; ?>"><?php echo num($f[$c]); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-900 text-white font-semibold">
                        <td colspan="5" class="px-4 py-3 text-right">TOTALES</td>
                        <?php foreach ($campos as $c): ?>
                            <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot[$c]); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>