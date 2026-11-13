<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/database.php';
cors();

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->conn->query("SELECT id, nombre, descripcion, votos, foto FROM disfraces WHERE eliminado = 0 ORDER BY votos DESC, id DESC");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === 'POST') {
        requireAuth();
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'multipart/form-data') === 0
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            && empty($_POST) && empty($_FILES)) {
            fail(413, "El archivo supera el límite de carga configurado en el servidor");
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($nombre === '' || $desc === '') fail(400, "Nombre y descripción obligatorios");
        if (mb_strlen($nombre) > 50)        fail(400, "El nombre admite hasta 50 caracteres");
        if (mb_strlen($desc) > 500)         fail(400, "La descripción admite hasta 500 caracteres");  
        $foto = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['foto'];
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
                    fail(413, "La imagen supera el límite de carga configurado en el servidor");
                }
                fail(400, "No se pudo recibir la imagen. Intenta seleccionarla nuevamente");
            }
            if ($upload['size'] > 35 * 1024 * 1024) fail(413, "La imagen no puede superar los 35 MB");
            $tmp = $upload['tmp_name'];
            // Se valida el tipo REAL del archivo, no solo la extensión que declara el cliente
            $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($tipos[$mime])) fail(400, "Formato de imagen no permitido");

            $dir = __DIR__ . '/../../uploads/';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                fail(500, "No se pudo preparar el almacenamiento de imágenes");
            }
            $foto = uniqid('dis_') . '.' . $tipos[$mime];
            if (!move_uploaded_file($tmp, $dir . $foto)) {
                fail(500, "No se pudo guardar la imagen");
            }
        }

        $stmt = $db->conn->prepare("INSERT INTO disfraces (nombre, descripcion, votos, foto, eliminado) VALUES (?, ?, 0, ?, 0)");
        $stmt->bindValue(1, $nombre);
        $stmt->bindValue(2, $desc);
        $stmt->bindValue(3, $foto);
        $stmt->execute();
        http_response_code(201);
        echo json_encode(["ok" => true, "id" => (int)$db->conn->lastInsertId()]);
        exit;
    }

    fail(405, "Método no permitido");
} catch (PDOException $e) {
    error_log("disfraces: " . $e->getMessage());
    fail(500, "Error interno del servidor");
}
