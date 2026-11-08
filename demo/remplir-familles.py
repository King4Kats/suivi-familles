# SPDX-FileCopyrightText: 2026 Flavien Auvray
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Remplit l'appli Familles avec 4 familles FICTIVES pour essayer l'interface en local.

    python demo/remplir-familles.py [http://localhost:8150]

Prérequis : les comptes de la démo Docker (claire = direction, paul = psychologue,
marie = médecin, thomas = salarié). Relançable : les familles de démo du même nom sont d'abord supprimées.
Aucune donnée réelle : noms, dates, lieux de suivi et situations sont inventés.
"""
import datetime
import importlib.util
import os
import sys

sys.argv += [] if len(sys.argv) > 1 else ["http://localhost:8150"]

# On réutilise la fonction d'appel du script de test.
spec = importlib.util.spec_from_file_location("t", os.path.join(os.path.dirname(__file__), "..", "tests", "tester-droits.py"))
src = open(spec.origin, encoding="utf-8").read().split('print("Préparation')[0]
exec(src)  # définit appel(), BASE, MDP


def jours(n):
    """Date AAAA-MM-JJ à n jours d'aujourd'hui (négatif = passé) : la démo reste « fraîche »."""
    return (datetime.date.today() + datetime.timedelta(days=n)).isoformat()


FAMILLES = [
    {
        "famille": {"nom": "Famille Moreau", "telephone": "06 12 34 56 78", "adresse": "12 rue des Tilleuls", "commune": "Challans",
                    "territoire": "Challans-Gois Communauté", "orientation": "Établissement scolaire", "dateEntree": jours(-120),
                    "besoins": ["Santé mentale, bien-être", "Parentalité", "Sommeil"], "consentement": jours(-118),
                    "situation": "Mère seule avec deux enfants depuis 2025. Orientée par l'infirmière du collège.\nFatigue importante, Hugo a du mal à s'endormir et à se concentrer en classe."},
        "membres": [
            {"prenom": "Sandrine", "lien": "Mère", "dateNaissance": "1988-06-14", "telephone": "06 12 34 56 78", "medecinTraitant": "Dr Lambert, Challans", "etablissement": "Aide à domicile, temps partiel"},
            {"prenom": "Hugo", "lien": "Fils", "dateNaissance": "2014-02-03", "medecinTraitant": "Dr Lambert, Challans", "psychologue": "Paul Lefèvre", "autresSuivis": "Orthophoniste le mercredi", "etablissement": "Collège, 6e", "vigilance": "Allergie aux arachides (trousse PAI au collège)."},
            {"prenom": "Inès", "lien": "Fille", "dateNaissance": "2019-09-21", "medecinTraitant": "Dr Lambert, Challans", "etablissement": "École maternelle, grande section"},
        ],
        "equipe": [("paul", "Psychologue", False), ("thomas", "Référent·e", True)],
        "referent": "thomas",
        "objectifs": [("Retrouver un rythme de sommeil régulier pour Hugo", "en_cours"), ("Rompre l'isolement de Sandrine", "en_cours"), ("Mettre en place le suivi orthophonique", "atteint")],
        "suivis": [
            ("thomas", {"texte": "Premier entretien à domicile avec Sandrine. Présentation de l'association, recueil du consentement.", "dateSuivi": jours(-118), "type": "Visite à domicile", "duree": 90}),
            ("paul", {"texte": "Premier entretien avec Hugo. Bon contact, difficultés de concentration en classe.", "dateSuivi": jours(-60), "membre": "Hugo", "type": "Entretien", "duree": 45}),
            ("paul", {"texte": "Hugo évoque des tensions à la maison le soir. À reprendre avec lui seul.", "dateSuivi": jours(-32), "membre": "Hugo", "type": "Entretien", "duree": 45, "prive": True}),
            ("thomas", {"texte": "Atelier « Bien dormir » au centre social : Sandrine et Hugo présents, bonne participation.", "dateSuivi": jours(-12), "type": "Atelier collectif", "duree": 120}),
        ],
        "actions": [
            ("thomas", {"titre": "Point avec l'infirmière du collège", "genre": "rdv", "echeance": jours(3), "heure": "14:30", "responsable": "thomas"}),
            ("paul", {"titre": "Séance avec Hugo", "genre": "rdv", "echeance": jours(9), "heure": "17:00", "responsable": "paul"}),
            ("claire", {"titre": "Envoyer le programme des ateliers parentalité", "echeance": jours(-2), "responsable": "thomas"}),
        ],
    },
    {
        "famille": {"nom": "Famille Benali", "telephone": "07 98 76 54 32", "commune": "Saint-Jean-de-Monts", "territoire": "Océan-Marais de Monts",
                    "orientation": "Services sociaux (CCAS, Département)", "dateEntree": jours(-55), "besoins": ["Accès aux droits et aux soins", "Logement"],
                    "situation": "Arrivée récente dans le département. Recherche de logement en cours. Pas encore de médecin traitant."},
        "membres": [
            {"prenom": "Karim", "lien": "Père", "dateNaissance": "1982-11-02"},
            {"prenom": "Nadia", "lien": "Mère", "dateNaissance": "1985-04-27", "medecinTraitant": "En cours de déclaration"},
            {"prenom": "Yanis", "lien": "Fils", "dateNaissance": "2011-07-30", "etablissement": "Collège, 4e"},
        ],
        "equipe": [("marie", "Médecin", True)],
        "objectifs": [("Trouver un médecin traitant pour toute la famille", "en_cours")],
        "suivis": [("marie", {"texte": "Bilan de santé des enfants à prévoir, carnets de vaccination demandés.", "dateSuivi": jours(-41), "type": "Entretien", "duree": 60})],
        "actions": [("marie", {"titre": "Récupérer les carnets de vaccination", "echeance": jours(-5), "responsable": "marie"})],
    },
    {
        "famille": {"nom": "Famille Garnier", "commune": "Saint-Gilles-Croix-de-Vie", "territoire": "Pays de Saint-Gilles-Croix-de-Vie",
                    "orientation": "Médecin, professionnel de santé", "dateEntree": jours(-20), "besoins": ["Alimentation", "Activité physique"],
                    "consentement": jours(-20), "situation": "Orientée par le médecin traitant pour un accompagnement sur l'alimentation et l'activité physique de Léo."},
        "membres": [{"prenom": "Élodie", "lien": "Mère", "dateNaissance": "1990-03-08"}, {"prenom": "Léo", "lien": "Fils", "dateNaissance": "2016-10-19", "medecinTraitant": "Dr Robin, Saint-Gilles"}],
        "equipe": [],
        "objectifs": [],
        "suivis": [("claire", {"texte": "Appel de premier contact, rendez-vous fixé.", "dateSuivi": jours(-20), "type": "Appel téléphonique", "duree": 15})],
        "actions": [("claire", {"titre": "Premier rendez-vous avec Élodie et Léo", "genre": "rdv", "echeance": jours(0), "heure": "10:00", "responsable": "claire"})],
    },
    {
        "famille": {"nom": "Famille Lucas", "commune": "Challans", "territoire": "Challans-Gois Communauté", "orientation": "Démarche spontanée",
                    "dateEntree": "2025-11-10", "besoins": ["Isolement, lien social"], "consentement": "2025-11-10",
                    "situation": "Suivi terminé : objectifs atteints."},
        "membres": [{"prenom": "Julie", "lien": "Mère"}, {"prenom": "Théo", "lien": "Fils", "dateNaissance": "2016-01-11"}],
        "equipe": [],
        "objectifs": [("Participer à une activité collective chaque semaine", "atteint")],
        "suivis": [("claire", {"texte": "Clôture du suivi d'un commun accord.", "dateSuivi": jours(-25), "type": "Entretien", "duree": 30})],
        "actions": [],
        "clos": ("Objectifs atteints", jours(-25)),
    },
]

_, existantes = appel("claire", "GET", "/familles")
noms = {F["famille"]["nom"] for F in FAMILLES}
for f in existantes["familles"]:
    if f["nom"] in noms:
        appel("claire", "DELETE", f"/familles/{f['id']}")

for F in FAMILLES:
    c, f = appel("claire", "POST", "/familles", F["famille"])
    assert c == 200, (c, f)
    fid = f["id"]
    ids = {}
    for m in F["membres"]:
        c, r = appel("claire", "POST", f"/familles/{fid}/membres", m)
        assert c == 200, (c, r)
        ids[m["prenom"]] = r["id"]
    for uid, fonction, modif in F["equipe"]:
        appel("claire", "POST", f"/familles/{fid}/acces", {"userId": uid, "fonction": fonction, "peutModifier": modif})
    if F.get("referent"):
        appel("claire", "PUT", f"/familles/{fid}/referent", {"userId": F["referent"]})
    for titre, statut in F["objectifs"]:
        _, o = appel("claire", "POST", f"/familles/{fid}/objectifs", {"titre": titre})
        if statut != "en_cours":
            appel("claire", "PUT", f"/objectifs/{o['id']}", {"statut": statut})
    for uid, s in F["suivis"]:
        corps = {k: v for k, v in s.items() if k != "membre"}
        if "membre" in s:
            corps["membreId"] = ids[s["membre"]]
        c, r = appel(uid, "POST", f"/familles/{fid}/suivis", corps)
        assert c == 200, (c, r)
    for uid, a in F["actions"]:
        c, r = appel(uid, "POST", f"/familles/{fid}/actions", a)
        assert c == 200, (c, r)
    if F.get("clos"):
        appel("claire", "PUT", f"/familles/{fid}/statut", {"statut": "clos", "motif": F["clos"][0], "dateFin": F["clos"][1]})
    print("OK", F["famille"]["nom"])
