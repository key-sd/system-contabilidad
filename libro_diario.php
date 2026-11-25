<?php
$base_path = '';
$page_title = 'Hogaris - Libro diario';
$active_menu = 'libro_diario';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

[$desde, $hasta, $etiqueta, $mes] = periodo_mes();

$stmt = $pdo->prepare("SELECT partida, fecha, folio, concepto, cuenta, cuenta_mayor, es_subcuenta, debe, haber
    FROM v_libro_diario WHERE fecha BETWEEN :d AND :h ORDER BY partida, orden");
$stmt->execute(['d' => $desde, 'h' => $hasta]);

// Agrupar: partida -> cuentas de mayor -> subcuentas (columna PARCIAL)
$partidas = [];
foreach ($stmt as $l) {
    $n = $l['partida'];
    if (!isset($partidas[$n])) {
        $partidas[$n] = ['fecha' => $l['fecha'], 'folio' => $l['folio'], 'concepto' => $l['concepto'], 'grupos' => []];
    }
    $g = &$partidas[$n]['grupos'];
    $last = count($g) - 1;
    if ($last < 0 || $g[$last]['mayor'] !== $l['cuenta_mayor'] || !$l['es_subcuenta'] && $g[$last]['subs']) {
        $g[] = ['mayor' => $l['cuenta_mayor'], 'debe' => 0, 'haber' => 0, 'subs' => []];
        $last++;
    }
    $g[$last]['debe']  += $l['debe'];
    $g[$last]['haber'] += $l['haber'];
    if ($l['es_subcuenta']) {
        $g[$last]['subs'][] = ['nombre' => $l['cuenta'], 'monto' => $l['debe'] + $l['haber']];
    }
    unset($g);
}

$tot_debe = $tot_haber = 0;
foreach ($partidas as $p) foreach ($p['grupos'] as $g) { $tot_debe += $g['debe']; $tot_haber += $g['haber']; }

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Libro diario', $etiqueta . ' · ' . count($partidas) . ' partidas', $mes); ?>

        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
        <?php if (!$partidas): sin_datos(); else: ?>
            <table class="<?php echo CLS_TABLE; ?>">
                <thead>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-left w-28">Fecha</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Detalle</th>
                        <th class="<?php echo CLS_TH; ?> text-center">Folio</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Parcial</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Debe</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Haber</th>
                    </tr>
                </thead>
                <?php foreach ($partidas as $num => $p): ?>
                <tbody>
                    <tr class="bg-slate-50/70">
                        <td class="<?php echo CLS_TD; ?> text-slate-500"><?php echo fecha_es($p['fecha']); ?></td>
                        <td class="<?php echo CLS_TD; ?> font-semibold text-slate-800">Partida N° <?php echo (int)$num; ?></td>
                        <td class="<?php echo CLS_TD; ?> text-center text-slate-400 font-mono"><?php echo $p['folio'] ? $num . '/' . $p['folio'] : ''; ?></td>
                        <td colspan="3" class="<?php echo CLS_TD; ?>"></td>
                    </tr>
                    <?php foreach ($p['grupos'] as $g): ?>
                    <tr>
                        <td class="<?php echo CLS_TD; ?>"></td>
                        <td class="<?php echo CLS_TD; ?> text-slate-800" style="padding-left: <?php echo $g['haber'] > 0 ? '2.5' : '1'; ?>rem">
                            <?php echo e($g['mayor']); ?>
                        </td>
                        <td class="<?php echo CLS_TD; ?>"></td>
                        <td class="<?php echo CLS_NUM; ?>"></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo $g['debe'] > 0 ? num($g['debe']) : ''; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo $g['haber'] > 0 ? num($g['haber']) : ''; ?></td>
                    </tr>
                        <?php foreach ($g['subs'] as $s): ?>
                        <tr>
                            <td class="<?php echo CLS_TD; ?>"></td>
                            <td class="<?php echo CLS_TD; ?> text-slate-500 text-xs" style="padding-left: <?php echo $g['haber'] > 0 ? '4' : '2.5'; ?>rem"><?php echo e($s['nombre']); ?></td>
                            <td class="<?php echo CLS_TD; ?>"></td>
                            <td class="<?php echo CLS_NUM; ?> text-slate-500 text-xs"><?php echo num($s['monto']); ?></td>
                            <td class="<?php echo CLS_NUM; ?>"></td>
                            <td class="<?php echo CLS_NUM; ?>"></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td class="<?php echo CLS_TD; ?>"></td>
                        <td colspan="5" class="px-4 py-2.5 border-b border-slate-200 text-xs italic text-slate-500" style="padding-left:1rem"><?php echo e($p['concepto']); ?></td>
                    </tr>
                </tbody>
                <?php endforeach; ?>
                <tfoot>
                    <tr class="bg-slate-900 text-white font-semibold">
                        <td colspan="4" class="px-4 py-3 text-right">SUMAS TOTALES</td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot_debe); ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?php echo num($tot_haber); ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>