<?php
class Post {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function haDadoLike($id_usuario, $id_publicacion) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM likes WHERE id_usuario = ? AND id_publicacion = ?");
        $stmt->execute([$id_usuario, $id_publicacion]);
        return (bool)$stmt->fetch();
    }

    public function toggleLike($id_usuario, $id_publicacion) {
        if ($this->haDadoLike($id_usuario, $id_publicacion)) {
            // Eliminar like
            $stmt = $this->pdo->prepare("DELETE FROM likes WHERE id_usuario = ? AND id_publicacion = ?");
        } else {
            // Insertar like
            $stmt = $this->pdo->prepare("INSERT INTO likes (id_usuario, id_publicacion) VALUES (?, ?)");
        }
        return $stmt->execute([$id_usuario, $id_publicacion]);
    }
}