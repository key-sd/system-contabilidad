<?php
session_start();
require_once 'includes/conexion.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        try {
            // Buscar usuario por nombre de usuario o por correo
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (usuario = :usuario OR email = :email) AND estado = 'activo' LIMIT 1");
            $stmt->execute([
                'usuario' => $username,
                'email'   => $username
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas o usuario inactivo.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el servidor al autenticar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hogaris - Iniciar Sesión</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen flex items-center justify-center p-4 selection:bg-blue-600 selection:text-white">

    <!-- Fondo decorativo con luces sutiles -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-md">
        
        <!-- Tarjeta de Login -->
        <div class="bg-slate-950/70 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-black/50">
            
            <!-- Logo y Encabezado -->
            <div class="text-center mb-8">
                <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/25 mb-4">
                    <i class="bi bi-house-door-fill"></i>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Hogaris</h1>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center gap-2.5">
                    <i class="bi bi-exclamation-triangle-fill text-base shrink-0"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Usuario o Correo
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="bi bi-person text-base"></i>
                        </span>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                               required 
                               placeholder="Ej: admin"
                               class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Contraseña
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="bi bi-lock text-base"></i>
                        </span>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               value="" 
                               required 
                               placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-600/30 transition-all duration-200 flex items-center justify-center gap-2 group">
                        <span>Iniciar sesión</span>
                        <i class="bi bi-arrow-right group-hover:translate-x-0.5 transition-transform"></i>
                    </button>
                </div>
            </form>

        </div>

    </div>

</body>
</html>
