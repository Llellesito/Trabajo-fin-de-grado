<?php
class Comment
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getComentarios($id_publicacion)
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id_comentario, c.texto AS contenido, c.fecha_comentario,
                   u.username, u.foto_perfil, u.id_usuario
            FROM comentarios c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE c.id_publicacion = ?
            ORDER BY c.fecha_comentario ASC
        ");
        $stmt->execute([$id_publicacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarComentario($id_usuario, $id_publicacion, $contenido)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO comentarios (id_usuario, id_publicacion, texto, fecha_comentario)
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$id_usuario, $id_publicacion, $contenido]);
    }

    public function borrarComentario($id_comentario, $id_usuario)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM comentarios WHERE id_comentario = ? AND id_usuario = ?
        ");
        return $stmt->execute([$id_comentario, $id_usuario]);
    }
}
