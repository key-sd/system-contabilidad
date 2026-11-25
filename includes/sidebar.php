<?php
function getNavClass($name, $active_menu) {
    if ($name === $active_menu) {
        return 'bg-blue-600 text-white font-medium shadow-sm';
    }
    return 'text-slate-300 hover:text-white hover:bg-slate-800/60 font-normal transition-colors duration-150';
}
?>

<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 z-30 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between transition-transform duration-300 transform -translate-x-full lg:translate-x-0 select-none">
    
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800/80">
        <a href="<?php echo $base_path; ?>index.php" class="flex items-center gap-2.5 text-white font-bold text-xl tracking-tight">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white text-base shadow-md shadow-blue-500/30">
                <i class="bi bi-house-door-fill"></i>
            </div>
            <span>Hogaris</span>
        </a>
        <button id="close-sidebar-btn" class="lg:hidden text-slate-400 hover:text-white p-1 rounded-lg">
            <i class="bi bi-x-lg text-lg"></i>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar px-3 py-4 space-y-6">
        
        <div>
            <a href="<?php echo $base_path; ?>index.php" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm <?php echo getNavClass('inicio', $active_menu); ?>">
                <i class="bi bi-grid-1x2 text-base"></i>
                <span>Inicio</span>
            </a>
        </div>
        <div>
            <div class="px-3 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Registro</span>
            </div>
            <nav class="space-y-1">
                <a href="catalogo.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('catalogo', $active_menu); ?>">
                    <i class="bi bi-journal-bookmark text-base"></i>
                    <span>Catálogo de cuentas</span>
                </a>
                <a href="libro_diario.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('libro_diario', $active_menu); ?>">
                    <i class="bi bi-journal-text text-base"></i>
                    <span>Libro diario</span>
                </a>
                <a href="libro_mayor.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('libro_mayor', $active_menu); ?>">
                    <i class="bi bi-journals text-base"></i>
                    <span>Libro mayor</span>
                </a>
            </nav>
        </div>
        <div>
            <div class="px-3 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">IVA</span>
            </div>
            <nav class="space-y-1">
                <a href="libro_compras.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('libro_compras', $active_menu); ?>">
                    <i class="bi bi-cart-check text-base"></i>
                    <span>Libro de compras</span>
                </a>
                <a href="ventas_constribuyentes.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('ventas_contribuyentes', $active_menu); ?>">
                    <i class="bi bi-receipt-cutoff text-base"></i>
                    <span>Ventas a contribuyentes</span>
                </a>
                <a href="ventas_consumidor.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('ventas_consumidor', $active_menu); ?>">
                    <i class="bi bi-receipt text-base"></i>
                    <span>Ventas a consumidor final</span>
                </a>
            </nav>
        </div>
        <div>
            <div class="px-3 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Reportes</span>
            </div>
            <nav class="space-y-1">
                <a href="balance_general.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('balance_general', $active_menu); ?>">
                    <i class="bi bi-file-earmark-bar-graph text-base"></i>
                    <span>Balance general</span>
                </a>
                <a href="estado_resultado.php" 
                   class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-sm <?php echo getNavClass('estado_resultados', $active_menu); ?>">
                    <i class="bi bi-graph-up-arrow text-base"></i>
                    <span>Estado de resultados</span>
                </a>
            </nav>
        </div>

    </div>
    <div class="p-3 border-t border-slate-800 bg-slate-950/40">
        <a href="<?php echo $base_path; ?>logout.php" 
           class="flex items-center justify-between w-full p-2.5 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-slate-800/80 transition-colors text-xs font-medium">
            <div class="flex items-center gap-2">
                <i class="bi bi-box-arrow-left text-base"></i>
                <span>Cerrar sesión</span>
            </div>
            <span class="text-[10px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded font-mono">admin</span>
        </a>
    </div>

</aside>
