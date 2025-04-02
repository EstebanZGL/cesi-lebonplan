document.addEventListener("DOMContentLoaded", function () {
    fetch("app/views/login/session.php", {
        headers: {
            'Accept': 'application/json; charset=utf-8',
        }
    })
        .then(response => {
            // Cloner la réponse pour pouvoir la lire deux fois
            const clone = response.clone();
            
            // Afficher la réponse brute pour débogage
            clone.text().then(text => {
                console.log("Réponse brute du serveur:", text);
            });
            
            return response.json();
        })
        .then(data => {
            // Afficher les données pour débogage
            console.log("Données parsées:", data);
            
            const loginBouton = document.getElementById("login-Bouton");
            const logoutBouton = document.getElementById("logout-Bouton");
            const welcomeMessage = document.getElementById("welcome-message");
            const pageGestion = document.getElementById("page-gestion");
            const pageDashboard = document.getElementById("page-dashboard");
            const pageAdmin= document.getElementById("page-admin");
            const mobilePageGestion = document.getElementById("mobile-page-gestion");
            const mobilePageDashboard = document.getElementById("mobile-page-dashboard");
            const mobilePageAdmin = document.getElementById("mobile-page-admin");
            const mobileLoginBouton = document.getElementById("mobile-login-Bouton");
            const mobileLogoutBouton = document.getElementById("mobile-logout-Bouton");

            if (data.logged_in) {
                loginBouton.style.display = "none";
                logoutBouton.style.display = "inline-block";
                if (mobileLoginBouton) mobileLoginBouton.style.display = "none";
                if (mobileLogoutBouton) mobileLogoutBouton.style.display = "inline-block";

                // Afficher un message de bienvenue avec le prénom de l'utilisateur
                let userTypeLabel;
                
                // Afficher le prénom brut pour débogage
                console.log("Prénom brut:", data.prenom);
                
                // Ne pas utiliser decodeURIComponent(escape()) car cela peut causer des problèmes
                const userFirstName = data.prenom || "";
                const userType = parseInt(data.utilisateur);
                
                switch (userType) {
                    case 0: // Étudiant
                        userTypeLabel = "Étudiant";
                        welcomeMessage.classList.add('etudiant');
                        
                        // Afficher le lien vers le tableau de bord pour les étudiants
                        if (pageDashboard) pageDashboard.style.display = "inline-block";
                        if (mobilePageDashboard) mobilePageDashboard.style.display = "block";
                        
                        // Afficher la section wishlist dans la barre latérale si on est sur la page des offres
                        if (window.location.pathname.includes('offres')) {
                            const wishlistSection = document.getElementById('wishlist-section');
                            if (wishlistSection) {
                                wishlistSection.style.display = 'block';
                            }
                        }
                        break;
                    case 1:
                        userTypeLabel = "Pilote";
                        welcomeMessage.classList.add('pilote');
                        pageGestion.style.display = "inline-block";
                        if (mobilePageGestion) mobilePageGestion.style.display = "inline-block";
                        break;
                    case 2:
                        userTypeLabel = "Admin";
                        welcomeMessage.classList.add('admin');
                        pageGestion.style.display = "inline-block";
                        pageAdmin.style.display = "inline-block";
                        if (mobilePageGestion) mobilePageGestion.style.display = "inline-block";
                        if (mobilePageAdmin) mobilePageAdmin.style.display = "inline-block";
                        break;
                    default:
                        userTypeLabel = "Bienvenue";
                }
                
                // Construire le message de bienvenue avec le prénom
                let welcomeText = userFirstName ? userFirstName : userTypeLabel;
                
                // Afficher le texte final pour débogage
                console.log("Texte de bienvenue:", welcomeText);
                
                welcomeMessage.textContent = welcomeText;
                welcomeMessage.style.display = "inline-block"; // Affiche le message
            } else {
                loginBouton.style.display = "inline-block";
                logoutBouton.style.display = "none";
                if (mobileLoginBouton) mobileLoginBouton.style.display = "inline-block";
                if (mobileLogoutBouton) mobileLogoutBouton.style.display = "none";
                welcomeMessage.style.display = "none"; // Cache le message de bienvenue
                if (pageGestion) pageGestion.style.display = "none";
                if (pageAdmin) pageAdmin.style.display = "none"; // Cache la page administrateur
                if (pageDashboard) pageDashboard.style.display = "none";
                if (mobilePageGestion) mobilePageGestion.style.display = "none";
                if (mobilePageAdmin) mobilePageAdmin.style.display = "none";
                if (mobilePageDashboard) mobilePageDashboard.style.display = "none";
                
                // Masquer la section wishlist dans la barre latérale si on est sur la page des offres
                if (window.location.pathname.includes('offres')) {
                    const wishlistSection = document.getElementById('wishlist-section');
                    if (wishlistSection) {
                        wishlistSection.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => console.error("Erreur lors de la récupération de la session :", error));
});