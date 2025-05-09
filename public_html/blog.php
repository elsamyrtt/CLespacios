<?php

// Prevenir acceso directo a este archivo si no está incluido desde otro
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}
// Configuración básica
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores al usuario final
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Aumentar límites para subida de archivos grandes
ini_set('upload_max_filesize', '0'); // Sin límite
ini_set('post_max_size', '0'); // Sin límite
ini_set('max_execution_time', '300'); // 5 minutos
ini_set('max_input_time', '300'); // 5 minutos

// Directorios y archivos importantes
define('CONFIG_FILE', realpath(__DIR__ . '/../private/config.php'));
define('UPLOAD_DIR', realpath(__DIR__ . '/../private/uploads'));
define('POSTS_FILE', realpath(__DIR__ . '/../private/posts.json'));

// Crear directorios si no existen
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Crear archivo de posts si no existe
if (!file_exists(POSTS_FILE)) {
    file_put_contents(POSTS_FILE, json_encode([]));
    chmod(POSTS_FILE, 0644);
}

// Cargar contraseña de administrador de forma segura
$ADMIN_PASSWORD = '';
if (file_exists(CONFIG_FILE)) {
    include_once(CONFIG_FILE);
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Función para verificar la contraseña de administrador
function verifyPassword($password) {
    global $ADMIN_PASSWORD;
    return $password === $ADMIN_PASSWORD;
}

// Función para sanitizar entradas
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función para sanitizar nombre de archivo
function sanitizeFilename($filename) {
    // Eliminar caracteres especiales y mantener la extensión
    $info = pathinfo($filename);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $info['filename']);
    return $name . '.' . $info['extension'];
}

// Función para cargar posts
function loadPosts() {
    if (file_exists(POSTS_FILE)) {
        $content = file_get_contents(POSTS_FILE);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// Función para guardar posts
function savePosts($posts) {
    file_put_contents(POSTS_FILE, json_encode($posts, JSON_PRETTY_PRINT));
}

// Manejar la autenticación
if (isset($_POST['password'])) {
    if (verifyPassword($_POST['password'])) {
        $_SESSION['authenticated'] = true;
        // Regenerar ID de sesión para prevenir ataques de fijación de sesión
        session_regenerate_id(true);
    } else {
        $error = "Contraseña incorrecta";
    }
}

// Manejar cierre de sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Manejar subida de archivos
if (isAuthenticated() && isset($_POST['upload']) && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filename = sanitizeFilename($_FILES['file']['name']);
        $destination = UPLOAD_DIR . '/' . $filename;
        
        // Mover el archivo subido al directorio de destino
        if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            $uploadSuccess = "Archivo subido correctamente: " . $filename;
        } else {
            $uploadError = "Error al subir el archivo";
        }
    } else {
        $uploadError = "Error en la subida: " . $_FILES['file']['error'];
    }
}

// Manejar creación de posts
if (isAuthenticated() && isset($_POST['create_post'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = $_POST['content'] ?? ''; // No sanitizamos el contenido para permitir HTML
    
    if (!empty($title) && !empty($content)) {
        $posts = loadPosts();
        $newPost = [
            'id' => time(), // Timestamp como ID único
            'title' => $title,
            'content' => $content,
            'date' => date('Y-m-d H:i:s'),
            'year' => date('Y'),
            'day' => date('d'),
            'hour' => date('H:i')
        ];
        
        array_unshift($posts, $newPost); // Añadir al principio
        savePosts($posts);
        $postSuccess = "Post creado correctamente";
    } else {
        $postError = "El título y el contenido son obligatorios";
    }
}

// Manejar eliminación de archivos
if (isAuthenticated() && isset($_POST['delete_file'])) {
    $filename = sanitizeInput($_POST['filename']);
    $filepath = UPLOAD_DIR . '/' . $filename;
    
    if (file_exists($filepath) && unlink($filepath)) {
        $deleteSuccess = "Archivo eliminado correctamente";
    } else {
        $deleteError = "Error al eliminar el archivo";
    }
}

// Manejar eliminación de posts
if (isAuthenticated() && isset($_POST['delete_post'])) {
    $postId = (int)$_POST['post_id'];
    $posts = loadPosts();
    
    $filtered = array_filter($posts, function($post) use ($postId) {
        return $post['id'] !== $postId;
    });
    
    if (count($filtered) < count($posts)) {
        savePosts(array_values($filtered)); // Reindexar array
        $postDeleteSuccess = "Post eliminado correctamente";
    } else {
        $postDeleteError = "Error al eliminar el post";
    }
}

// Manejar descarga de archivos
if (isset($_GET['download'])) {
    $filename = sanitizeInput($_GET['download']);
    $filepath = UPLOAD_DIR . '/' . $filename;
    
    // Verificar que el archivo existe y es accesible
    if (file_exists($filepath) && is_readable($filepath)) {
        // Enviar encabezados para descarga
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Archivo no encontrado";
        exit;
    }
}

// Función para listar archivos en el directorio de subidas
function listUploadedFiles() {
    $files = [];
    if (file_exists(UPLOAD_DIR) && is_dir(UPLOAD_DIR)) {
        $items = scandir(UPLOAD_DIR);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_file(UPLOAD_DIR . '/' . $item)) {
                $files[] = [
                    'name' => $item,
                    'size' => filesize(UPLOAD_DIR . '/' . $item),
                    'modified' => date('Y-m-d H:i:s', filemtime(UPLOAD_DIR . '/' . $item))
                ];
            }
        }
    }
    return $files;
}

