# Sesame Campus — Preuves de tests (TP01)

À remplir pendant la démo locale (captures d’écran + observations).  
**Aucun test sur un système réel non autorisé.**

**URL :** `http://localhost/portail_fasi_secure/sesame_campus/public/`

| # | Scénario | Attendu | Résultat | Preuve |
|---|----------|---------|----------|--------|
| T1 | Mot de passe incorrect (`admin@fasi.test` + faux MDP) | Message générique « Identifiants invalides » (pas « email inconnu ») | ☐ OK / ☐ KO | capture |
| T2 | 5 échecs consécutifs même IP | Message de verrouillage temporaire | ☐ OK / ☐ KO | capture + table `login_attempts` |
| T3 | Connexion valide admin | Dashboard admin | ☐ OK / ☐ KO | capture |
| T4 | Connexion enseignant | Espace enseignant distinct | ☐ OK / ☐ KO | capture |
| T5 | Connexion étudiant | Espace étudiant distinct | ☐ OK / ☐ KO | capture |
| T6 | Étudiant ouvre `?r=admin` | HTTP **403** Accès refusé | ☐ OK / ☐ KO | capture |
| T7 | Lien « Simuler une session expirée » puis navigation | Redirection login + message d’expiration | ☐ OK / ☐ KO | capture |
| T8 | Inscription étudiant (nouvel email) | Compte créé, hash en base (pas de clair) | ☐ OK / ☐ KO | capture + phpMyAdmin `password_hash` |
| T9 | Reset simulé | Lien démo → nouveau MDP → connexion OK | ☐ OK / ☐ KO | capture |
| T10 | POST sans jeton CSRF | HTTP **403** | ☐ OK / ☐ KO | capture DevTools / réponse |

## Observations

- Date / environnement : _______________________
- Conclusion : contrôles TP01 opérationnels / à corriger : _______________________
