<?php
$base_path = '';
$page_title = 'Hogaris - Inicio';
$active_menu = 'inicio';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

$res = $pdo->query("SELECT
        COALESCE(SUM(CASE WHEN codigo='4101' THEN monto END),0) AS ventas,
        COALESCE(SUM(CASE WHEN codigo='5101' THEN monto END),0) AS costo,
        COALESCE(SUM(CASE WHEN codigo IN ('6101','6102') THEN monto END),0) AS gastos
    FROM v_estado_resultados")->fetch();
$ventas = (float)$res['ventas'];
$costo  = (float)$res['costo'];
$gastos = (float)$res['gastos'];
$resultado = $ventas - $costo - $gastos;

$saldos = $pdo->query("SELECT codigo, saldo_deudor - saldo_acreedor AS saldo FROM v_balanza_comprobacion")->fetchAll(PDO::FETCH_KEY_PAIR);
$efectivo = (float)($saldos['1101'] ?? 0);
$por_cobrar = (float)($saldos['1102'] ?? 0) + (float)($saldos['1103'] ?? 0);
$iva_credito = (float)($saldos['1105'] ?? 0);

$inv = $pdo->query("SELECT COALESCE(SUM(valor_inventario),0) AS valor, COALESCE(SUM(stock_actual),0) AS unidades FROM productos")->fetch();

$ultimas = $pdo->query("SELECT numero, fecha, concepto FROM partidas WHERE estado='contabilizada' ORDER BY fecha DESC, numero DESC LIMIT 6")->fetchAll();
$criticos = $pdo->query("SELECT p.codigo, p.marca, p.modelo, p.stock_actual, c.nombre AS categoria
    FROM productos p JOIN categorias c ON c.id = p.categoria_id
    WHERE p.estado='activo' AND p.stock_actual <= GREATEST(p.stock_minimo, 1)
    ORDER BY p.stock_actual, p.marca LIMIT 8")->fetchAll();

$cards = [
    ['Ventas netas',       money($ventas),      'bi-cash-stack',        'text-blue-600 bg-blue-50'],
    ['Resultado del período', money($resultado), $resultado >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow', $resultado >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'],
    ['Efectivo y equivalentes', money($efectivo), 'bi-bank',            'text-indigo-600 bg-indigo-50'],
    ['Inventario (' . number_format($inv['unidades']) . ' uds.)', money($inv['valor']), 'bi-box-seam', 'text-amber-600 bg-amber-50'],
    ['Por cobrar',         money($por_cobrar),  'bi-hourglass-split',   'text-sky-600 bg-sky-50'],
    ['IVA crédito fiscal', money($iva_credito), 'bi-receipt',           'text-violet-600 bg-violet-50'],
];

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Inicio', 'Resumen contable de ' . ($_SESSION['usuario_nombre'] ?? 'Hogaris')); ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
            <?php foreach ($cards as [$label, $valor, $icono, $color]): ?>
            <div class="<?php echo CLS_CARD; ?> p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl <?php echo $color; ?>">
                    <i class="bi <?php echo $icono; ?>"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500"><?php echo e($label); ?></p>
                    <p class="text-xl font-bold text-slate-900 tabular-nums"><?php echo $valor; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <section class="<?php echo CLS_CARD; ?>">
                <h2 class="px-5 py-4 text-sm font-semibold text-slate-800 border-b border-slate-100">Últimas partidas</h2>
                <?php if (!$ultimas): sin_datos('Aún no hay partidas contabilizadas.'); else: ?>
                <table class="<?php echo CLS_TABLE; ?>">
                    <tbody>
                    <?php foreach ($ultimas as $p): ?>
                        <tr>
                            <td class="<?php echo CLS_TD; ?> text-slate-400 font-mono">N° <?php echo (int)$p['numero']; ?></td>
                            <td class="<?php echo CLS_TD; ?> text-slate-500"><?php echo fecha_es($p['fecha']); ?></td>
                            <td class="px-4 py-2.5 border-b border-slate-100 text-slate-700"><?php echo e($p['concepto']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>

            <section class="<?php echo CLS_CARD; ?>">
                <h2 class="px-5 py-4 text-sm font-semibold text-slate-800 border-b border-slate-100">Existencias críticas (1 unidad o menos)</h2>
                <?php if (!$criticos): sin_datos('Todo el inventario tiene existencias suficientes.'); else: ?>
                <table class="<?php echo CLS_TABLE; ?>">
                    <tbody>
                    <?php foreach ($criticos as $p): ?>
                        <tr>
                            <td class="<?php echo CLS_TD; ?> text-slate-400 font-mono"><?php echo e($p['codigo']); ?></td>
                            <td class="px-4 py-2.5 border-b border-slate-100 text-slate-700"><?php echo e($p['marca'] . ' — ' . $p['modelo']); ?></td>
                            <td class="<?php echo CLS_NUM; ?>">
                                <span class="px-2 py-0.5 rounded-lg text-xs font-semibold <?php echo $p['stock_actual'] == 0 ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'; ?>">
                                    <?php echo (int)$p['stock_actual']; ?> uds.
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>