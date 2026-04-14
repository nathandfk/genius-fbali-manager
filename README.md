# Fbali Webhook Manager

Plugin WordPress / WooCommerce qui :
- Ajoute un champ **Taille Fbali** sur chaque variation produit
- Modifie les **payloads webhook** (commandes et produits) pour injecter les attributs `genius_color`, `genius_label`, `genius_size` et `genius_taille_fbali`
- Se **met à jour automatiquement** depuis ce dépôt GitHub

---

## Installation

1. Télécharger le ZIP de la dernière [release](../../releases/latest)
2. Dans WordPress → Extensions → Ajouter → Téléverser une extension
3. Activer le plugin

---

## Mise à jour automatique via GitHub

Le plugin vérifie automatiquement les nouvelles versions toutes les 12 heures.

### Étapes pour publier une mise à jour

1. Modifier le code dans le dépôt
2. Mettre à jour la constante `FWM_VERSION` dans `fbali-webhook-manager.php`  
   _ex : `define( 'FWM_VERSION', '1.0.1' );`_
3. Créer une **Release GitHub** avec un tag correspondant à la version  
   _ex : tag `1.0.1` ou `v1.0.1`_
4. Joindre le **ZIP du plugin** à la release (ou laisser GitHub générer le ZIP automatiquement)

WordPress affichera alors la mise à jour dans **Tableau de bord → Mises à jour**.

---

## Configuration initiale

Dans `fbali-webhook-manager.php`, modifier les deux constantes :

```php
define( 'FWM_GITHUB_USER', 'VOTRE-USERNAME' );        // Votre nom d'utilisateur GitHub
define( 'FWM_GITHUB_REPO', 'fbali-webhook-manager' ); // Nom exact du dépôt GitHub
```

### Dépôt privé

Si le dépôt est privé, passer un token d'accès personnel GitHub lors de l'instanciation dans `fbali-webhook-manager.php` :

```php
new Fbali_Github_Updater( FWM_PLUGIN_FILE, FWM_GITHUB_USER, FWM_GITHUB_REPO, 'ghp_votre_token' );
```

---

## Structure du plugin

```
fbali-webhook-manager/
├── fbali-webhook-manager.php          ← Point d'entrée principal
└── includes/
    ├── class-fbali-variation-field.php   ← Champ "Taille Fbali" en back-office
    ├── class-fbali-webhook-order.php     ← Modification payload webhook commande
    ├── class-fbali-webhook-product.php   ← Modification payload webhook produit
    └── class-fbali-github-updater.php    ← Mise à jour automatique GitHub
```

---

## Changelog

### 1.0.0
- Version initiale
# genius-fbali-manager
