<?php
class Comment
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getComentarios($id_publicacion, $id_usuario_sesion = 0)
    {
        // Comentarios raíz (sin padre)
        $stmt = $this->pdo->prepare("
            SELECT c.id_comentario, c.texto AS contenido, c.fecha_comentario,
                   u.username, u.foto_perfil, u.id_usuario,
                   (SELECT COUNT(*) FROM likes_comentarios WHERE id_comentario = c.id_comentario) AS totalLikes
            FROM comentarios c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE c.id_publicacion = ? AND (c.id_padre IS NULL OR c.id_padre = 0)
            ORDER BY c.fecha_comentario ASC
        ");
        $stmt->execute([$id_publicacion]);
        $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comentarios as &$c) {
            // Verificar si el usuario dio like a este comentario
            $c['yaDioLike'] = $this->haDadoLikeComentario($id_usuario_sesion, $c['id_comentario']);

            // Cargar respuestas
            $stmtR = $this->pdo->prepare("
                SELECT r.id_comentario, r.texto AS contenido, r.fecha_comentario,
                       u.username, u.foto_perfil, u.id_usuario,
                       (SELECT COUNT(*) FROM likes_comentarios WHERE id_comentario = r.id_comentario) AS totalLikes
                FROM comentarios r
                JOIN usuarios u ON r.id_usuario = u.id_usuario
                WHERE r.id_padre = ?
                ORDER BY r.fecha_comentario ASC
            ");
            $stmtR->execute([$c['id_comentario']]);
            $respuestas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

            foreach ($respuestas as &$r) {
                $r['yaDioLike'] = $this->haDadoLikeComentario($id_usuario_sesion, $r['id_comentario']);
                $r['foto_perfil'] = $r['foto_perfil'] ? base64_encode($r['foto_perfil']) : null;
            }
            $c['respuestas'] = $respuestas;
        }

        return $comentarios;
    }

    public function agregarComentario($id_usuario, $id_publicacion, $contenido, $id_padre = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comentarios (id_usuario, id_publicacion, texto, fecha_comentario, id_padre)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        return $stmt->execute([$id_usuario, $id_publicacion, $contenido, $id_padre ?: null]);
    }

    public function borrarComentario($id_comentario, $id_usuario)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM comentarios WHERE id_comentario = ? AND id_usuario = ?
        ");
        return $stmt->execute([$id_comentario, $id_usuario]);
    }

    public function toggleLikeComentario($id_usuario, $id_comentario)
    {
        if ($this->haDadoLikeComentario($id_usuario, $id_comentario)) {
            $stmt = $this->pdo->prepare("DELETE FROM likes_comentarios WHERE id_usuario = ? AND id_comentario = ?");
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO likes_comentarios (id_usuario, id_comentario) VALUES (?, ?)");
        }
        return $stmt->execute([$id_usuario, $id_comentario]);
    }

    public function haDadoLikeComentario($id_usuario, $id_comentario)
    {
        if (!$id_usuario) return false;
        $stmt = $this->pdo->prepare("SELECT 1 FROM likes_comentarios WHERE id_usuario = ? AND id_comentario = ?");
        $stmt->execute([$id_usuario, $id_comentario]);
        return (bool)$stmt->fetch();
    }

    public function getLikesComentario($id_comentario)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM likes_comentarios WHERE id_comentario = ?");
        $stmt->execute([$id_comentario]);
        return $stmt->fetchColumn();
    }
}
