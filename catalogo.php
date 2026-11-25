<?php
$base_path = '';
$page_title = 'Hogaris - Catálogo de cuentas';
$active_menu = 'catalogo';

require_once 'includes/header.php';
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';

$cuentas = $pdo->query("SELECT codigo, nombre, tipo, naturaleza, nivel, acepta_movimientos FROM cuentas ORDER BY codigo")->fetchAll();

require_once 'includes/sidebar.php';
?>

<div class="flex-1 flex flex-col h-screen overflow-hidden">
    <?php require_once 'includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6 sm:p-8">
        <?php encabezado_pagina('Catálogo de cuentas', count($cuentas) . ' cuentas registradas'); ?>

        <div class="<?php echo CLS_CARD; ?> overflow-x-auto">
            <table class="<?php echo CLS_TABLE; ?>">
                <thead>
                    <tr>
                        <th class="<?php echo CLS_TH; ?> text-left">Código</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Cuenta</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Tipo</th>
                        <th class="<?php echo CLS_TH; ?> text-left">Naturaleza</th>
                        <th class="<?php echo CLS_TH; ?> text-center">Movimientos</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cuentas as $c):
                    $nivel = (int)$c['nivel'];
                    $peso  = $nivel <= 2 ? 'font-bold text-slate-900' : ($nivel == 3 ? 'font-medium text-slate-800' : 'text-slate-600');
                    $fondo = $nivel == 1 ? 'bg-slate-50' : '';
                ?>
                    <tr class="<?php echo $fondo; ?>">
                        <td class="<?php echo CLS_TD; ?> font-mono text-slate-500"><?php echo e($c['codigo']); ?></td>
                        <td class="<?php echo CLS_TD; ?> <?php echo $peso; ?>" style="padding-left: <?php echo 1 + ($nivel - 1) * 1.5; ?>rem">
                            <?php echo e($c['nombre']); ?>
                        </td>
                        <td class="<?php echo CLS_TD; ?> capitalize text-slate-500"><?php echo e($c['tipo']); ?></td>
                        <td class="<?php echo CLS_TD; ?> capitalize text-slate-500"><?php echo e($c['naturaleza']); ?></td>
                        <td class="<?php echo CLS_TD; ?> text-center">
                            <?php echo $c['acepta_movimientos']
                                ? '<i class="bi bi-check-circle-fill text-emerald-500"></i>'
                                : '<span class="text-slate-300">—</span>'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>