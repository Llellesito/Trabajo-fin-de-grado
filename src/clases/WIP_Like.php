<?php
class Like
{
    private $db;

    public function __construct($db_connection)
    {
        $this->db = $db_connection;
    }

    public function toggleLike($user_id, $post_id)
    {
        // 1. Verificar si ya existe el like
        $query = "SELECT * FROM likes WHERE user_id = :u AND post_id = :p";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['u' => $user_id, 'p' => $post_id]);

        if ($stmt->fetch()) {
            // 2. Si existe, lo quitamos (Dislike)
            $delete = "DELETE FROM likes WHERE user_id = :u AND post_id = :p";
            $stmt = $this->db->prepare($delete);
            return $stmt->execute(['u' => $user_id, 'p' => $post_id]);
        } else {
            // 3. Si no existe, lo ponemos (Like)
            $insert = "INSERT INTO likes (user_id, post_id) VALUES (:u, :p)";
            $stmt = $this->db->prepare($insert);
            return $stmt->execute(['u' => $user_id, 'p' => $post_id]);
        }
    }

    public function countLikes($post_id)
    {
        $query = "SELECT COUNT(*) as total FROM likes WHERE post_id = :p";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['p' => $post_id]);
        return $stmt->fetch()['total'];
    }
}
