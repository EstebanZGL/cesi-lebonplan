<?php
// Démarrer la session seulement si elle n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'app/models/UserModel.php';

class LoginController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function index() {
        // Si l'utilisateur est déjà connecté, le rediriger vers la page d'accueil
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            header("Location: /home");
            exit;
        }
        
        // Afficher le formulaire de connexion
        require 'app/views/login/login.php';
    }

    public function authenticate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (!empty($email) && !empty($password)) {
                // Récupérer l'utilisateur avec l'email fourni
                $user = $this->userModel->getUserByEmail($email);
                
                error_log("Données utilisateur récupérées: " . print_r($user, true));

                // Vérification du mot de passe hashé
                if ($user && password_verify($password, $user['mdp'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['utilisateur'] = $user['utilisateur'];
                    $_SESSION['logged_in'] = true;
                    
                    // Stocker explicitement le prénom et le nom de l'utilisateur en session
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['nom'] = $user['nom'];
                    
                    // Ajouter un log pour vérifier les valeurs stockées en session
                    error_log("Session après authentification: " . print_r($_SESSION, true));

                    // Redirection vers la page d'accueil avec chemin absolu
                    header("Location: /home");
                    exit;
                } else {
                    // Ajouter un log pour le débogage
                    error_log("Échec de connexion pour {$email} - Mot de passe incorrect ou utilisateur non trouvé");
                    
                    // Redirection en cas d'erreur avec chemin absolu
                    $_SESSION['error'] = "Email ou mot de passe incorrect";
                    header("Location: /login");
                    exit;
                }
            } else {
                $_SESSION['error'] = "Veuillez remplir tous les champs";
                header("Location: /login");
                exit;
            }
        } else {
            header("Location: /login");
            exit;
        }
    }
}