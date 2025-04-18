<?php
class EtudiantModel {
    private $db;
    
    public function __construct() {
        require_once 'config/database.php';
        $this->db = getDbConnection();
    }
    
    public function getEtudiants($limit = 10, $offset = 0) {
        $query = "SELECT e.*, u.nom, u.prenom, u.email, o.titre as offre_titre,
                 (SELECT COUNT(*) FROM wishlist w WHERE w.etudiant_id = e.id) as nb_offres_wishlist 
                 FROM etudiant e 
                 LEFT JOIN utilisateur u ON e.utilisateur_id = u.id 
                 LEFT JOIN offre_stage o ON e.offre_id = o.id 
                 ORDER BY u.nom, u.prenom ASC 
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countEtudiants() {
        $query = "SELECT COUNT(*) as total FROM etudiant";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }
    
    public function getEtudiantById($id) {
        $query = "SELECT e.*, u.nom, u.prenom, u.email 
                 FROM etudiant e 
                 LEFT JOIN utilisateur u ON e.utilisateur_id = u.id 
                 WHERE e.id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUtilisateursForSelect() {
        $query = "SELECT id, CONCAT(nom, ' ', prenom, ' (', email, ')') as nom_complet 
                 FROM utilisateur 
                 WHERE id NOT IN (SELECT utilisateur_id FROM etudiant) 
                 ORDER BY nom, prenom ASC";
        $stmt = $this->db->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createEtudiant($data) {
        $query = "INSERT INTO etudiant (utilisateur_id, promotion, formation, offre_id) 
                 VALUES (:utilisateur_id, :promotion, :formation, :offre_id)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':utilisateur_id', $data['utilisateur_id'], PDO::PARAM_INT);
        $stmt->bindParam(':promotion', $data['promotion'], PDO::PARAM_STR);
        $stmt->bindParam(':formation', $data['formation'], PDO::PARAM_STR);
        $stmt->bindParam(':offre_id', $data['offre_id'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    public function updateEtudiant($id, $data) {
        $query = "UPDATE etudiant SET 
                 promotion = :promotion, 
                 formation = :formation, 
                 offre_id = :offre_id 
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':promotion', $data['promotion'], PDO::PARAM_STR);
        $stmt->bindParam(':formation', $data['formation'], PDO::PARAM_STR);
        $stmt->bindParam(':offre_id', $data['offre_id'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    public function deleteEtudiant($id) {
        try {
            // Commencer une transaction
            $this->db->beginTransaction();
            
            // D'abord, récupérer l'ID de l'utilisateur associé à cet étudiant
            $query = "SELECT utilisateur_id FROM etudiant WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$etudiant || !isset($etudiant['utilisateur_id'])) {
                $this->db->rollBack();
                error_log("Erreur: Étudiant avec ID $id non trouvé ou sans utilisateur associé");
                return false;
            }
            
            $utilisateur_id = $etudiant['utilisateur_id'];
            
            // Supprimer d'abord les candidatures liées
            $query = "DELETE FROM candidature WHERE etudiant_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les entrées de la wishlist
            $query = "DELETE FROM wishlist WHERE etudiant_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer l'étudiant
            $query = "DELETE FROM etudiant WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer l'utilisateur associé
            $query = "DELETE FROM utilisateur WHERE id = :utilisateur_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Valider la transaction
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            // En cas d'erreur, annuler les modifications
            $this->db->rollBack();
            error_log("Erreur lors de la suppression de l'étudiant et de l'utilisateur associé: " . $e->getMessage());
            return false;
        }
    }
    
    public function getEtudiantStats() {
        $stats = [];
        
        // Nombre total d'étudiants
        $query = "SELECT COUNT(*) as total FROM etudiant";
        $stmt = $this->db->query($query);
        $stats['total'] = $stmt->fetchColumn();
        
        // Répartition par promotion
        $query = "SELECT promotion, COUNT(*) as count 
                 FROM etudiant 
                 GROUP BY promotion 
                 ORDER BY count DESC";
        $stmt = $this->db->query($query);
        $stats['par_promotion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Répartition par formation
        $query = "SELECT formation, COUNT(*) as count 
                 FROM etudiant 
                 GROUP BY formation 
                 ORDER BY count DESC";
        $stmt = $this->db->query($query);
        $stats['par_formation'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Nombre d'étudiants avec une offre assignée
        $query = "SELECT COUNT(*) as count FROM etudiant WHERE offre_id IS NOT NULL";
        $stmt = $this->db->query($query);
        $stats['avec_offre'] = $stmt->fetchColumn();
        
        // NOUVELLE STATISTIQUE: Nombre d'étudiants ayant au moins une candidature acceptée
        $query = "SELECT COUNT(DISTINCT e.id) as count
                 FROM etudiant e
                 JOIN candidature c ON e.id = c.etudiant_id
                 WHERE c.statut = 'ACCEPTEE'";
        $stmt = $this->db->query($query);
        $stats['avec_candidature_acceptee'] = $stmt->fetchColumn();
        
        // Nombre de candidatures par étudiant - REQUÊTE CORRIGÉE
        $query = "SELECT u.nom, u.prenom, COUNT(c.id) as nb_candidatures 
                 FROM etudiant e 
                 JOIN utilisateur u ON e.utilisateur_id = u.id 
                 JOIN candidature c ON e.id = c.etudiant_id 
                 GROUP BY e.id, u.nom, u.prenom 
                 ORDER BY nb_candidatures DESC 
                 LIMIT 10";
        $stmt = $this->db->query($query);
        $stats['candidatures'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Activité des étudiants (candidatures + wishlist)
        $query = "SELECT 
                    u.nom, 
                    u.prenom, 
                    IFNULL(c.nb_candidatures, 0) as nb_candidatures,
                    IFNULL(w.nb_wishlist, 0) as nb_wishlist,
                    (IFNULL(c.nb_candidatures, 0) + IFNULL(w.nb_wishlist, 0)) as total
                 FROM 
                    etudiant e
                    JOIN utilisateur u ON e.utilisateur_id = u.id
                    LEFT JOIN (
                        SELECT etudiant_id, COUNT(*) as nb_candidatures 
                        FROM candidature 
                        GROUP BY etudiant_id
                    ) c ON e.id = c.etudiant_id
                    LEFT JOIN (
                        SELECT etudiant_id, COUNT(*) as nb_wishlist 
                        FROM wishlist 
                        GROUP BY etudiant_id
                    ) w ON e.id = w.etudiant_id
                 WHERE 
                    (IFNULL(c.nb_candidatures, 0) > 0 OR IFNULL(w.nb_wishlist, 0) > 0)
                 ORDER BY 
                    total DESC, nb_candidatures DESC, nb_wishlist DESC
                 LIMIT 10";
        $stmt = $this->db->query($query);
        $stats['activite_etudiants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // AJOUT: Statistiques des candidatures par statut
        $query = "SELECT 
                    COUNT(*) as total_candidatures,
                    SUM(CASE WHEN statut = 'ACCEPTEE' THEN 1 ELSE 0 END) as candidatures_acceptees,
                    SUM(CASE WHEN statut = 'REFUSEE' THEN 1 ELSE 0 END) as candidatures_refusees,
                    SUM(CASE WHEN statut = 'EN_ATTENTE' THEN 1 ELSE 0 END) as candidatures_en_attente
                  FROM candidature";
        $stmt = $this->db->query($query);
        $candidatures_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculer les pourcentages
        $stats['candidatures_stats'] = $candidatures_stats;
        $stats['candidatures_stats']['pourcentage_acceptees'] = ($candidatures_stats['total_candidatures'] > 0) ? 
            round(($candidatures_stats['candidatures_acceptees'] / $candidatures_stats['total_candidatures']) * 100, 2) : 0;
        $stats['candidatures_stats']['pourcentage_refusees'] = ($candidatures_stats['total_candidatures'] > 0) ? 
            round(($candidatures_stats['candidatures_refusees'] / $candidatures_stats['total_candidatures']) * 100, 2) : 0;
        $stats['candidatures_stats']['pourcentage_en_attente'] = ($candidatures_stats['total_candidatures'] > 0) ? 
            round(($candidatures_stats['candidatures_en_attente'] / $candidatures_stats['total_candidatures']) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Met à jour un étudiant par son ID utilisateur
     */
    public function updateEtudiantByUserId($utilisateur_id, $data) {
        try {
            // Vérifier d'abord si l'étudiant existe
            $stmt = $this->db->prepare("SELECT id FROM etudiant WHERE utilisateur_id = :utilisateur_id");
            $stmt->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
            $stmt->execute();
            $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($etudiant) {
                // Mettre à jour l'étudiant existant
                $query = "UPDATE etudiant SET 
                         promotion = :promotion, 
                         formation = :formation";
                
                if (isset($data['offre_id'])) {
                    $query .= ", offre_id = :offre_id";
                }
                
                $query .= " WHERE utilisateur_id = :utilisateur_id";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
                $stmt->bindParam(':promotion', $data['promotion'], PDO::PARAM_STR);
                $stmt->bindParam(':formation', $data['formation'], PDO::PARAM_STR);
                
                if (isset($data['offre_id'])) {
                    $stmt->bindParam(':offre_id', $data['offre_id'], PDO::PARAM_INT);
                }
                
                return $stmt->execute();
            } else {
                // Créer un nouvel étudiant
                $query = "INSERT INTO etudiant (utilisateur_id, promotion, formation, offre_id) 
                         VALUES (:utilisateur_id, :promotion, :formation, :offre_id)";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
                $stmt->bindParam(':promotion', $data['promotion'], PDO::PARAM_STR);
                $stmt->bindParam(':formation', $data['formation'], PDO::PARAM_STR);
                $stmt->bindParam(':offre_id', $data['offre_id'], PDO::PARAM_INT);
                
                return $stmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'étudiant par ID utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    public function searchEtudiants($search, $limit = 10, $offset = 0) {
        try {
            $search = '%' . $search . '%';
            $query = "SELECT e.*, u.nom, u.prenom, u.email,
                     (SELECT COUNT(*) FROM wishlist w WHERE w.etudiant_id = e.id) as nb_offres_wishlist
                     FROM etudiant e
                     JOIN utilisateur u ON e.utilisateur_id = u.id
                     WHERE u.nom LIKE :search 
                        OR u.prenom LIKE :search 
                        OR u.email LIKE :search 
                        OR e.promotion LIKE :search
                        OR e.formation LIKE :search
                     ORDER BY u.nom, u.prenom ASC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':search', $search, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la recherche des étudiants: " . $e->getMessage());
            return [];
        }
    }
    
    public function countEtudiantsSearch($search) {
        try {
            $search = '%' . $search . '%';
            $query = "SELECT COUNT(*) as total 
                     FROM etudiant e
                     JOIN utilisateur u ON e.utilisateur_id = u.id
                     WHERE u.nom LIKE :search 
                        OR u.prenom LIKE :search 
                        OR u.email LIKE :search 
                        OR e.promotion LIKE :search
                        OR e.formation LIKE :search";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':search', $search, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("Erreur lors du comptage des étudiants recherchés: " . $e->getMessage());
            return 0;
        }
    }
}
?>