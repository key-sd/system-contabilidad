<?php
$base_path = '';
$page_title = 'Hogaris - Libro mayor';
$active_menu = 'libro_mayor';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

[$desde, $hasta, $etiqueta, $mes] = periodo_mes();

$stmt = $pdo->prepare("SELECT mayor_codigo, mayor_nombre, partida_numero, debe, haber
    FROM v_libro_mayor WHERE fecha BETWEEN :d AND :h ORDER BY mayor_codigo, fecha, partida_numero");
$stmt->execute(['d' => $desde, 'h' => $hasta]);

$cuentas = [];
foreach ($stmt as $m) {
    $k = $m['mayor_codigo'];
    $cuentas[$k]['nombre'] = $m['mayor_nombre'];
    if ($m['debe'] > 0)  $cuentas[$k]['debe'][]  = [$m['partida_numero'], $m['debe']];
    if ($m['haber'] > 0) $cuentas[$k]['haber'][] = [$m['partida_numero'], $m['haber']];
}

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Libro mayor', $etiqueta . ' · esquemas en T (' . count($cuentas) . ' cuentas con movimiento)', $mes); ?>

        <?php if (!$cuentas): ?>
            <div class="<?php echo CLS_CARD; ?>"><?php sin_datos(); ?></div>
        <?php else: ?>
        <div class="grid grid-cols-1 2xl:grid-cols-2 gap-5">
        <?php foreach ($cuentas as $codigo => $c):
            $debe  = $c['debe']  ?? [];
            $haber = $c['haber'] ?? [];
            $td = array_sum(array_column($debe, 1));
            $th = array_sum(array_column($haber, 1));
            $filas = max(count($debe), count($haber));
            $sd = $td > $th ? $td - $th : 0;
            $sh = $th > $td ? $th - $td : 0;
        ?>
            <section class="<?php echo CLS_CARD; ?>">
                <h2 class="px-5 py-3 text-sm font-semibold text-slate-800 bg-slate-50 border-b border-slate-200">
                    <span class="font-mono text-slate-400 mr-2"><?php echo e($codigo); ?></span><?php echo e($c['nombre']); ?>
                </h2>
                <table class="<?php echo CLS_TABLE; ?>">
                    <thead>
                        <tr>
                            <th class="<?php echo CLS_TH; ?> text-left">Part.</th>
                            <th class="<?php echo CLS_TH; ?> text-right border-r border-slate-200">Debe</th>
                            <th class="<?php echo CLS_TH; ?> text-left">Part.</th>
                            <th class="<?php echo CLS_TH; ?> text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($i = 0; $i < $filas; $i++): ?>
                        <tr>
                            <td class="<?php echo CLS_TD; ?> text-slate-400 font-mono"><?php echo isset($debe[$i]) ? $debe[$i][0] . ')' : ''; ?></td>
                            <td class="<?php echo CLS_NUM; ?> border-r border-slate-200"><?php echo isset($debe[$i]) ? num($debe[$i][1]) : ''; ?></td>
                            <td class="<?php echo CLS_TD; ?> text-slate-400 font-mono"><?php echo isset($haber[$i]) ? '(' . $haber[$i][0] : ''; ?></td>
                            <td class="<?php echo CLS_NUM; ?>"><?php echo isset($haber[$i]) ? num($haber[$i][1]) : ''; ?></td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-500">Mov.</td>
                            <td class="px-4 py-2.5 text-right tabular-nums border-r border-slate-200"><?php echo num($td); ?></td>
                            <td class="px-4 py-2.5"></td>
                            <td class="px-4 py-2.5 text-right tabular-nums"><?php echo num($th); ?></td>
                        </tr>
                        <tr class="font-bold text-slate-900">
                            <td class="px-4 py-2.5 text-slate-500">Saldo</td>
                            <td class="px-4 py-2.5 text-right tabular-nums border-r border-slate-200"><?php echo $sd ? num($sd) : ''; ?></td>
                            <td class="px-4 py-2.5 text-xs text-emerald-600"><?php echo (!$sd && !$sh) ? 'SALDADA' : ''; ?></td>
                            <td class="px-4 py-2.5 text-right tabular-nums"><?php echo $sh ? num($sh) : ''; ?></td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>