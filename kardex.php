<?php
$base_path = '';
$page_title = 'Hogaris - Kardex';
$active_menu = 'kardex';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

$productos = $pdo->query("SELECT id, codigo, marca, modelo FROM productos ORDER BY codigo")->fetchAll();
$sel = (int)($_GET['producto'] ?? ($productos[0]['id'] ?? 0));

$stmt = $pdo->prepare("SELECT * FROM v_kardex WHERE producto_id = :p ORDER BY id");
$stmt->execute(['p' => $sel]);
$mov = $stmt->fetchAll();

$actual = null;
foreach ($productos as $p) if ($p['id'] == $sel) $actual = $p;

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Kardex</h1>
                <p class="text-sm text-slate-500 mt-1">Método: costo promedio ponderado<?php echo $actual ? ' · ' . e($actual['marca'] . ' — ' . $actual['modelo']) : ''; ?></p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <select name="producto" onchange="this.form.submit()"
                        class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 max-w-xs">
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo $p['id'] == $sel ? 'selected' : ''; ?>>
                            <?php echo e($p['codigo'] . ' · ' . $p['marca'] . ' — ' . $p['modelo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="px-4 py-2 text-sm bg-blue-600 text-white rounded-xl">Ver</button></noscript>
            </form>
        </div>

        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
        <?php if (!$mov): sin_datos('Este producto no tiene movimientos.'); else: ?>
            <table class="<?php echo CLS_TABLE; ?>">
                <thead>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-left" rowspan="2">Fecha</th>
                        <th class="<?php echo CLS_TH; ?> text-left" rowspan="2">Documento</th>
                        <th class="<?php echo CLS_TH; ?> text-left" rowspan="2">Concepto</th>
                        <th class="<?php echo CLS_TH; ?> text-center border-l border-slate-200" colspan="3">Movimiento</th>
                        <th class="<?php echo CLS_TH; ?> text-center border-l border-slate-200" colspan="3">Saldo</th>
                    </tr>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-right border-l border-slate-200">Entrada</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Salida</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo total</th>
                        <th class="<?php echo CLS_TH; ?> text-right border-l border-slate-200">Cantidad</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Costo prom.</th>
                        <th class="<?php echo CLS_TH; ?> text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mov as $m): ?>
                    <tr class="hover:bg-slate-50/60">
                        <td class="<?php echo CLS_TD; ?>"><?php echo fecha_es($m['fecha']); ?></td>
                        <td class="<?php echo CLS_TD; ?> font-mono"><?php echo e($m['documento']); ?></td>
                        <td class="<?php echo CLS_TD; ?> capitalize text-slate-500"><?php echo e(str_replace('_', ' ', $m['origen'])); ?></td>
                        <td class="<?php echo CLS_NUM; ?> border-l border-slate-100 text-emerald-600"><?php echo $m['entrada_cantidad'] ?: ''; ?></td>
                        <td class="<?php echo CLS_NUM; ?> text-rose-600"><?php echo $m['salida_cantidad'] ?: ''; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($m['costo_total']); ?></td>
                        <td class="<?php echo CLS_NUM; ?> border-l border-slate-100 font-semibold"><?php echo (int)$m['saldo_cantidad']; ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($m['saldo_costo_promedio']); ?></td>
                        <td class="<?php echo CLS_NUM; ?>"><?php echo num($m['saldo_valor']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>