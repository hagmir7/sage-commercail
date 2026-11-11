
<?php

return [
    'commandes_en_cours' => [
        'label' => 'Commandes en cours',
        'permissions' => [
            'voir' => 'Voir',
            'imprimer' => 'Imprimer',
            'exporter' => 'Exporter',
            'transformer' => 'Transformer',
            'reinitialiser' => 'Réinitialiser',
            'vente_comptoir' => 'Vente comptoir',
            'modifier' => 'Modifier',
            'archiver' => 'Archiver',
        ],
    ],

    'societe_commandes' => [
        'label' => 'Commandes société',
        'permissions' => [
            'voir' => 'Voir',
            'imprimer' => 'Imprimer',
            'imprimer_billets' => 'Imprimer billets',
            'valider' => 'Valider',
            'valider_partiellement' => 'Valider partiellement',
            'voir_archive' => 'Voir archive',
            'transformer' => 'Transformer',
            'exporter' => 'Exporter',
            'controler' => 'Contrôler',
        ],
    ],

    'fabrication_commandes' => [
        'label' => 'Commandes fabrication',
        'permissions' => [
            'voir' => 'Voir',
            'imprimer' => 'Imprimer',
            'modifier_date_prevue' => 'Modifier date prévue',
            'ajouter_remarque' => 'Ajouter remarque',
            'valider' => 'Valider',
            'voir_archive' => 'Voir archive',
        ],
    ],

    'fabrication_peinture' => [
        'label' => 'Fabrication peinture',
        'permissions' => [
            'voir' => 'Voir',
            'imprimer' => 'Imprimer',
            'modifier_date_prevue' => 'Modifier date prévue',
            'ajouter_remarque' => 'Ajouter remarque',
            'valider' => 'Valider',
            'voir_archive' => 'Voir archive',
        ],
    ],

    'fabrication_montage' => [
        'label' => 'Fabrication montage',
        'permissions' => [
            'voir' => 'Voir',
            'imprimer' => 'Imprimer',
            'valider' => 'Valider',
            'voir_archive' => 'Voir archive',
        ],
    ],

    'stock_fabrication' => [
        'label' => 'Stock fabrication',
        'permissions' => [
            'voir' => 'Voir',
            'voir_article' => 'Voir article',
            'voir_capacite' => 'Voir capacité',
            'importer' => 'Importer',
            'creer_modifier_capacite' => 'Créer et modifier capacité',
            'creer_article' => 'Créer article',
            'modifier_article' => 'Modifier article',
            'supprimer_article' => 'Supprimer article',
        ],
    ],

    'ordres_fabrication' => [
        'label' => 'Ordres de fabrication',
        'permissions' => [
            'voir' => 'Voir',
            'creer' => 'Créer',
            'imprimer' => 'Imprimer',
            'dupliquer' => 'Dupliquer',
            'modifier_statut' => 'Modifier statut',
            'supprimer' => 'Supprimer',
        ],
    ],


    // Fournisseurs
    'fournisseurs' => [
        'label' => 'Fournisseurs',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer',
        ],
    ],

    // Évaluation fournisseur
    'evaluation_fournisseur' => [
        'label' => 'Évaluation fournisseur',
        'permissions' => [
            'creer' => 'Créer',
            'voir' => 'Afficher',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer',
        ],
    ],

    // Utilisateurs
    'utilisateurs' => [
        'label' => 'Utilisateurs',
        'permissions' => [
            'voir' => 'Afficher',
            'mettre_a_jour' => 'Mettre à jour',
            'creer' => 'Créer',
            'supprimer' => 'Supprimer',
            'modifier_mot_de_passe' => 'Modifier mot de passe utilisateur',
            'voir_actions' => 'Afficher actions utilisateur',
        ],
    ],

    // Rôles
    'roles' => [
        'label' => 'Rôles',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'supprimer' => 'Supprimer',
            'attribuer' => 'Attribuer',
        ],
    ],

    // Permissions
    'permissions' => [
        'label' => 'Permissions',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer',
            'attribuer' => 'Attribuer',
        ],
    ],

    // Dépôts
    'depots' => [
        'label' => 'Dépôts',
        'permissions' => [
            'voir' => 'Afficher',
            'supprimer' => 'Supprimer',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'exporter' => 'Exporter',
        ],
    ],

    // Emplacements
    'emplacements' => [
        'label' => 'Emplacements',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer',
            'importer' => 'Importer',
            'exporter' => 'Exporter',
        ],
    ],

    // Palettes
    'palettes' => [
        'label' => 'Palettes',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'supprimer' => 'Supprimer',
            'modifier' => 'Modifier',
        ],
    ],

    // Articles
    'articles' => [
        'label' => 'Articles',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'importer' => 'Importer',
            'exporter' => 'Exporter',
            'supprimer' => 'Supprimer',
        ],
    ],

    // Mouvements
    'mouvements' => [
        'label' => 'Mouvements',
        'permissions' => [
            'voir' => 'Afficher tous les mouvements',
            'exporter' => 'Exporter tous les mouvements',
            'in.voir' => 'Afficher les entrées',
            'in.creer' => 'Créer une entrée',
            'in.modifier' => 'Modifier une entrée',
            'in.supprimer' => 'Supprimer une entrée',
            'in.exporter' => 'Exporter les entrées',
            'out.voir' => 'Afficher les sorties',
            'out.creer' => 'Créer une sortie',
            'out.modifier' => 'Modifier une sortie',
            'out.supprimer' => 'Supprimer une sortie',
            'out.exporter' => 'Exporter les sorties',
            'transfer.voir' => 'Afficher les transferts',
            'transfer.creer' => 'Créer un transfert',
            'transfer.modifier' => 'Modifier un transfert',
            'transfer.supprimer' => 'Supprimer un transfert',
            'transfer.exporter' => 'Exporter les transferts',
        ],
    ],


    'documents_achat' => [
        'label' => "Documents d'achat",
        'permissions' => [
            'voir_tout' => 'Voir tout',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'transformer' => 'Transformer',
            'modifier_statut' => 'Modifier statut',
            'imprimer' => 'Imprimer',
        ],
    ],

    'non_conformites_fournisseur' => [
        'label' => 'Non-conformités fournisseur',
        'permissions' => [
            'voir' => 'Afficher',
            'creer' => 'Créer',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer',
            'valider' => 'Valider',
        ],
    ],

    'controle_documents' => [
        'label' => 'Contrôle des documents',
        'permissions' => [
            'voir' => 'Afficher',
            'controler' => 'Contrôler',
            'rejeter' => 'Rejeter',
            'ajouter_remarque' => 'Ajouter une remarque',
        ],
    ],

    'archives' => [
        'label' => 'Archives',
        'permissions' => [
            'global.voir' => 'Voir toutes les archives',
            'company.voir' => 'Voir les archives société',
            'fabrication.voir' => 'Voir les archives fabrication',
            'montage.voir' => 'Voir les archives montage',
            'peinture.voir' => 'Voir les archives peinture',
            'preparation.voir' => 'Voir les archives préparation',
        ],
    ],
    'inventaire' => [
        'label' => 'Inventaire',
        'permissions' => [
            'voir' => 'Voir les inventaires',
            'creer' => 'Créer un inventaire',
            'modifier' => 'Modifier un inventaire',
            'supprimer' => 'Supprimer un inventaire',
            'voir.indicateur' => 'Voir l\'indicateur d\'inventaire',
            'reinitialiser' => 'Réinitialiser un inventaire',
            'integrer' => 'Intégrer un inventaire',
            'traiter' => 'Traiter un inventaire',
            'exporter' => 'Exporter des inventaires',
        ],
    ],
];
