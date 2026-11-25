<?php
// Funciones auxiliares compartidas por las páginas del sistema.

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function num($n): string {
    return number_format((float)$n, 2);
}

function money($n): string {
    $n = (float)$n;
    return ($n < 0 ? '-$' : '$') . number_format(abs($n), 2);
}

function fecha_es($d): string {
    return $d ? date('d/m/Y', strtotime($d)) : '';
}

function nombre_mes(int $m): string {
    $meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return $meses[$m] ?? '';
}

/**
 * Lee el filtro ?mes=YYYY-MM. Devuelve [desde, hasta, etiqueta, valor_input].
 * Sin filtro => todo el período.
 */
function periodo_mes(): array {
    $mes = $_GET['mes'] ?? '';
    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mes, $m)) {
        $desde = "$mes-01";
        $hasta = date('Y-m-t', strtotime($desde));
        return [$desde, $hasta, nombre_mes((int)$m[2]) . ' ' . $m[1], $mes];
    }
    return ['1900-01-01', '2999-12-31', 'Todo el período', ''];
}

/** Barra de título + filtro de mes. */
function encabezado_pagina(string $titulo, string $subtitulo, ?string $mes = null, string $extra = ''): void {
    ?>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?php echo e($titulo); ?></h1>
            <p class="text-sm text-slate-500 mt-1"><?php echo e($subtitulo); ?></p>
        </div>
        <?php if ($mes !== null): ?>
        <form method="GET" class="flex items-center gap-2">
            <?php echo $extra; ?>
            <input type="month" name="mes" value="<?php echo e($mes); ?>"
                   class="px-3 py-2 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            <button class="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-500 text-white rounded-xl transition-colors">Filtrar</button>
            <?php if ($mes !== ''): ?>
                <a href="?" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-800">Ver todo</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php
}

/** Clases reutilizables de tablas. */
const CLS_CARD  = 'bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden';
const CLS_TABLE = 'w-full text-sm';
const CLS_TH    = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200 whitespace-nowrap';
const CLS_TD    = 'px-4 py-2.5 border-b border-slate-100 whitespace-nowrap';
const CLS_NUM   = 'px-4 py-2.5 border-b border-slate-100 text-right tabular-nums whitespace-nowrap';

function sin_datos(string $msg = 'No hay registros para el período seleccionado.'): void {
    echo '<div class="p-10 text-center text-sm text-slate-400">' . e($msg) . '</div>';
}