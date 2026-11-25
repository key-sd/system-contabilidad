<?php
$base_path = '';
$page_title = 'Hogaris - Balance general';
$active_menu = 'balance_general';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

// Fecha de corte: ?corte=YYYY-MM-DD (por defecto, la última partida contabilizada)
$max = $pdo->query("SELECT MAX(fecha) FROM partidas WHERE estado = 'contabilizada'")->fetchColumn() ?: date('Y-m-d');
$corte = $_GET['corte'] ?? $max;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte)) $corte = $max;

$stmt = $pdo->prepare("SELECT m.codigo, m.nombre, m.tipo, g.nombre AS grupo,
        SUM(a.debe) AS mov_d, SUM(a.haber) AS mov_h
    FROM v_asientos a
    JOIN cuentas m ON m.id = a.mayor_id
    JOIN cuentas g ON g.id = m.padre_id
    WHERE a.fecha <= :c
    GROUP BY m.id, m.codigo, m.nombre, m.tipo, g.nombre
    ORDER BY m.codigo");
$stmt->execute(['c' => $corte]);
$cuentas = $stmt->fetchAll();

// Estructura del balance
$secc = ['activo' => [], 'pasivo' => [], 'patrimonio' => []];
$bal = ['mov_d' => 0, 'mov_h' => 0, 'sal_d' => 0, 'sal_h' => 0];
foreach ($cuentas as $c) {
    $dif = $c['mov_d'] - $c['mov_h'];
    $bal['mov_d'] += $c['mov_d']; $bal['mov_h'] += $c['mov_h'];
    $bal['sal_d'] += max($dif, 0); $bal['sal_h'] += max(-$dif, 0);
    if (isset($secc[$c['tipo']])) {
        $saldo = $c['tipo'] === 'activo' ? $dif : -$dif;   // depreciación y pérdida salen negativas
        if (abs($saldo) > 0.004) $secc[$c['tipo']][$c['grupo']][] = [$c['nombre'], $saldo];
    }
}
$total = fn($t) => array_sum(array_map(fn($g) => array_sum(array_column($g, 1)), $secc[$t]));
$t_activo = $total('activo'); $t_pasivo = $total('pasivo'); $t_patr = $total('patrimonio');
$cuadra = abs($t_activo - ($t_pasivo + $t_patr)) < 0.005;

function bloque_balance(array $grupos): void {
    foreach ($grupos as $grupo => $filas) {
        echo '<tr><td colspan="2" class="px-5 pt-4 pb-1 text-xs font-bold uppercase tracking-wider text-slate-400">' . e($grupo) . '</td></tr>';
        foreach ($filas as [$n, $s]) {
            echo '<tr><td class="px-5 py-2 border-b border-slate-100 text-slate-700">' . e($n) . '</td>'
               . '<td class="px-5 py-2 border-b border-slate-100 text-right tabular-nums ' . ($s < 0 ? 'text-rose-600' : '') . '">' . money($s) . '</td></tr>';
        }
    }
}

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Balance general</h1>
                <p class="text-sm text-slate-500 mt-1">Estado de situación financiera al <?php echo fecha_es($corte); ?></p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="corte" value="<?php echo e($corte); ?>"
                       class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <button class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-500 text-white rounded-xl transition-colors">Al corte</button>
            </form>
        </div>

        <?php if (!$cuentas): ?>
            <div class="<?php echo CLS_CARD; ?>"><?php sin_datos('No hay movimientos hasta esa fecha.'); ?></div>
        <?php else: ?>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-4">
            <section class="<?php echo CLS_CARD; ?>">
                <h2 class="px-5 py-3 text-sm font-semibold text-slate-800 bg-slate-50 border-b border-slate-200">ACTIVO</h2>
                <table class="<?php echo CLS_TABLE; ?>"><tbody><?php bloque_balance($secc['activo']); ?></tbody>
                    <tfoot><tr class="bg-slate-900 text-white font-semibold">
                        <td class="px-5 py-3">TOTAL ACTIVO</td><td class="px-5 py-3 text-right tabular-nums"><?php echo money($t_activo); ?></td>
                    </tr></tfoot>
                </table>
            </section>

            <section class="<?php echo CLS_CARD; ?>">
                <h2 class="px-5 py-3 text-sm font-semibold text-slate-800 bg-slate-50 border-b border-slate-200">PASIVO Y PATRIMONIO</h2>
                <table class="<?php echo CLS_TABLE; ?>"><tbody>
                    <?php bloque_balance($secc['pasivo']); ?>
                    <tr class="bg-slate-50"><td class="px-5 py-2 font-semibold text-slate-700">Total pasivo</td><td class="px-5 py-2 text-right tabular-nums font-semibold"><?php echo money($t_pasivo); ?></td></tr>
                    <?php bloque_balance($secc['patrimonio']); ?>
                    <tr class="bg-slate-50"><td class="px-5 py-2 font-semibold text-slate-700">Total patrimonio neto</td><td class="px-5 py-2 text-right tabular-nums font-semibold"><?php echo money($t_patr); ?></td></tr>
                </tbody>
                    <tfoot><tr class="bg-slate-900 text-white font-semibold">
                        <td class="px-5 py-3">TOTAL PASIVO + PATRIMONIO</td><td class="px-5 py-3 text-right tabular-nums"><?php echo money($t_pasivo + $t_patr); ?></td>
                    </tr></tfoot>
                </table>
            </section>
        </div>

        <p class="mb-8 text-sm <?php echo $cuadra ? 'text-emerald-600' : 'text-rose-600'; ?>">
            <i class="bi <?php echo $cuadra ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
            <?php echo $cuadra ? 'El balance cuadra: Activo = Pasivo + Patrimonio.' : 'El balance NO cuadra. Revise las partidas.'; ?>
            <span class="text-slate-400">(Antes del cierre, el resultado del período aparece en las cuentas de ingresos y gastos, no en el patrimonio.)</span>
        </p>

        <h2 class="text-lg font-bold text-slate-900 mb-3">Balanza de comprobación</h2>
        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
            <table class="<?php echo CLS_TABLE; ?>">
                <thead><tr>
                    <th class="<?php echo CLS_TH; ?> text-left">Código</th>
                    <th class="<?php echo CLS_TH; ?> text-left">Cuenta contable</th>
                    <th class="<?php echo CLS_TH; ?> text-right">Mov. deudor</th>
                    <th class="<?php echo CLS_TH; ?> text-right">Mov. acreedor</th>
                    <th class="<?php echo CLS_TH; ?> text-right">Saldo deudor</th>
                    <th class="<?php echo CLS_TH; ?> text-right">Saldo acreedor</th>
                </tr></thead>
                <tbody>
                <?php foreach ($cuentas as $c): $dif = $c['mov_d'] - $c['mov_h']; ?>
                    <tr>
                        <td class="<?php echo CLS_TD; ?> font-mono text-slate-400"><?php echo e($c['codigo']); ?></td>
                        <td class="<?php echo CLS_TD; ?>"><?php echo e($c['nombre']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($c['mov_d']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($c['mov_h']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo $dif > 0 ? num($dif) : ''; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo $dif < 0 ? num(-$dif) : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="bg-slate-900 text-white font-semibold">
                    <td colspan="2" class="px-4 py-3 text-right">SUMAS IGUALES</td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo num($bal['mov_d']); ?></td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo num($bal['mov_h']); ?></td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo num($bal['sal_d']); ?></td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo num($bal['sal_h']); ?></td>
                </tr></tfoot>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>