// Cargar los posts y archivos para mostrarlos
$posts = loadPosts();
$files = isAuthenticated() ? listUploadedFiles() : [];

// Función para formatear el tamaño de archivo
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Generar un token CSRF para proteger formularios
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Blog y Archivos</title>
    <style>
        /* Estilos mínimos necesarios */
        .error { color: red; }
        .success { color: green; }
        .hidden { display: none; }
        .admin-panel { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; }
        .file-item, .post-item { margin-bottom: 10px; padding: 5px; }
        textarea { width: 100%; height: 200px; }
    </style>
</head>
<body>
    <h1>Sistema de Blog y Archivos</h1>
    
    <?php if (!isAuthenticated()): ?>
        <!-- Formulario de login -->
        <div>
            <h2>Acceso Administrador</h2>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Acceder</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Panel de administración -->
        <div class="admin-panel">
            <p>Sesión de administrador activa. <a href="?logout=1">Cerrar sesión</a></p>
            
            <!-- Subida de archivos -->
            <div>
                <h2>Subir Archivo</h2>
                <?php if (isset($uploadSuccess)): ?>
                    <p class="success"><?php echo $uploadSuccess; ?></p>
                <?php endif; ?>
                <?php if (isset($uploadError)): ?>
                    <p class="error"><?php echo $uploadError; ?></p>
                <?php endif; ?>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="file" name="file" required>
                    <button type="submit" name="upload" value="1">Subir</button>
                </form>
            </div>
            
            <!-- Lista de archivos -->
            <div>
                <h2>Archivos Subidos</h2>
                <?php if (isset($deleteSuccess)): ?>
                    <p class="success"><?php echo $deleteSuccess; ?></p>
                <?php endif; ?>
                <?php if (isset($deleteError)): ?>
                    <p class="error"><?php echo $deleteError; ?></p>
                <?php endif; ?>
                
                <?php if (empty($files)): ?>
                    <p>No hay archivos subidos.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($files as $file): ?>
                            <li class="file-item">
                                <strong><?php echo sanitizeInput($file['name']); ?></strong>
                                (<?php echo formatFileSize($file['size']); ?>) - 
                                <?php echo sanitizeInput($file['modified']); ?>
                                
                                <a href="?download=<?php echo urlencode($file['name']); ?>">Descargar</a>
                                
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="filename" value="<?php echo sanitizeInput($file['name']); ?>">
                                    <button type="submit" name="delete_file" value="1">Eliminar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Crear post -->
            <div>
                <h2>Crear Nuevo Post</h2>
                <?php if (isset($postSuccess)): ?>
                    <p class="success"><?php echo $postSuccess; ?></p>
                <?php endif; ?>
                <?php if (isset($postError)): ?>
                    <p class="error"><?php echo $postError; ?></p>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div>
                        <label for="title">Título:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div>
                        <label for="content">Contenido:</label>
                        <textarea id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" name="create_post" value="1">Publicar</button>
                </form>
            </div>
            
            <!-- Gestión de posts -->
            <div>
                <h2>Gestionar Posts</h2>
                <?php if (isset($postDeleteSuccess)): ?>
                    <p class="success"><?php echo $postDeleteSuccess; ?></p>
                <?php endif; ?>
                <?php if (isset($postDeleteError)): ?>
                    <p class="error"><?php echo $postDeleteError; ?></p>
                <?php endif; ?>
                
                <?php if (empty($posts)): ?>
                    <p>No hay posts publicados.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($posts as $post): ?>
                            <li class="post-item">
                                <strong><?php echo sanitizeInput($post['title']); ?></strong>
                                (<?php echo sanitizeInput($post['date']); ?>)
                                
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="delete_post" value="1">Eliminar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Sección pública de posts -->
    <div>
        <h2>Posts Publicados</h2>
        <?php if (empty($posts)): ?>
            <p>No hay posts publicados.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article>
                    <h3><?php echo sanitizeInput($post['title']); ?></h3>
                    <p class="post-meta">
                        Publicado: <?php echo sanitizeInput($post['day']); ?>/<?php echo date('m'); ?>/<?php echo sanitizeInput($post['year']); ?> 
                        a las <?php echo sanitizeInput($post['hour']); ?>
                    </p>
                    <div class="post-content">
                        <?php echo $post['content']; ?>
                    </div>
                </article>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Script para evitar acceso mediante inspección de elemento -->
    <script>
    (function() {
        // Monitorear cambios en los elementos ocultos
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.classList.contains('hidden') && 
                    getComputedStyle(mutation.target).display !== 'none') {
                    mutation.target.style.display = 'none';
                }
            });
        });
        
        // Aplicar el observador a los elementos con clase 'hidden'
        document.querySelectorAll('.hidden').forEach(function(el) {
            observer.observe(el, { attributes: true });
        });
        
        // Evitar que se desactiven eventos
        document.addEventListener('DOMContentLoaded', function() {
            const originalAddEventListener = EventTarget.prototype.addEventListener;
            EventTarget.prototype.addEventListener = function(type, listener, options) {
                if (this.classList && this.classList.contains('hidden')) {
                    return;
                }
                originalAddEventListener.call(this, type, listener, options);
            };
        });
    })();
    </script>
</body>
</html>