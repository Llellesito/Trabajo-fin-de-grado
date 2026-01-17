<?php

class User
{
    private $pdo;


    // El constructor recibe la conexión a la base de datos
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }


    // MÉTODO 1: Verificar si ya lo sigue
    public function estaSiguiendo($id_seguidor, $id_seguido)
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM seguidores WHERE id_seguidor = ? AND id_seguido = ?");
        $stmt->execute([$id_seguidor, $id_seguido]);
        return (bool)$stmt->fetch();
    }


    // MÉTODO 2: Alternar entre seguir y dejar de seguir
    public function toggleFollow($id_seguidor, $id_seguido)
    {
        if ($this->estaSiguiendo($id_seguidor, $id_seguido)) {
            // Unfollow
            $stmt = $this->pdo->prepare("DELETE FROM seguidores WHERE id_seguidor = ? AND id_seguido = ?");
        } else {
            // Follow
            $stmt = $this->pdo->prepare("INSERT INTO seguidores (id_seguidor, id_seguido) VALUES (?, ?)");
        }
        return $stmt->execute([$id_seguidor, $id_seguido]);
    }


    public function usernameDisponible($username, $id_excluir)
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE username = ? AND id_usuario != ?");
        $stmt->execute([$username, $id_excluir]);
        return !$stmt->fetch(); // Retorna true si NO encontró a nadie más con ese nombre
    }


    public function updateProfile($id_usuario, $username, $nombre, $bio, $foto_blob = null)
    {
        try {
            if ($foto_blob !== null) {
                // Actualización con foto
                $sql = "UPDATE usuarios SET username = ?, nombre = ?, bio = ?, foto_perfil = ? WHERE id_usuario = ?";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$username, $nombre, $bio, $foto_blob, $id_usuario]);
            } else {
                // Actualización sin cambiar la foto
                $sql = "UPDATE usuarios SET username = ?, nombre = ?, bio = ? WHERE id_usuario = ?";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$username, $nombre, $bio, $id_usuario]);
            }
        } catch (PDOException $e) {
            // Esto nos ayudará a saber si hubo un error de base de datos
            error_log("Error en updateProfile: " . $e->getMessage());
            return false;
        }
    }
}
