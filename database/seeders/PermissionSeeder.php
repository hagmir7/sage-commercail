<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            // Fournisseurs
            'Afficher : fournisseurs',
            'Créer : fournisseurs',
            'Modifier : fournisseurs',
            'Supprimer : fournisseur',

            // Évaluation fournisseur
            'Créer : évaluation fournisseur',
            'Afficher : évaluation fournisseur',
            'Modifier : évaluation fournisseur',
            'Supprimer : évaluation fournisseur',

            // Utilisateurs
            'Afficher : utilisateurs',
            'Mettre à jour : utilisateurs',
            'Créer : utilisateurs',
            'Supprimer : utilisateurs',
            'Modifier : mot de passe utilisateur',
            'Afficher : actions utilisateur',

            // Rôles
            'Afficher : rôles',
            'Créer : rôles',
            'Supprimer : rôles',
            'Attribuer : rôles',

            // Permissions
            'Afficher : permissions',
            'Créer : permissions',
            'Modifier : permissions',
            'Supprimer : permissions',
            'Attribuer : permissions',

            // Dépôts
            'Afficher : dépôts',
            'Supprimer : dépôts',
            'Créer : dépôts',
            'Modifier : dépôts',
            'Exporter : dépôts',

            // Emplacements
            'Afficher : emplacements',
            'Créer : emplacements',
            'Modifier : emplacements',
            'Supprimer : emplacements',
            'Importer : emplacements',
            'Exporter : emplacements',

            // Palettes
            'Afficher : palettes',
            'Créer : palettes',
            'Supprimer : palettes',
            'Modifier : palettes',

            // Articles
            'Afficher : articles',
            'Créer : articles',
            'Modifier : articles',
            'Mettre à jour : articles',
            'Importer : articles',
            'Exporter : articles',

            // Mouvements
            'Afficher : mouvements',
            'Créer : mouvements',
            'Supprimer : mouvements',
            'Modifier : mouvements',
            'Importer : mouvements',
            'Exporter : mouvements',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}