<?php
$base_path = '';
$page_title = 'Hogaris - Estado de resultados';
$active_menu = 'estado_resultados';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

[$desde, $hasta, $etiqueta, $mes] = periodo_mes();

$stmt = $pdo->prepare("SELECT codigo, cuenta, SUM(monto) AS monto
    FROM v_estado_resultados WHERE fecha BETWEEN :d AND :h GROUP BY codigo, cuenta ORDER BY codigo");
$stmt->execute(['d' => $desde, 'h' => $hasta]);
$m = [];
foreach ($stmt as $r) $m[$r['codigo']] = (float)$r['monto'];

$ventas   = $m['4101'] ?? 0;
$costo    = $m['5101'] ?? 0;
$bruta    = $ventas - $costo;
$g_ventas = $m['6101'] ?? 0;
$g_admin  = $m['6102'] ?? 0;
$gastos   = $g_ventas + $g_admin;
$neto     = $bruta - $gastos;

function fila_er(string $label, float $monto, string $estilo = '', int $sangria = 0): void {
    echo '<tr class="' . $estilo . '"><td class="px-5 py-2.5 border-b border-slate-100" style="padding-left:' . (1.25 + $sangria * 1.5) . 'rem">' . e($label) . '</td>'
       . '<td class="px-5 py-2.5 border-b border-slate-100 text-right tabular-nums">' . money($monto) . '</td></tr>';
}

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Estado de resultados', $etiqueta, $mes); ?>

        <div class="<?php echo CLS_CARD; ?> max-w-2xl">
            <?php if (!$m): sin_datos(); else: ?>
            <table class="<?php echo CLS_TABLE; ?>">
                <tbody>
                    <?php fila_er('Ventas netas', $ventas, 'font-semibold text-slate-900'); ?>
                    <?php fila_er('(-) Costo de ventas', $costo, 'text-slate-600', 1); ?>
                    <?php fila_er($bruta >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA', $bruta, 'font-bold bg-slate-50 text-slate-900'); ?>
                    <?php fila_er('Gastos de ventas', $g_ventas, 'text-slate-600', 1); ?>
                    <?php fila_er('Gastos de administración', $g_admin, 'text-slate-600', 1); ?>
                    <?php fila_er('(-) Total gastos operativos', $gastos, 'font-semibold text-slate-800'); ?>
                </tbody>
                <tfoot>
                    <tr class="font-bold text-white <?php echo $neto >= 0 ? 'bg-emerald-600' : 'bg-rose-600'; ?>">
                        <td class="px-5 py-3"><?php echo $neto >= 0 ? 'UTILIDAD DEL PERÍODO' : 'PÉRDIDA DEL PERÍODO'; ?></td>
                        <td class="px-5 py-3 text-right tabular-nums"><?php echo money($neto); ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>