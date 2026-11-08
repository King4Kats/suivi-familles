<?php
// SPDX-FileCopyrightText: 2026 Flavien Auvray
// SPDX-License-Identifier: AGPL-3.0-or-later
/**
 * Crée UNE famille de démonstration, entièrement FICTIVE, directement dans Nextcloud
 * (sans passer par un mot de passe : le script tourne dans le conteneur).
 *
 *   php famille-demo.php <direction> [<référent> [<psychologue>]]
 *   ex. : php /opt/apps-familles/famille-demo.php claire thomas paul
 *
 * <direction>   : un compte de la direction (admin ou groupe « direction ») qui crée la fiche.
 * <référent>    : reçoit l'accès « peut modifier » et devient référent·e (par défaut : <direction>).
 * <psychologue> : facultatif, reçoit un accès en lecture + notes de suivi.
 * Relançable : la famille « Famille Durand (démo) » est d'abord supprimée si elle existe.
 */

require '/var/www/html/lib/base.php';
\OC_App::loadApp('suivi_familles');

use OCA\SuiviFamilles\Service\FamillesService;
use OCA\SuiviFamilles\Service\Store;

$uid = $argv[1] ?? 'admin';
$referent = $argv[2] ?? $uid;
$psy = $argv[3] ?? null;
$NOM = 'Famille Durand (démo)';
$s = \OCP\Server::get(FamillesService::class);
$store = \OCP\Server::get(Store::class);
$jours = fn (int $n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));

foreach ($store->familles() as $f) {
    if ($f['nom'] === $NOM) { $s->supprimerFamille($uid, $f['id']); echo "ancienne démo supprimée\n"; }
}

$f = $s->creerFamille($uid, [
    'nom' => $NOM, 'telephone' => '06 00 00 00 00', 'email' => 'famille.durand@exemple.fr',
    'adresse' => '3 impasse des Mimosas', 'commune' => 'Saint-Jean-de-Monts', 'territoire' => 'Océan-Marais de Monts',
    'orientation' => 'Médecin, professionnel de santé', 'dateEntree' => $jours(-75),
    'besoins' => ['Santé mentale, bien-être', 'Sommeil', 'Parentalité'], 'consentement' => $jours(-74),
    'situation' => "FAMILLE FICTIVE, pour découvrir l'appli.\nCouple avec deux enfants. Le père, en arrêt depuis l'hiver, se sent épuisé ; Lucas (11 ans) dort mal depuis le déménagement.\nOrientée par le médecin traitant.",
]);
$id = $f['id'];

$membres = [];
foreach ([
    ['prenom' => 'Julien', 'nom' => 'Durand', 'lien' => 'Père', 'dateNaissance' => '1984-03-12', 'telephone' => '06 00 00 00 00', 'medecinTraitant' => 'Dr Martin, Saint-Jean-de-Monts', 'etablissement' => 'En arrêt de travail', 'autresSuivis' => 'Sophrologie'],
    ['prenom' => 'Claire', 'nom' => 'Durand', 'lien' => 'Mère', 'dateNaissance' => '1986-09-05', 'medecinTraitant' => 'Dr Martin, Saint-Jean-de-Monts', 'etablissement' => 'Infirmière, horaires de nuit'],
    ['prenom' => 'Lucas', 'lien' => 'Fils', 'dateNaissance' => '2015-05-20', 'medecinTraitant' => 'Dr Martin, Saint-Jean-de-Monts', 'etablissement' => 'École élémentaire, CM2', 'vigilance' => 'Asthme : ventoline dans le cartable.'],
    ['prenom' => 'Emma', 'lien' => 'Fille', 'dateNaissance' => '2020-01-14', 'etablissement' => 'École maternelle, moyenne section'],
] as $m) {
    $membres[$m['prenom']] = $s->creerMembre($uid, $id, $m)['id'];
}

// L'équipe : qui a accès à la fiche, avec quelle fonction.
$s->ajouterAcces($uid, $id, $referent, 'Référent·e', true);
$s->definirReferent($uid, $id, $referent);
if ($psy) { $s->ajouterAcces($uid, $id, $psy, 'Psychologue', false); }

foreach ([['Retrouver un sommeil régulier pour Lucas', 'en_cours'], ['Soulager l\'épuisement de Julien', 'en_cours'], ['Trouver un mode de garde le mercredi', 'atteint']] as [$titre, $statut]) {
    $o = $s->creerObjectif($uid, $id, ['titre' => $titre]);
    if ($statut !== 'en_cours') { $s->modifierObjectif($uid, $o['id'], ['statut' => $statut]); }
}

foreach ([
    ['texte' => 'Premier entretien à domicile avec Julien et Claire. Présentation de l\'association, accord de la famille pour le suivi.', 'dateSuivi' => $jours(-74), 'type' => 'Visite à domicile', 'duree' => 90],
    ['texte' => 'Appel au médecin traitant (avec l\'accord de la famille) pour coordonner le suivi de Julien.', 'dateSuivi' => $jours(-60), 'type' => 'Appel téléphonique', 'duree' => 15],
    ['texte' => 'Entretien avec Lucas : il parle d\'angoisses le soir depuis le déménagement.', 'dateSuivi' => $jours(-40), 'type' => 'Entretien', 'duree' => 45, 'membreId' => $membres['Lucas']],
    ['texte' => 'Exemple de note privée : visible seulement par son auteur et la direction.', 'dateSuivi' => $jours(-39), 'type' => 'Entretien', 'membreId' => $membres['Julien'], 'prive' => true],
    ['texte' => 'Atelier « Bien dormir » : Claire et Lucas présents, bonne participation.', 'dateSuivi' => $jours(-14), 'type' => 'Atelier collectif', 'duree' => 120],
] as $suivi) {
    // Les entretiens avec Lucas sont notés par le psychologue (s'il y en a un), le reste par le référent.
    $auteur = ($psy && ($suivi['type'] ?? '') === 'Entretien') ? $psy : $referent;
    $s->creerSuivi($auteur, $id, $suivi);
}

foreach ([
    ['titre' => 'Rendez-vous avec la famille à domicile', 'genre' => 'rdv', 'echeance' => $jours(4), 'heure' => '10:00', 'responsable' => $referent],
    ['titre' => 'Appeler l\'école de Lucas', 'genre' => 'action', 'echeance' => $jours(-1), 'responsable' => $referent],
    ['titre' => 'Envoyer le programme des ateliers parentalité', 'genre' => 'action', 'echeance' => $jours(10), 'responsable' => $referent],
] as $a) {
    $s->creerAction($uid, $id, $a);
}
$fait = $s->creerAction($referent, $id, ['titre' => 'Transmettre la liste des modes de garde', 'genre' => 'action', 'echeance' => $jours(-20), 'responsable' => $referent]);
$s->modifierAction($referent, $fait['id'], ['fait' => true]);

echo "OK : « $NOM » créée (n° $id)\n";
